<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json'); // Crucial: Respond with JSON

$suggestions = [];
$query = $_GET['query'] ?? '';

if (!empty($query)) {
    // Sanitize the query for LIKE clause
    $search_query = '%' . $query . '%';

    try {
        // Search for suggestions in both shop names and item names
        // Using UNION to combine results from two tables
        $stmt = $conn->prepare(
            "SELECT name FROM shops WHERE name LIKE ? " .
            "UNION " .
            "SELECT name FROM items WHERE name LIKE ? LIMIT 10" // Limit suggestions
        );
        if ($stmt === false) {
            error_log("get_suggestions.php DB prepare error: " . $conn->error);
            throw new Exception('Failed to prepare database statement for suggestions.');
        }

        $stmt->bind_param("ss", $search_query, $search_query); // Two 's' for two string parameters
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $suggestions[] = htmlspecialchars($row['name']); // Sanitize name before adding
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        error_log("Error in get_suggestions.php: " . $e->getMessage());
        echo json_encode([]); // Return empty array on error
        exit();
    }
}

echo json_encode($suggestions);
?>