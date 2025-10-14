<?php
header('Content-Type: application/json');
include '../shared/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$participant_id = (int)($input['participant_id'] ?? 0);

if (!$participant_id) {
  echo json_encode(['success' => false, 'message' => 'Missing participant ID']);
  exit;
}

$res = $conn->query("SELECT round_id FROM rounds WHERE is_active = 1 ORDER BY round_id DESC LIMIT 1");
$round = $res && $res->num_rows ? $res->fetch_assoc() : null;

if (!$round) {
  echo json_encode(['success' => false, 'message' => 'No active round']);
  exit;
}

$round_id = (int)$round['round_id'];
$q = "SELECT penalty_probability FROM effort_tasks WHERE participant_id = $participant_id AND round_id = $round_id LIMIT 1";
$res2 = $conn->query($q);

if ($res2 && $res2->num_rows > 0) {
  $row = $res2->fetch_assoc();
  echo json_encode(['success' => true, 'penalty_probability' => $row['penalty_probability']]);
} else {
  echo json_encode(['success' => false, 'message' => 'No penalty found']);
}

$conn->close();
?>
