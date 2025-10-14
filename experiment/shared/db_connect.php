<?php
// Barebones connection
$servername = "localhost";
$username = "root";    // replace with your MySQL username
$password = "root";        // replace with your MySQL password
$dbname = "experiment";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else{
    
}
?>
