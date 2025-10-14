<?php
header('Content-Type: application/json');
include '../shared/db_connect.php';

// --- 1️ Helper functions ---

function getOngoingSession($conn) {
    $result = $conn->query("SELECT * FROM sessions WHERE session_status = 'ongoing' LIMIT 1");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
}


function getLiveRound($conn, $session_id) {
    $query = "SELECT * FROM rounds WHERE session_id = $session_id AND is_active = 1 LIMIT 1";
    $result = $conn->query($query);
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
}

function getActiveStage($conn, $round_id) {
    $query = "SELECT * FROM stages WHERE round_id = $round_id AND is_active = 1 LIMIT 1";
    $result = $conn->query($query);
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
}

// --- 2️ Gather current status ---

$status = [
    'session' => null,
    'round' => null,
    'stage' => null
];

// Get current ongoing session
$session = getOngoingSession($conn);
if ($session) {
    $status['session'] = [
        'session_id' => $session['session_id'],
        'session_name' => $session['session_name'],
        'status' => $session['session_status']
    ];

    // Get current live round (if any)
    $round = getLiveRound($conn, $session['session_id']);
    if ($round) {
        $status['round'] = [
            'round_id' => $round['round_id'],
            'round_number' => $round['round_number'],
            'is_active' => $round['is_active'],
            'started_at' => $round['created_at']
        ];

        // Get current active stage (if any)
        $stage = getActiveStage($conn, $round['round_id']);
        if ($stage) {
            $status['stage'] = [
                'stage_id' => $stage['stage_id'],
                'stage_name' => $stage['stage_name'],
                'started_at' => $stage['started_at']
            ];
        }
    }
}

// --- 3️ Return everything as JSON ---
echo json_encode([
    'success' => true,
    'data' => $status
]);

$conn->close();

// Output Description:
// This script generates a JSON response containing the current status of an ongoing session, including details about the active session, round, and stage (if any). 
// The response has two main keys:
// - 'success': A boolean indicating the request was successful (always true in this case).
// - 'data': An object with three properties:
//   - 'session': Contains the session_id, session_name, and status of the ongoing session, or null if no session is ongoing.
//   - 'round': Contains the round_id, round_number, is_active status, and started_at timestamp of the active round, or null if no round is active.
//   - 'stage': Contains the stage_id, stage_name, and started_at timestamp of the active stage, or null if no stage is active.
//
// Example $status object structure:
// $status = [
//     'session' => [
//         'session_id' => 1,
//         'session_name' => 'Annual Conference 2025',
//         'status' => 'ongoing'
//     ],
//     'round' => [
//         'round_id' => 5,
//         'round_number' => 2,
//         'is_active' => 1,
//         'started_at' => '2025-10-13 09:00:00'
//     ],
//     'stage' => [
//         'stage_id' => 3,
//         'stage_name' => 'Q&A Session',
//         'started_at' => '2025-10-13 09:15:00'
//     ]
// ];
// If no ongoing session, round, or stage exists, the corresponding key will be null, e.g., 'session' => null.

?>
