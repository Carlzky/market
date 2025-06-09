<?php
$servername = "localhost";
$username = "root"; // IMPORTANT: Replace with your actual database username
$password = "";     // IMPORTANT: Replace with your actual database password
$dbname = "cvsumarketplace_db"; // IMPORTANT: Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // For development: Show the specific error
    die("Database connection failed: " . $conn->connect_error);
    // For production: Log the error and show a generic message:
    // error_log("Database connection failed: " . $conn->connect_error);
    // die("An error occurred. Please try again later.");
}

// Set the character set to UTF-8 (highly recommended)
if (!$conn->set_charset("utf8mb4")) {
    // Handle error if setting charset fails
    error_log("Error loading character set utf8mb4: " . $conn->error);
    // You might choose to die here, or log and continue depending on strictness
}


?>