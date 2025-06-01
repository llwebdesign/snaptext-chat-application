<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            handleLogin($pdo);
            break;
        case 'register':
            handleRegister($pdo);
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            header('Location: ../index.php');
            exit;
    }
}

function handleLogin($pdo) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id, password, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: ../index.php');
        } else {
            header('Location: ../index.php?error=invalid_credentials');
        }
    } catch (PDOException $e) {
        header('Location: ../index.php?error=db_error');
    }
    exit;
}

function handleRegister($pdo) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header('Location: ../register.php?error=email_exists');
            exit;
        }

        // Handle avatar upload
        $avatarPath = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '.' . $fileExtension;
            $avatarPath = 'uploads/avatars/' . $newFileName;
            
            move_uploaded_file($_FILES['avatar']['tmp_name'], '../' . $avatarPath);
        }

        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $email, $hashedPassword, $avatarPath]);
        
        // Auto-login after registration
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['username'] = $username;
        
        header('Location: ../index.php');
    } catch (PDOException $e) {
        header('Location: ../register.php?error=registration_failed');
    }
    exit;
}

function handleLogout() {
    session_destroy();
    header('Location: ../index.php');
    exit;
}
