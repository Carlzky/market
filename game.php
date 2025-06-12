<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php'; // Ensure this file exists and connects to your DB

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html"); // Ensure this redirects to your actual login page
    exit;
}
$user_id = $_SESSION['user_id'];

// --- Initialize variables with default values to prevent htmlspecialchars errors ---
// Default profile picture URL. Adjust path if necessary.
$default_profile_picture_url = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50";
$username_display = "Guest"; // Default username
$name_display = "Guest User"; // Default full name
$profile_image_display = $default_profile_picture_url; // Default for display

// Fetch user data from the database
if ($stmt = $conn->prepare("SELECT username, name, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    // Bind results to temporary variables to allow for null coalescing later
    $stmt->bind_result($fetched_username, $fetched_name, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    // Assign fetched values, using null coalescing operator to ensure they are strings
    $username_display = $fetched_username ?? "Guest";
    $name_display = $fetched_name ?? "Guest User";

    // If a profile picture exists in the DB, use it, otherwise use the hardcoded default
    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    } else {
        // If no picture in DB, use session if available, else use the hardcoded default
        $profile_image_display = $_SESSION['profile_picture'] ?? $default_profile_picture_url;
    }

    // Update session with the latest fetched/derived values
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data in game.php: " . $conn->error);
    // If DB fetch fails, ensure session vars are still set to defaults for display
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="CSS/game.css">
    <title>Anime Reflex Challenge!</title>

</head>

<body>
    <nav>
        <div class="logo"><a href="Homepage.php"><h1>Lo Go.</h1></a></div>
        <div class="search-container">
            <div class="searchbar">
                <input type="text" placeholder="Search..." />
                <button class="searchbutton">Search</button>
            </div>
            <div class="cart">
                <a href="Homepage.php">
                    <img src="Pics/cart.png" alt="Cart" />
                </a>
            </div>
        </div>
    </nav>

    <div class="section">
        <div class="leftside">
            <div class="sidebar">
                <div class="profile-header">
                    <div class="profile-pic" style="background-image: url('<?php echo htmlspecialchars($profile_image_display); ?>');"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($name_display); ?></strong>
                        <p>@<?php echo htmlspecialchars($username_display); ?></p>
                        <div class="editprof">
                            <a href="Profile.php">✎ Edit Profile</a>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="options">
                    <p><img src="Pics/profile.png" class="dppic" /><a href="viewprofile.php"><strong>My Account</strong></a></p>
                    <ul>
                        <li><a href="viewprofile.php">Profile</a></li>
                        <li><a href="Wallet.php">Wallet</a></li>
                        <li><a href="Address.php">Addresses</a></li>
                        <li><a href="change_password.php">Change Password</a></li>
                    </ul>
                    <p><img src="Pics/purchase.png" /><a href="#">My Purchase</a></p>
                    <p><img src="Pics/notif.png" /><a href="notification_settings.php">Notifications</a></p>
                    <p><img src="Pics/gameicon.png" /><a href="game.php">Game</a></p>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="header">
                <h2>Catch the Sparkle!</h2>
                <p>Click the shimmering sparkle before it disappears to earn points!</p>
                <hr />
            </div>

            <div class="main">
                <div class="game-container">
                    <div class="game-stats">
                        <span>Score: <span id="score">0</span></span>
                        <span>Time: <span id="time">30</span>s</span>
                    </div>
                    <div class="game-board" id="gameBoard">
                        </div>
                    <div class="game-controls">
                        <button class="game-btn" id="startGameBtn">Start Game</button>
                        <button class="game-btn" id="resetGameBtn">Reset Game</button>
                    </div>
                    <div class="message-box" id="messageBox"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Game elements
        const gameBoard = document.getElementById('gameBoard');
        const scoreDisplay = document.getElementById('score');
        const timeDisplay = document.getElementById('time');
        const startGameBtn = document.getElementById('startGameBtn');
        const resetGameBtn = document.getElementById('resetGameBtn');
        const messageBox = document.getElementById('messageBox');

        // Game state variables
        let score = 0;
        let timeLeft = 30; // Game duration in seconds
        let timerIntervalId; // Stores the setInterval ID for the timer
        let popUpTimeoutId; // Stores the setTimeout ID for the next character pop-up
        let lastHole = null; // To prevent the same character from popping in the same hole twice in a row
        let gameActive = false; // Flag to indicate if the game is currently running

        // --- Game Setup Functions ---

        /**
         * Dynamically creates the specified number of holes for the game board.
         * Each hole contains a 'character' element that will pop up.
         * @param {number} numHoles - The total number of holes to create.
         */
        function createHoles(numHoles = 9) {
            gameBoard.innerHTML = ''; // Clear any existing holes
            for (let i = 0; i < numHoles; i++) {
                const hole = document.createElement('div');
                hole.classList.add('hole');
                const character = document.createElement('div');
                character.classList.add('character');
                character.textContent = '✨'; // The sparkle emoji for the character
                hole.appendChild(character);
                gameBoard.appendChild(hole);
            }
        }

        /**
         * Selects a random hole from the game board, ensuring it's different from the last hole used.
         * @returns {HTMLElement} The randomly selected hole element.
         */
        function getRandomHole() {
            const holes = document.querySelectorAll('.hole');
            let randomHole;
            // Loop until a new hole is selected, different from the last one
            do {
                const randomIndex = Math.floor(Math.random() * holes.length);
                randomHole = holes[randomIndex];
            } while (randomHole === lastHole); // Keep looping if it's the same hole as last time
            lastHole = randomHole; // Update the last used hole
            return randomHole;
        }

        /**
         * Makes a character (sparkle) pop up from a random hole.
         * Schedules itself to run again if the game is active.
         */
        function popUpCharacter() {
            if (!gameActive) {
                return; // Stop if game is not active
            }

            const hole = getRandomHole();
            const character = hole.querySelector('.character');

            // Reset animation state and make character visible
            character.classList.remove('whacked');
            character.classList.add('up');

            // Random time for the character to stay up (between 700ms and 1500ms)
            const randomTime = Math.random() * 800 + 700; // Adjusted for slightly faster pace

            // Schedule character to disappear and the next pop-up
            popUpTimeoutId = setTimeout(() => {
                character.classList.remove('up'); // Hide the character
                if (gameActive) {
                    popUpCharacter(); // Schedule the next character pop-up
                }
            }, randomTime);
        }

        /**
         * Handles a click event on the game board.
         * Increments score if a sparkle is hit.
         * @param {Event} event - The click event object.
         */
        function whack(event) {
            if (!gameActive) return; // Only process clicks if game is active

            // Check if the clicked element is the character itself
            if (event.target.classList.contains('character')) {
                score++; // Increment score
                scoreDisplay.textContent = score; // Update score display

                // Trigger the 'whacked' animation and immediately hide the sparkle
                event.target.classList.add('whacked');
                // Remove 'up' class after a very short delay to allow animation to start
                setTimeout(() => {
                    event.target.classList.remove('up');
                }, 100); // Small delay to let animation begin

                // Clear the timeout for this specific character if it was hit before disappearing
                clearTimeout(popUpTimeoutId);
                // Schedule the next character pop-up immediately to maintain flow
                if (gameActive) { // Only if game is still active
                    popUpCharacter();
                }
            }
        }

        // --- Game Control Functions ---

        /**
         * Starts the "Catch the Sparkle!" game.
         * Initializes score, timer, and starts character pop-ups.
         */
        function startGame() {
            score = 0;
            timeLeft = 30; // Reset time
            scoreDisplay.textContent = score;
            timeDisplay.textContent = timeLeft;
            messageBox.style.display = 'none'; // Hide any previous messages
            gameActive = true;

            // Add event listener for whacking characters (delegated to gameBoard)
            gameBoard.addEventListener('click', whack);

            // Start the timer countdown
            timerIntervalId = setInterval(() => {
                timeLeft--;
                timeDisplay.textContent = timeLeft;
                if (timeLeft <= 0) {
                    endGame(); // End game when time runs out
                }
            }, 1000); // Update every second

            // Start characters popping up after a short delay
            popUpTimeoutId = setTimeout(popUpCharacter, 500);

            // Disable Start button, enable Reset button
            startGameBtn.disabled = true;
            resetGameBtn.disabled = false;
        }

        /**
         * Ends the current game.
         * Stops timers, hides characters, and displays final score.
         */
        function endGame() {
            gameActive = false; // Set game to inactive
            clearInterval(timerIntervalId); // Stop the main game timer
            clearTimeout(popUpTimeoutId); // Stop any pending character pop-ups

            // Remove the click listener to prevent scoring after game ends
            gameBoard.removeEventListener('click', whack);

            // Hide all characters on the board
            document.querySelectorAll('.character').forEach(char => {
                char.classList.remove('up', 'whacked');
            });

            // Display game over message with final score
            messageBox.textContent = `Game Over! Your final score is ${score} sparkles caught!`;
            messageBox.style.display = 'block'; // Show game over message

            // Enable Start button, disable Reset button
            startGameBtn.disabled = false;
            resetGameBtn.disabled = true;
        }

        /**
         * Resets the game to its initial state without starting it.
         */
        function resetGame() {
            endGame(); // Ensure any active game is stopped first
            score = 0;
            timeLeft = 30;
            scoreDisplay.textContent = score;
            timeDisplay.textContent = timeLeft;
            messageBox.style.display = 'none';
            // Ensure start button is enabled and reset is disabled for a fresh start
            startGameBtn.disabled = false;
            resetGameBtn.disabled = true;
        }

        // --- Event Listeners ---

        startGameBtn.addEventListener('click', startGame);
        resetGameBtn.addEventListener('click', resetGame);

        // --- Initial Page Load Setup ---
        window.onload = function() {
            createHoles(); // Create the game board holes when the page loads
            resetGameBtn.disabled = true; // Reset button is disabled by default until game starts
        };
    </script>
</body>
</html>