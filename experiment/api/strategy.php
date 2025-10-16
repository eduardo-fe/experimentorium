<?php
session_start();
header('Content-Type: application/json');
include '../shared/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$participant_id = $_SESSION['participant_id'] ?? 0;
$session_id = $_SESSION['session_id'] ?? 0;

if (!$participant_id) {
    echo json_encode(['success' => false, 'message' => 'No participant logged in']);
    exit;
}

// Get active round
$res = $conn->query("SELECT round_id FROM rounds WHERE session_id = $session_id AND is_active = 1 LIMIT 1");
$round = $res && $res->num_rows ? $res->fetch_assoc() : null;
if (!$round) {
    echo json_encode(['success' => false, 'message' => 'No active round']);
    exit;
}
$round_id = (int)$round['round_id'];

// Delete existing decisions for this participant and round (overwrite)
$conn->query("DELETE FROM decisions WHERE participant_id = $participant_id AND round_id = $round_id");

// Prepare insert statement
$stmt = $conn->prepare("INSERT INTO decisions (participant_id, round_id, match_id, decision_type, decision_value) VALUES (?, ?, NULL, ?, ?)");

foreach ($input['decisions'] as $type => $value) {
    $stmt->bind_param("iiss", $participant_id, $round_id, $type, $value);
    $stmt->execute();
}

echo json_encode(['success' => true, 'message' => 'Decisions saved']);
$conn->close();
?>
