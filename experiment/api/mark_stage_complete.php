<?php
session_start();
header('Content-Type: application/json');
include '../shared/db_connect.php';

$participant_id = $_SESSION['participant_id'] ?? 0;
$session_id = $_SESSION['session_id'] ?? 0;

$input = json_decode(file_get_contents('php://input'), true);
$round_id = $input['round_id'] ?? 0;
$stage_name = $input['stage_name'] ?? '';

if (!$participant_id || !$round_id || !$stage_name) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Insert or update completion
$stmt = $conn->prepare("
    INSERT INTO participant_stage_status 
        (participant_id, session_id, round_id, stage_name, completed, completed_at)
    VALUES (?, ?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
");
$stmt->bind_param("iiis", $participant_id, $session_id, $round_id, $stage_name);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Stage marked complete']);
$conn->close();
