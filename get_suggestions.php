<?php
// get_suggestions.php
header('Content-Type: application/json'); // Respond with JSON

include 'db_connect.php'; // Include your database connection

$suggestions = [];
$query = $_GET['query'] ?? '';

if (!empty($query)) {
    // Use prepared statement to prevent SQL injection
    $search_param = "%" . $query . "%";
    $sql = "SELECT name FROM food_stores WHERE name LIKE ? LIMIT 10"; // Limit results for efficiency

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $search_param);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['name'];
            }
            $result->free();
        } else {
            error_log("Error executing suggestion query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Database prepare statement failed for suggestions: " . $conn->error);
    }
}

$conn->close();
echo json_encode($suggestions);
?>