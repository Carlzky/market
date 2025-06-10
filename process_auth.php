<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "cvsumarketplace_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
    exit();
}

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputIdentifier = htmlspecialchars(trim($_POST['username'] ?? '')); // This can be username or email
    $inputPassword = trim($_POST['password'] ?? '');
    $mode = $_POST['mode'] ?? '';

    // Determine if the input identifier is an email address
    $is_email = filter_var($inputIdentifier, FILTER_VALIDATE_EMAIL);

    if (!in_array($mode, ['login', 'register'])) {
        $response['message'] = 'Invalid authentication mode provided.';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // --- Registration Logic ---
    if ($mode === 'register') {
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        // Removed the 'initialName' variable and its assignment
        // $initialName = htmlspecialchars(trim($_POST['full_name'] ?? '')); 

        // Modified the validation condition: removed 'empty($initialName)'
        if (empty($inputIdentifier) || empty($inputPassword) || empty($confirmPassword)) {
            $response['message'] = 'All required fields (username/email, password, confirm password) are needed for registration.';
        } elseif (strlen($inputPassword) < 6) {
            $response['message'] = 'Password must be at least 6 characters long.';
        } elseif ($inputPassword !== $confirmPassword) {
            $response['message'] = 'Passwords do not match.';
        } else {
            // Check for uniqueness based on whether it's an email or username
            $field_to_check = $is_email ? 'email' : 'username';
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE " . $field_to_check . " = ?");

            if ($stmt_check === false) {
                error_log('Prepare failed (check uniqueness): ' . htmlspecialchars($conn->error));
                $response['message'] = 'Database error during registration check.';
            } else {
                $stmt_check->bind_param("s", $inputIdentifier);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $response['message'] = $is_email ? 'Email is already registered.' : 'Username is already registered.';
                } else {
                    $hashed_password = password_hash($inputPassword, PASSWORD_DEFAULT);

                    // Prepare statement for insertion - Removed 'name' column
                    if ($is_email) {
                        $stmt_insert = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                    } else {
                        $stmt_insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    }
                    
                    if ($stmt_insert === false) {
                        error_log('Prepare failed (insert user): ' . htmlspecialchars($conn->error));
                        $response['message'] = 'Database error during registration.';
                    } else {
                        // Bind parameters - Removed '$initialName'
                        $stmt_insert->bind_param("ss", $inputIdentifier, $hashed_password);

                        if ($stmt_insert->execute()) {
                            $response['success'] = true;
                            $response['message'] = 'Registered successfully! You can now log in.';
                        } else {
                            error_log('Registration failed: ' . htmlspecialchars($stmt_insert->error));
                            $response['message'] = 'Registration failed. Please try again.';
                        }
                        $stmt_insert->close();
                    }
                }
                $stmt_check->close();
            }
        }
    }
    // --- Login Logic ---
    elseif ($mode === 'login') {
        if (empty($inputIdentifier) || empty($inputPassword)) {
            $response['message'] = 'Username/Email and password are required.';
        } else {
            $user = null;

            // Attempt to find user by email first, then by username
            if ($is_email) {
                $stmt_login = $conn->prepare("SELECT id, username, email, name, profile_picture, password FROM users WHERE email = ?");
            } else {
                $stmt_login = $conn->prepare("SELECT id, username, email, name, profile_picture, password FROM users WHERE username = ?");
            }
            
            if ($stmt_login === false) {
                error_log('Prepare failed (login): ' . htmlspecialchars($conn->error));
                $response['message'] = 'Database error during login.';
            } else {
                $stmt_login->bind_param("s", $inputIdentifier);
                $stmt_login->execute();
                $result = $stmt_login->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($inputPassword, $user['password'])) {
                        $_SESSION['user_id'] = $user['id'];
                        // Set username in session for display purposes (prioritize actual username if available)
                        $_SESSION['username'] = $user['username'] ?? $user['email']; 
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['profile_picture'] = $user['profile_picture'] ?: 'profile.png'; // Default if null

                        $response['success'] = true;
                        $response['message'] = 'Login successful! Redirecting to Homepage.';
                        $response['redirect'] = 'loadingpage.html';
                    } else {
                        $response['message'] = 'Invalid username/email or password.';
                    }
                } else {
                    $response['message'] = 'Invalid username/email or password.';
                }
                $stmt_login->close();
            }
        }
    }
} else {
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

echo json_encode($response);
$conn->close();
exit();
?>