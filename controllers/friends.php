<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'list':
        getFriendsList($pdo);
        break;
    case 'send_request':
        sendFriendRequest($pdo);
        break;
    case 'accept_request':
        acceptFriendRequest($pdo);
        break;
    case 'reject_request':
        rejectFriendRequest($pdo);
        break;
    case 'pending_requests':
        getPendingRequests($pdo);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action']);
        exit;
}

function getFriendsList($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.avatar, u.is_online
            FROM friendships f
            JOIN users u ON (f.user1_id = u.id OR f.user2_id = u.id)
            WHERE (f.user1_id = ? OR f.user2_id = ?)
            AND f.status = 'accepted'
            AND u.id != ?
        ");
        
        $userId = $_SESSION['user_id'];
        $stmt->execute([$userId, $userId, $userId]);
        $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($friends);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to fetch friends list']);
    }
}

function sendFriendRequest($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $recipientEmail = $data['email'] ?? '';
    
    if (!$recipientEmail) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Email is required']);
        exit;
    }

    try {
        // Check if recipient exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$recipientEmail]);
        $recipient = $stmt->fetch();

        if (!$recipient) {
            throw new Exception('User not found');
        }

        $recipientId = $recipient['id'];
        $senderId = $_SESSION['user_id'];

        // Check if they're already friends or if there's a pending request
        $stmt = $pdo->prepare("
            SELECT * FROM friendships 
            WHERE (user1_id = ? AND user2_id = ?) 
               OR (user1_id = ? AND user2_id = ?)
        ");
        $stmt->execute([$senderId, $recipientId, $recipientId, $senderId]);
        
        if ($stmt->fetch()) {
            throw new Exception('Friend request already exists or users are already friends');
        }

        // Create friend request
        $stmt = $pdo->prepare("
            INSERT INTO friendships (user1_id, user2_id, status, created_at) 
            VALUES (?, ?, 'pending', NOW())
        ");
        $stmt->execute([$senderId, $recipientId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function acceptFriendRequest($pdo) {
    $requestId = $_POST['request_id'] ?? 0;
    
    try {
        // Verify request exists and user is the recipient
        $stmt = $pdo->prepare("
            SELECT * FROM friendships 
            WHERE id = ? AND user2_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requestId, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Invalid friend request');
        }

        // Accept request
        $stmt = $pdo->prepare("
            UPDATE friendships 
            SET status = 'accepted', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$requestId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function rejectFriendRequest($pdo) {
    $requestId = $_POST['request_id'] ?? 0;
    
    try {
        // Verify request exists and user is the recipient
        $stmt = $pdo->prepare("
            SELECT * FROM friendships 
            WHERE id = ? AND user2_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requestId, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Invalid friend request');
        }

        // Delete request
        $stmt = $pdo->prepare("DELETE FROM friendships WHERE id = ?");
        $stmt->execute([$requestId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getPendingRequests($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT f.id as request_id, u.id, u.username, u.avatar
            FROM friendships f
            JOIN users u ON f.user1_id = u.id
            WHERE f.user2_id = ? AND f.status = 'pending'
        ");
        
        $stmt->execute([$_SESSION['user_id']]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($requests);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to fetch pending requests']);
    }
}
