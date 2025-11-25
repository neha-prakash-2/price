<?php
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // If logged in, redirect them to their panel, not the register page
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        header("Location: admin_panel.php");
    } else {
        header("Location: user_panel.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PriceComp</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Base Styles (from style.css) --- */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-image: url('https://images.unsplash.com/photo-1553095066-5014bc7b7f2d?q=80&w=2787&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #333;
        }

        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .auth-box {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 2.5rem 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .register-box {
            max-width: 500px;
        }

        .title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .title .highlight {
            color: #4f46e5;
        }

        .subtitle {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 2rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .input-group input[type="email"],
        .input-group input[type="password"],
        .input-group input[type="text"] {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box; /* Important for padding to work correctly */
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            background-color: #4f46e5;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #4338ca;
        }

        .links {
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .links a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 0.8rem;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .btn-secondary {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            background-color: #e0e0e0;
            color: #333;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .gender-options {
            display: flex;
            justify-content: flex-start;
            gap: 1.5rem;
            align-items: center;
        }

        .gender-options input[type="radio"] {
            margin-right: 0.3rem;
        }

        /* --- Avatar Preview on Form --- */
        .avatar-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        #avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ddd;
        }

        /* --- Modal Styles (This creates the pop-up) --- */
        .modal-overlay {
            display: none; /* This is the key part that hides it initially */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            text-align: center;
        }

        .modal-close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: #000;
        }

        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        /* --- Avatar Selection inside the pop-up --- */
        .avatar-selection-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .avatar-selection-container label {
            display: block;
            cursor: pointer;
        }

        .avatar-selection-container img {
            width: 100%;
            max-width: 80px; /* Added for consistency */
            height: auto;
            border-radius: 50%;
            border: 3px solid transparent;
            transition: border-color 0.3s;
        }

        .avatar-selection-container input[type="radio"] {
            display: none; /* Hide the actual radio button */
        }

        .avatar-selection-container input[type="radio"]:checked + img {
            border-color: #4f46e5;
            box-shadow: 0 0 10px rgba(79, 70, 229, 0.5);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box register-box">
            <h1 class="title">Create Account</h1>
            <h2 class="subtitle">Join PriceComp Today!</h2>

            <?php
                if (isset($_GET['error'])) {
                    echo '<p class="error-message">' . htmlspecialchars($_GET['error']) . '</p>';
                }
            ?>

            <form action="register_action.php" method="POST">
                <!-- Avatar Preview + Select -->
                <div class="avatar-preview-container">
                    <img src="https://placehold.co/100x100/EFEFEF/AAAAAA?text=Avatar" 
                         id="avatar-preview" alt="Selected Avatar"
                         onerror="this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=Avatar'">
                    <!-- Set a default value in case user doesn't choose one -->
                    <input type="hidden" name="profile_photo" id="profile_photo_input" value="avatar1.gif" required> 
                    <br>
                    <button type="button" class="btn-secondary" onclick="openModal()">Choose Avatar</button>
                </div>

                <div class="input-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="input-group">
                    <label>Gender</label>
                    <div class="gender-options">
                        <input type="radio" id="male" name="gender" value="Male" required>
                        <label for="male">Male</label>
                        <input type="radio" id="female" name="gender" value="Female">
                        <label for="female">Female</label>
                    </div>
                </div>
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
            <div class="links">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>

    <!-- Avatar Selection Modal -->
    <div id="avatar-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h3>Choose Your Avatar</h3>
            <div class="avatar-selection-container">
                <label>
                    <input type="radio" name="avatar_choice" value="avatar1.gif" onchange="selectAvatar('avatar1.gif')">
                    <!-- Add onerror fallback images -->
                    <img src="avatars/avatar1.gif" alt="Avatar 1" onerror="this.src='https://placehold.co/80x80?text=1'">
                </label>
                <label>
                    <input type="radio" name="avatar_choice" value="avatar2.gif" onchange="selectAvatar('avatar2.gif')">
                    <img src="avatars/avatar2.gif" alt="Avatar 2" onerror="this.src='https://placehold.co/80x80?text=2'">
                </label>
                <label>
                    <input type="radio" name="avatar_choice" value="avatar3.gif" onchange="selectAvatar('avatar3.gif')">
                    <img src="avatars/avatar3.gif" alt="Avatar 3" onerror="this.src='https://placehold.co/80x80?text=3'">
                </label>
                <label>
                    <input type="radio" name="avatar_choice" value="avatar4.gif" onchange="selectAvatar('avatar4.gif')">
                    <img src="avatars/avatar4.gif" alt="Avatar 4" onerror="this.src='https://placehold.co/80x80?text=4'">
                </label>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('avatar-modal');
        const previewImg = document.getElementById('avatar-preview');
        const photoInput = document.getElementById('profile_photo_input');

        function openModal() {
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function selectAvatar(filename) {
            // Update preview + hidden input
            const newSrc = 'avatars/' + filename;
            previewImg.src = newSrc;
            // Add fallback for preview image as well
            previewImg.onerror = () => { 
                previewImg.src = 'https://placehold.co/100x100/EFEFEF/AAAAAA?text=Avatar'; 
            }; 
            photoInput.value = filename;
            closeModal();
        }

        // Close modal if user clicks outside
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
