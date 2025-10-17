
<?php
// Tell the browser/client that the response will be JSON
header('Content-Type: application/json');

// Include the shared database connection file
include '../shared/db_connect.php';

// --- 1. Read the JSON body sent by JavaScript ---
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

include 'helpers.php'; // <- new file
include "match_participants.php";
include 'effort_helpers.php';  

// --- Get current ongoing session (if any) ---
$existing_session = getOngoingSession($conn);

// --- Main action handler ---
switch ($action) {

    // --- CREATE SESSION ---
    case 'create':
        if ($existing_session) {
            echo json_encode([
                'success' => false,
                'message' => 'A session is already ongoing',
                'session_id' => $existing_session['session_id']
            ]);
        } else {
            $session_name = 'Session ' . date('Y-m-d H:i:s');
            $session_status = 'ongoing';
            $conn->query("INSERT INTO sessions (session_name, created_at, session_status)
                          VALUES ('$session_name', NOW(), '$session_status')");
            $new_session_id = $conn->insert_id;

            echo json_encode([
                'success' => true,
                'message' => 'Session created',
                'session_id' => $new_session_id
            ]);
        }
        break;

    // --- DELETE SESSION ---
    case 'delete':
        if ($existing_session) {
            $conn->query("DELETE FROM sessions WHERE session_status = 'ongoing'");
            echo json_encode([
                'success' => true,
                'message' => 'Ongoing session deleted',
                'session_id' => $existing_session['session_id']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No ongoing session to delete'
            ]);
        }
        break;

    case 'create_round_1':
    case 'create_round_2':
    case 'create_round_3':
        if (!$existing_session) {
            echo json_encode(['success' => false, 'message' => 'No ongoing session']);
            break;
        }

        $session_id = (int)$existing_session['session_id'];

        // check if a live (active) round exists
        $live_round = getLiveRound($conn, $session_id);

        if ($live_round) {
            // check the active stage of that round
            $active_stage = getActiveStageByRound($conn, $live_round['round_id']);

            // if there's an active stage and it's not 'end_of_round', block creation
            if ($active_stage && $active_stage['stage_name'] !== 'end_of_round') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot create new round: current round not finished',
                    'round_id' => $live_round['round_id'],
                    'round_number' => $live_round['round_number'],
                    'current_stage' => $active_stage['stage_name']
                ]);
                break;
            }

            // if active_stage is end_of_round or there is no active_stage, finalize that round
            $conn->query("
                UPDATE rounds
                SET is_active = 0, ended_at = NOW()
                WHERE round_id = " . (int)$live_round['round_id']
            );

            // ensure any remaining active stages for that round are cleared
            $conn->query("
                UPDATE stages
                SET is_active = 0, ended_at = NOW()
                WHERE round_id = " . (int)$live_round['round_id'] . " AND is_active = 1
            ");
        }

        // determine next round number for this session
        $res = $conn->query("SELECT MAX(round_number) AS max_round FROM rounds WHERE session_id = $session_id");
        $row = $res ? $res->fetch_assoc() : null;
        $next_round_number = ((int)($row['max_round'] ?? 0)) + 1;

        // create new round and make it active
        $conn->query("
            INSERT INTO rounds (session_id, round_number, created_at, is_active)
            VALUES ($session_id, $next_round_number, NOW(), 1)
        ");
        $new_round_id = $conn->insert_id;

        // create stages for this round (linked by round_id)

        switch ($action) {
        case 'create_round_1':
            $stages = ['instructions', 'strategy_method', 'effort_task', 'outcome_realization', 'payoff_computation', 'end_of_round'];
            break;
        case 'create_round_2':
            $stages = ['instructions_type2', 'effort_task', 'outcome_realization', 'payoff_computation', 'end_of_round'];
            break;
        case 'create_round_3':
            $stages = ['instructions_type3', 'strategy_method', 'effort_task', 'outcome_realization', 'payoff_computation', 'end_of_round'];
            break;
        }


        foreach ($stages as $order => $name) {
            $is_active = ($order === 0) ? 1 : 0;
            $started_at = ($order === 0) ? 'NOW()' : 'NULL';
            // we store both round_id and session_id (session_id keeps cross-checking easy)
            $conn->query("
                INSERT INTO stages (round_id, session_id, stage_name, stage_order, is_active, started_at)
                VALUES ($new_round_id, $session_id, '" . $conn->real_escape_string($name) . "', " . ($order + 1) . ", $is_active, $started_at)
            ");
        }
        
        // Generate effort tasks for all type A participants
        generateEffortTaskPenalties($conn, $new_round_id, $session_id);

        echo json_encode([
            'success' => true,
            'message' => 'New round created with stages',
            'round_id' => $new_round_id,
            'round_number' => $next_round_number,
            'round_type' => $action
        ]);
    break;

    // --- DELETE ROUND (only active/incomplete rounds) ---
    case 'delete_round':
        if (!$existing_session) {
            echo json_encode(['success' => false, 'message' => 'No ongoing session']);
            break;
        }

        $session_id = $existing_session['session_id'];
        $live_round = getLiveRound($conn, $session_id);

        if (!$live_round) {
            echo json_encode(['success' => false, 'message' => 'No active/incomplete round to delete']);
            break;
        }

        $round_id = $live_round['round_id'];

        // 1. Delete player-level decisions for this round
        if (!$conn->query("DELETE FROM decisions WHERE round_id = $round_id")) {
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting decisions: ' . $conn->error
            ]);
            break;
        }

        // 2. Delete participant stage decisions (if you have that table)
        if ($conn->query("SHOW TABLES LIKE 'participant_stage_decisions'")->num_rows) {
            if (!$conn->query("DELETE FROM participant_stage_decisions WHERE round_id = $round_id")) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error deleting participant stage decisions: ' . $conn->error
                ]);
                break;
            }
        }

        // 3. Delete participant stage status
        if ($conn->query("SHOW TABLES LIKE 'participant_stage_status'")->num_rows) {
            if (!$conn->query("DELETE FROM participant_stage_status WHERE round_id = $round_id")) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error deleting participant stage status: ' . $conn->error
                ]);
                break;
            }
        }

        // 4. Delete effort tasks
        if (!$conn->query("DELETE FROM effort_tasks WHERE round_id = $round_id")) {
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting effort tasks: ' . $conn->error
            ]);
            break;
        }

        // 5. Delete stages
        if (!$conn->query("DELETE FROM stages WHERE round_id = $round_id")) {
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting stages: ' . $conn->error
            ]);
            break;
        }

        // 6. Finally, delete the round itself
        if (!$conn->query("DELETE FROM rounds WHERE round_id = $round_id")) {
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting round: ' . $conn->error
            ]);
            break;
        }


            echo json_encode([
                'success' => true,
                'message' => 'Active round deleted',
                'round_id' => $round_id
            ]);
    break;



    // ------------- NEXT STAGE -------------
    case 'next_stage':
        if (!$existing_session) {
            echo json_encode(['success' => false, 'message' => 'No ongoing session']);
            break;
        }

        $session_id = (int)$existing_session['session_id'];
        $live_round = getLiveRound($conn, $session_id);

        if (!$live_round) {
            echo json_encode(['success' => false, 'message' => 'No active round found']);
            break;
        }

        $round_id = (int)$live_round['round_id'];

        $active_stage = getActiveStageByRound($conn, $round_id);

        if (!$active_stage) {
            echo json_encode(['success' => false, 'message' => 'No active stage found']);
            break;
        }

        $current_stage_order = (int)$active_stage['stage_order'];
        $current_stage_name  = $active_stage['stage_name'];
        $current_stage_id    = (int)$active_stage['stage_id'];

        // End current stage
        $conn->query("UPDATE stages SET is_active = 0, ended_at = NOW() WHERE stage_id = $current_stage_id");

        // If current is end_of_round, close the round
        if ($current_stage_name === 'end_of_round') {
            $conn->query("UPDATE rounds SET is_active = 0, ended_at = NOW() WHERE round_id = $round_id");

            // also mark any stray active stages for this round as ended
            $conn->query("UPDATE stages SET is_active = 0, ended_at = NOW() WHERE round_id = $round_id AND is_active = 1");

            echo json_encode(['success' => true, 'message' => 'End of round reached, round closed', 'round_id' => $round_id]);
            break;
        }

        // Otherwise find and activate the next stage in THIS round
        $next_stage = getNextStageByRound($conn, $round_id, $current_stage_order);
        if ($next_stage) {
            $next_stage_id = (int)$next_stage['stage_id'];
            $next_stage_name = $next_stage['stage_name'];

            $conn->query("UPDATE stages SET is_active = 1, started_at = NOW() WHERE stage_id = $next_stage_id");
            
            // Matching players 
            $trigger_message = null;

            if ($next_stage_name === 'outcome_realization') {
                // Rank A players before matching
                $rank_message = rankEffortTaskResults($conn, $session_id, $round_id);

                // Trigger random matching
                
                $trigger_message = match_participants($conn, $session_id, $round_id);

            }

                    
            echo json_encode([
                    'success' => true,
                    'message' => 'Moved to next stage: ' . $next_stage_name . 
                                ($trigger_message ? ' — ' . $trigger_message : ''),
                    'current_stage' => $current_stage_name,
                    'next_stage' => $next_stage_name,
                    'round_id' => $round_id
                ]);

        } else {
            // This should not normally happen if stages were created consistently,
            // but as a fallback mark round closed:
            $conn->query("UPDATE rounds SET is_active = 0, ended_at = NOW() WHERE round_id = $round_id");
            echo json_encode(['success' => true, 'message' => 'No next stage found — round closed', 'round_id' => $round_id]);
        }
        break;




    // --- INVALID ACTION ---
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
    break;
}

// --- Close DB connection ---
$conn->close();
?>

