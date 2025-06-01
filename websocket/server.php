<?php
require_once '../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class ChatServer implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data) return;

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'message':
                $this->handleMessage($from, $data);
                break;
            case 'call_offer':
                $this->handleCallOffer($from, $data);
                break;
            case 'call_answer':
                $this->handleCallAnswer($from, $data);
                break;
            case 'ice_candidate':
                $this->handleIceCandidate($from, $data);
                break;
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove user connection mapping
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                
                // Notify friends that user is offline
                $this->broadcastUserStatus($userId, false);
                break;
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function handleAuth($conn, $data) {
        $userId = $data['user_id'];
        $this->userConnections[$userId] = $conn;
        
        // Notify friends that user is online
        $this->broadcastUserStatus($userId, true);
    }

    protected function handleMessage($from, $data) {
        $recipientId = $data['recipient_id'];
        
        if (isset($this->userConnections[$recipientId])) {
            $this->userConnections[$recipientId]->send(json_encode([
                'type' => 'message',
                'message' => $data['message']
            ]));
        }
    }

    protected function handleCallOffer($from, $data) {
        $recipientId = $data['recipient_id'];
        
        if (isset($this->userConnections[$recipientId])) {
            $this->userConnections[$recipientId]->send(json_encode([
                'type' => 'call_offer',
                'signal' => $data['signal'],
                'sender_id' => $data['sender_id'],
                'sender_name' => $data['sender_name'],
                'withVideo' => $data['withVideo'] ?? false
            ]));
        }
    }

    protected function handleCallAnswer($from, $data) {
        $recipientId = $data['recipient_id'];
        
        if (isset($this->userConnections[$recipientId])) {
            $this->userConnections[$recipientId]->send(json_encode([
                'type' => 'call_answer',
                'signal' => $data['signal']
            ]));
        }
    }

    protected function handleIceCandidate($from, $data) {
        $recipientId = $data['recipient_id'];
        
        if (isset($this->userConnections[$recipientId])) {
            $this->userConnections[$recipientId]->send(json_encode([
                'type' => 'ice_candidate',
                'candidate' => $data['candidate']
            ]));
        }
    }

    protected function broadcastUserStatus($userId, $isOnline) {
        // Get user's friends from database
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=snaptext_db", "root", "");
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    CASE 
                        WHEN f.user1_id = ? THEN f.user2_id
                        ELSE f.user1_id
                    END as friend_id
                FROM friendships f
                WHERE (f.user1_id = ? OR f.user2_id = ?)
                AND f.status = 'accepted'
            ");
            $stmt->execute([$userId, $userId, $userId]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $friendId = $row['friend_id'];
                if (isset($this->userConnections[$friendId])) {
                    $this->userConnections[$friendId]->send(json_encode([
                        'type' => 'user_status',
                        'user_id' => $userId,
                        'is_online' => $isOnline
                    ]));
                }
            }
        } catch (\Exception $e) {
            echo "Error broadcasting user status: {$e->getMessage()}\n";
        }
    }
}

// Run the WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

echo "WebSocket server running on port 8080\n";
$server->run();
