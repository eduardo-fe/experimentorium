<?php
session_start();

// Start output buffering to prevent accidental output
ob_start();

ini_set('display_errors', 0); // Disable displaying errors to the client
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json');

// Include DB connection (optional, since we're defining $pdo locally)
if (!file_exists('../shared/db_connect.php')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}
include '../shared/db_connect.php';

// Define PDO connection locally as a fallback
try {
    $host = 'localhost';
    $dbname = 'experiment'; // Your database name
    $username = 'root'; // MAMP default
    $password = 'root'; // MAMP default

    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean();
    error_log('Database connection failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to connect to database: ' . $e->getMessage()]);
    exit;
}

// Get input data
$data = json_decode(file_get_contents('php://input'), true);

$participant_id = $data['participant_id'] ?? null;
$session_id = $data['session_id'] ?? null;
$round_id = $data['round_id'] ?? null;
$task_type = $data['task_type'] ?? null;
$effort_score = $data['effort_score'] ?? null;

// Validate input
if (!$participant_id || !$session_id || !$round_id || !$task_type || $effort_score === null) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Update effort_tasks table
    $stmt = $pdo->prepare("
        UPDATE effort_tasks 
        SET effort_score = :effort_score, completed_at = NOW()
        WHERE participant_id = :participant_id AND round_id = :round_id
    ");

    $stmt->execute([
        ':participant_id' => $participant_id,
        ':round_id' => $round_id,
        ':effort_score' => $effort_score
    ]);

    // Check if any rows were updated
    if ($stmt->rowCount() === 0) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No matching record found for participant_id: ' . $participant_id . ' and round_id: ' . $round_id
        ]);
        exit;
    }

    // Optionally, mark participant_stage_status as completed (uncomment if needed)
    
    $stage_name = 'effort_task';
    $now = date('Y-m-d H:i:s');
    $stmt2 = $pdo->prepare("
        INSERT INTO participant_stage_status 
            (participant_id, session_id, round_id, stage_name, completed, completed_at)
        VALUES 
            (:participant_id, :session_id, :round_id, :stage_name, 1, :completed_at)
        ON DUPLICATE KEY UPDATE completed = 1, completed_at = :completed_at
    ");
    $stmt2->execute([
        ':participant_id' => $participant_id,
        ':session_id' => $session_id,
        ':round_id' => $round_id,
        ':stage_name' => $stage_name,
        ':completed_at' => $now
    ]);
    

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Effort task updated successfully']);
    exit;

} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
    exit;
}
?>