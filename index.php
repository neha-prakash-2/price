<?php
session_start();

// =======================================
// SESSION CHECK
// =======================================
// If the user is already logged in, redirect them immediately.
if (isset($_SESSION['user_id'])) {
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
    <title>Welcome to PriceComp</title>
    
    <!-- Fonts and Main Stylesheet -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* Inline styles for specific welcome page elements */
        .welcome-actions {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        /* Responsive buttons */
        @media (min-width: 480px) {
            .welcome-actions {
                flex-direction: row;
                justify-content: center;
            }
            .welcome-actions .btn {
                width: auto;
                min-width: 120px;
            }
        }

        /* Outline button style for Register */
        .btn-secondary-outline {
            background-color: transparent;
            color: #4f46e5; /* Matches your theme color */
            border: 2px solid #4f46e5;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            padding: 0.9rem 1rem; /* Match .btn padding roughly */
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary-outline:hover {
            background-color: rgba(79, 70, 229, 0.1);
            background-color: #4f46e5;
            color: white;
        }
        
        /* Ensure links behave like blocks/buttons */
        a.btn {
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-box">
            <h1 class="title">Price<span class="highlight">Comp</span></h1>
            <p class="subtitle">Your ultimate price comparison tool.</p>
            
            <div class="welcome-actions">
                <a href="login.php" class="btn">Login</a>
                <a href="register.php" class="btn-secondary-outline">Register</a>
            </div>
        </div>
    </div>

</body>
</html>