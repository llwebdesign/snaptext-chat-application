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
        getMessages($pdo);
        break;
    case 'send':
        sendMessage($pdo);
        break;
    case 'delete':
        deleteMessage($pdo);
        break;
    case 'edit':
        editMessage($pdo);
        break;
    case 'upload_image':
        uploadImage($pdo);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action']);
        exit;
}

function getMessages($pdo) {
    $userId = $_GET['user_id'] ?? 0;
    $currentUserId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.avatar 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = ? AND m.recipient_id = ?) 
               OR (m.sender_id = ? AND m.recipient_id = ?)
            ORDER BY m.created_at ASC
        ");
        
        $stmt->execute([$currentUserId, $userId, $userId, $currentUserId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($messages);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to fetch messages']);
    }
}

function sendMessage($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $recipientId = $data['recipient_id'] ?? 0;
    $content = $data['content'] ?? '';
    $type = $data['type'] ?? 'text';
    
    if (!$recipientId || !$content) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, recipient_id, text, type, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$_SESSION['user_id'], $recipientId, $content, $type]);
        $messageId = $pdo->lastInsertId();

        // Fetch the created message
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.avatar 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to send message']);
    }
}

function deleteMessage($pdo) {
    $messageId = $_POST['message_id'] ?? 0;
    
    try {
        // Verify message ownership
        $stmt = $pdo->prepare("
            SELECT sender_id FROM messages WHERE id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();

        if (!$message || $message['sender_id'] != $_SESSION['user_id']) {
            throw new Exception('Unauthorized');
        }

        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$messageId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function editMessage($pdo) {
    // Only premium users can edit messages
    if (!isPremiumUser($pdo, $_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Premium feature only']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = $data['message_id'] ?? 0;
    $newText = $data['text'] ?? '';

    try {
        // Verify message ownership
        $stmt = $pdo->prepare("
            SELECT sender_id FROM messages WHERE id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();

        if (!$message || $message['sender_id'] != $_SESSION['user_id']) {
            throw new Exception('Unauthorized');
        }

        $stmt = $pdo->prepare("
            UPDATE messages 
            SET text = ?, edited_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newText, $messageId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function uploadImage($pdo) {
    if (!isset($_FILES['image'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No image uploaded']);
        exit;
    }

    $file = $_FILES['image'];
    $recipientId = $_POST['recipient_id'] ?? 0;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'File upload failed']);
        exit;
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid file type']);
        exit;
    }

    try {
        $uploadDir = '../uploads/messages/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($file['name']);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, type, image, created_at) 
                VALUES (?, ?, 'image', ?, NOW())
            ");
            
            $imageUrl = 'uploads/messages/' . $fileName;
            $stmt->execute([$_SESSION['user_id'], $recipientId, $imageUrl]);
            
            $messageId = $pdo->lastInsertId();

            // Fetch the created message
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.avatar 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
        } else {
            throw new Exception('Failed to move uploaded file');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function isPremiumUser($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT is_premium FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user && $user['is_premium'];
}
