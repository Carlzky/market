<?php
session_start();
echo "Session ID: " . session_id() . "<br>";

if (!isset($_SESSION['test_value'])) {
    $_SESSION['test_value'] = time(); // Set a value if not already set
    echo "Session value set for the first time: " . $_SESSION['test_value'] . "<br>";
} else {
    echo "Session value retrieved: " . $_SESSION['test_value'] . "<br>";
}

echo "<a href='test_session.php'>Refresh this page</a><br>";
echo "<a href='Homepage.php'>Go to Homepage.php</a>";
?>