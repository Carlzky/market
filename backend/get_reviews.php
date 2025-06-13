<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

$reviews = [];
$sql = "";
$stmt = null;

try {
    if (isset($_GET['shop_id']) && !empty($_GET['shop_id'])) {
        $shop_id = (int)$_GET['shop_id'];
        $sql = "SELECT sr.id, sr.user_id, sr.rating, sr.comment, u.name AS user_name, u.profile_picture, s.name AS shop_name
                FROM shop_reviews sr
                JOIN users u ON sr.user_id = u.id
                JOIN shops s ON sr.shop_id = s.id
                WHERE sr.shop_id = ?
                ORDER BY sr.created_at DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $shop_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['profile_picture'] = $row['profile_picture'] ? htmlspecialchars($row['profile_picture']) : 'Pics/profile.png';
                $reviews[] = $row;
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for specific shop reviews: " . $conn->error);
        }
    } else {
        $sql = "SELECT sr.id, sr.user_id, sr.rating, sr.comment, u.name AS user_name, u.profile_picture, s.name AS shop_name
                FROM shop_reviews sr
                JOIN users u ON sr.user_id = u.id
                JOIN shops s ON sr.shop_id = s.id
                ORDER BY sr.created_at DESC
                LIMIT 6";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['profile_picture'] = $row['profile_picture'] ? htmlspecialchars($row['profile_picture']) : 'Pics/profile.png';
                $reviews[] = $row;
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for general reviews: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch reviews.']);
    exit();
}

echo json_encode($reviews);
?>