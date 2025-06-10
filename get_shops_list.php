<?php
// get_shops_list.php
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');
$shops = [];

$sql = "SELECT id, name FROM shops ORDER BY name ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $shops[] = $row;
    }
} else {
    // Log error but return empty array to client
    error_log("Error fetching shops: " . $conn->error);
}

$conn->close();
echo json_encode($shops);
?>