<?php
session_start();
header('Content-Type: application/json');
include '../shared/db_connect.php';

$participant_id = $_SESSION['participant_id'] ?? 0;

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

function getCompletedStages($conn, $participant_id, $round_id) {
    $completed = [];
    if (!$participant_id || !$round_id) return $completed;

    $stmt = $conn->prepare("SELECT stage_name FROM participant_stage_status WHERE participant_id = ? AND round_id = ? AND completed =1");
    $stmt->bind_param("ii", $participant_id, $round_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $completed[] = $row['stage_name'];
    }
    return $completed;
}

// --- 2️ Gather current status ---

$status = [
    'session' => null,
    'round' => null,
    'stage' => null,
    'completed_stages' => [] // safe default
];

// Get current ongoing session
$session = getOngoingSession($conn);
if ($session) {
    $status['session'] = [
        'session_id' => $session['session_id'],
        'session_name' => $session['session_name'],
        'status' => $session['session_status'],
        'youare' => $participant_id
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

        // Only include participant's completed stages if logged in
        if ($participant_id) {
            $status['completed_stages'] = getCompletedStages($conn, $participant_id, $round['round_id']);
        }
    }
}

// --- 3️ Return JSON ---
echo json_encode([
    'success' => true,
    'data' => $status
]);

$conn->close();
?>
