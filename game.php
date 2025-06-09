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

// Initialize variables for display
$username_display = "Guest";
$name_display = "";
$profile_image_display = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50"; // Default placeholder if no image or path is found

// Fetch user data from the database
if ($stmt = $conn->prepare("SELECT username, name, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    // If a profile picture exists in the DB, use it
    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }
    // Update session with the latest DB values, especially useful after login
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data in game.php: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Anime Reflex Challenge!</title>
    <style>
        /* General Layout Styles (from your provided code) */
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #FEFAE0; }
        nav { background-color: #B5C99A; padding: 10px 50px; display: flex; align-items: center; gap: 20px; }
        .logo { font-size: 24px; color: #6DA71D; }
        .logo a { text-decoration: none; color: #6DA71D; }
        .logo a:hover { filter: brightness(1.2); }
        .search-container { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .searchbar input { width: 350px; padding: 10px 14px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); border: none; border-radius: 4px; }
        .searchbutton { padding: 10px 16px; background-color: #38B000; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .searchbutton:hover { filter: brightness(1.15); }
        .cart { width: 40px; height: 40px; margin-left: 15px; }
        .cart img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; }
        .cart img:hover { filter: brightness(1.15); }
        .section { display: flex; flex-wrap: wrap; min-height: auto; padding: 20px; gap: 20px; }
        .leftside { padding: 15px; }
        .sidebar { width: 250px; padding: 10px 35px 10px 10px; border-right: 1px solid #ccc; min-height: auto; }
        .sidebar a { text-decoration: none; color: black; }
        .profile-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .profile-pic {
            width: 65px;
            height: 65px;
            background-color: #ccc;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            /* PHP will dynamically set this background-image */
            background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $profile_image_display); ?>');
        }
        .username { font-size: 16px; margin: 0; }
        .editprof { font-size: 13px; }
        .editprof a { text-decoration: none; color: gray; }
        .editprof a:hover { color: #38B000; }
        .options p { display: flex; align-items: center; gap: 10px; margin: 30px 0 9px; font-weight: bold; }
        .options ul { list-style: none; padding-left: 20px; margin-top: 0; }
        .options ul li { margin: 8px 0; cursor: pointer; padding-left: 20px; }
        .options p a:hover,
        .options ul li a:hover {
            color: #38B000;
        }
        .options ul li.active {
            color: #38B000;
            font-weight: bold;
        }
        .options ul li.active a {
            color: #38B000;
            font-weight: bold;
        }
        .options ul li.active a:hover {
            color: #38B000;
        }
        .options img { width: 30px; height: 30px; }
        .content-wrapper { flex: 1; display: flex; flex-direction: column; }
        .header { margin-bottom: 30px; }
        .header hr { margin-left: 0; margin-right: 0; }
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
        }

        /* Game Specific Styles */
        .game-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
            width: 100%;
            max-width: 600px; /* Limit game width */
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .game-stats {
            display: flex;
            justify-content: space-around;
            width: 100%;
            margin-bottom: 20px;
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }

        .game-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            width: 100%;
            max-width: 450px; /* Size of the game board */
            margin: 20px auto;
            background-color: #f7f9f7; /* Lighter background for the board */
            padding: 20px;
            border-radius: 12px;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .hole {
            width: 130px; /* Slightly larger holes */
            height: 130px;
            background-color: #E6EFC4; /* Lighter green */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: flex-end; /* Character pops up from bottom */
            overflow: hidden;
            position: relative;
            cursor: pointer;
            border: 4px solid #B5C99A; /* Border matching nav color */
            box-shadow: inset 0 0 10px rgba(0,0,0,0.2);
            transition: background-color 0.2s ease-in-out;
        }

        .hole:active {
            background-color: #ccd5ae; /* Darker on click */
        }

        .character {
            width: 90%;
            height: 90%;
            background-color: #F8F3DC; /* Light, almost white for the sparkle background */
            border-radius: 50%;
            position: absolute;
            bottom: -120%; /* Start hidden below the hole */
            transition: bottom 0.2s ease-out, transform 0.1s ease-out; /* Added transform for pop effect */
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 70px; /* Larger emoji size */
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4)); /* More prominent shadow */
            cursor: pointer;
            border: 2px solid #FFD700; /* Gold border for sparkle */
        }

        .character.up {
            bottom: 5%; /* Visible position */
            transform: scale(1.05); /* Slightly bigger when up */
        }

        .character.whacked {
            /* Animation for when the character is whacked */
            animation: popOut 0.2s ease-out forwards;
        }

        @keyframes popOut {
            0% { transform: scale(1.05); opacity: 1; bottom: 5%; }
            100% { transform: scale(0.5); opacity: 0; bottom: -120%; }
        }


        .game-controls {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .game-btn {
            background-color: #38B000;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px; /* Slightly more rounded buttons */
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease, transform 0.1s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            text-transform: uppercase; /* Uppercase text */
            font-weight: bold;
        }

        .game-btn:hover {
            background-color: #2e8b00;
            transform: translateY(-2px); /* Slight lift on hover */
        }

        .game-btn:active {
            transform: translateY(0); /* Press effect */
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .message-box {
            margin-top: 20px;
            padding: 15px;
            background-color: #E0F7FA; /* Light blue for messages */
            border: 1px solid #B2EBF2;
            border-radius: 8px;
            color: #00796B; /* Darker teal text */
            font-weight: bold;
            display: none; /* Hidden by default */
            text-align: center;
            font-size: 1.1em;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
    </style>
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
                    <div class="profile-pic" style="background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $profile_image_display); ?>');"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($_SESSION['name'] ?? $name_display); ?></strong>
                        <p>@<?php echo htmlspecialchars($_SESSION['username'] ?? $username_display); ?></p>
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
                        <li><a href="notification_settings.php">Notification Settings</a></li>
                    </ul>
                    <p><img src="Pics/purchase.png" /><a href="#">My Purchase</a></p>
                    <p><img src="Pics/notif.png" /><a href="#">Notifications</a></p>
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
                        <!-- Game holes and characters will be dynamically generated here -->
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