<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SnapText - Modern Messaging App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-100 font-[Inter]">
    <div id="app" class="min-h-screen">
        <?php
        session_start();
        require_once 'config/database.php';
        
        if (isset($_SESSION['user_id'])) {
            include 'views/dashboard.php';
        } else {
            include 'views/login.php';
        }
        ?>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>
