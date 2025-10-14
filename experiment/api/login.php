<?php
session_start();
header('Content-Type: application/json');

// ---- DB connection ----
include '../shared/db_connect.php';

// ---- Get JSON input ----
$input = json_decode(file_get_contents("php://input"), true);
$participant_code = trim($input['participant_id'] ?? '');

if ($participant_code === '') {
    echo json_encode(['success' => false, 'message' => 'No participant code provided']);
    exit;
}

// ---- Get ongoing session ----
$session_result = $conn->query("SELECT * FROM sessions WHERE session_status = 'ongoing' LIMIT 1");
if (!$session_result || $session_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No ongoing session found']);
    exit;
}
$session = $session_result->fetch_assoc();
$session_id = $session['session_id'];

// ---- Determine role ----
$role = ((int)$participant_code % 2 === 0) ? 'A' : 'B';

// ---- Check if participant already exists ----
$query = "SELECT participant_id, role FROM participants WHERE participant_code = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $participant_code);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row) {
    // Participant already exists
    $participant_id = $row['participant_id'];
    $role = $row['role']; // use existing role
    $message = 'Participant already exists';

    // Update last_seen
    $stmt3 = mysqli_prepare($conn, "UPDATE participants SET last_seen = NOW() WHERE participant_id = ?");
    mysqli_stmt_bind_param($stmt3, "i", $participant_id);
    mysqli_stmt_execute($stmt3);

} else {
    // Insert new participant
    $stmt2 = mysqli_prepare($conn, "INSERT INTO participants (session_id, participant_code, role) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt2, "iss", $session_id, $participant_code, $role);
    mysqli_stmt_execute($stmt2);
    $participant_id = mysqli_insert_id($conn);
    $message = 'New participant registered';
}


// ---- Store in session ----
$_SESSION['participant_id'] = $participant_id;
$_SESSION['participant_code'] = $participant_code;
$_SESSION['role'] = $role;
$_SESSION['session_id'] = $session_id;
$_SESSION['stage'] = $_SESSION['stage'] ?? 'waiting';

// ---- Return JSON ----
echo json_encode([
    'success' => true,
    'participant_id' => $participant_id,
    'role' => $role,
    'session_id' => $session_id,
    'message' => $message
]);
