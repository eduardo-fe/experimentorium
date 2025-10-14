<?php 
// ----------------- Helpers -----------------

function getOngoingSession($conn) {
    $res = $conn->query("SELECT * FROM sessions WHERE session_status = 'ongoing' LIMIT 1");
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

function getLiveRound($conn, $session_id) {
    $session_id = (int)$session_id;
    $res = $conn->query("SELECT * FROM rounds WHERE session_id = $session_id AND is_active = 1 LIMIT 1");
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

// get the active stage for a specific round (IMPORTANT: uses round_id)
function getActiveStageByRound($conn, $round_id) {
    $round_id = (int)$round_id;
    $res = $conn->query("SELECT * FROM stages WHERE round_id = $round_id AND is_active = 1 LIMIT 1");
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

// get the next stage in a round (ordered by stage_order)
function getNextStageByRound($conn, $round_id, $current_order) {
    $round_id = (int)$round_id;
    $current_order = (int)$current_order;
    $q = "SELECT * FROM stages WHERE round_id = $round_id AND stage_order > $current_order ORDER BY stage_order ASC LIMIT 1";
    $res = $conn->query($q);
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

// --- Generate effort task penalties for all type A participants ---
function generateEffortTaskPenalties($conn, $round_id, $session_id) {
    // Fetch all type A participants for this session
    $res = $conn->query("SELECT participant_id FROM participants WHERE session_id = $session_id AND role = 'A'");
    if (!$res) return false;

    $participants = [];
    while ($row = $res->fetch_assoc()) {
        $participants[] = $row['participant_id'];
    }

    $n = count($participants);
    if ($n === 0) return false;

    // Shuffle for randomness in assignment order
    shuffle($participants);

    // Compute split sizes
    $half = floor($n / 2);
    $remainder = $n % 2;

    // Randomly decide which group gets the extra participant (if any)
    $extra_to_high = ($remainder === 1) ? (rand(0, 1) === 1) : false;

    $high_count = $half + ($extra_to_high ? 1 : 0);
    $low_count  = $n - $high_count;

    // Split participants
    $high_group = array_slice($participants, 0, $high_count);
    $low_group  = array_slice($participants, $high_count);

    // Prepare insert statement
    $stmt = $conn->prepare("
        INSERT INTO effort_tasks 
            (participant_id, round_id, penalty_probability, penalty_amount) 
        VALUES (?, ?, ?, ?)
    ");

    // Assign penalties to the 'high' group
    foreach ($high_group as $pid) {
        $penalty_probability = 'high';
        $penalty_amount = (mt_rand() / mt_getrandmax() < 0.7) ? 8 : 2; // 70% chance of 8
        $stmt->bind_param("iisi", $pid, $round_id, $penalty_probability, $penalty_amount);
        $stmt->execute();
    }

    // Assign penalties to the 'low' group
    foreach ($low_group as $pid) {
        $penalty_probability = 'low';
        $penalty_amount = (mt_rand() / mt_getrandmax() < 0.2) ? 8 : 2; // 20% chance of 8
        $stmt->bind_param("iisi", $pid, $round_id, $penalty_probability, $penalty_amount);
        $stmt->execute();
    }

    return true;
}



?>