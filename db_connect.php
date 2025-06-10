<?php
// db_connect.php
$servername = "localhost";
$username = "root";       // IMPORTANT: Replace with your actual database username
$password = "";           // IMPORTANT: Replace with your actual database password
$dbname = "cvsumarketplace_db"; // IMPORTANT: Replace with your actual database name

// Enable MySQLi error reporting for easier debugging (highly recommended in development)
// This will make MySQLi throw exceptions for errors instead of just returning false
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // For development: Show the specific error and stop execution
    die("Database connection failed: " . $conn->connect_error);
    // For production: Log the error and show a generic message:
    // error_log("Database connection failed: " . $conn->connect_error);
    // die("An error occurred. Please try again later.");
}

// Set the character set to UTF-8 (highly recommended)
if (!$conn->set_charset("utf8mb4")) {
    // Handle error if setting charset fails (though with MYSQLI_REPORT_STRICT, an exception might be thrown)
    error_log("Error loading character set utf8mb4: " . $conn->error);
    // If you want to be very strict, you could die here:
    // die("Failed to set character set for database connection.");
}

// You can remove the closing PHP tag if this is the only thing in the file
// This prevents accidental whitespace from being sent before headers
?>