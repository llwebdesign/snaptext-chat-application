<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-md">
        <!-- User Profile -->
        <div class="p-4 border-b">
            <div class="flex items-center space-x-3">
                <?php
                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                $avatarUrl = $user['avatar'] ?: 'assets/images/default-avatar.png';
                ?>
                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile" 
                    class="w-10 h-10 rounded-full">
                <div>
                    <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <a href="controllers/auth.php?action=logout" 
                        class="text-sm text-gray-500 hover:text-gray-700">Logout</a>
                </div>
            </div>
        </div>

        <!-- Friends List -->
        <div class="p-4">
            <h2 class="text-lg font-semibold mb-4">Friends</h2>
            <div class="space-y-2" id="friends-list">
                <!-- Friends will be loaded here via JavaScript -->
            </div>
            
            <!-- Add Friend Button -->
            <button onclick="showAddFriendModal()" 
                class="mt-4 w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                Add Friend
            </button>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        <!-- Chat Header -->
        <div class="bg-white shadow-sm p-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="" alt="" class="w-8 h-8 rounded-full" id="chat-user-avatar">
                <h2 class="font-semibold" id="chat-user-name">Select a friend to start chatting</h2>
            </div>
            <div class="flex space-x-2">
                <button onclick="startCall()" class="p-2 rounded-full hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </button>
                <button onclick="startVideoCall()" class="p-2 rounded-full hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
            <!-- Messages will be loaded here via JavaScript -->
        </div>

        <!-- Message Input -->
        <div class="bg-white p-4 border-t">
            <form id="message-form" class="flex space-x-4">
                <input type="text" id="message-input" 
                    class="flex-1 px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                    placeholder="Type your message...">
                <button type="button" onclick="attachImage()" 
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </button>
                <button type="submit" 
                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Send
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Add Friend Modal -->
<div id="add-friend-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-96">
        <h3 class="text-lg font-semibold mb-4">Add Friend</h3>
        <input type="email" id="friend-email" placeholder="Enter friend's email" 
            class="w-full px-4 py-2 border rounded-md mb-4">
        <div class="flex justify-end space-x-2">
            <button onclick="closeAddFriendModal()" 
                class="px-4 py-2 text-gray-600 hover:text-gray-800">
                Cancel
            </button>
            <button onclick="sendFriendRequest()" 
                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                Send Request
            </button>
        </div>
    </div>
</div>

<!-- Call Modal -->
<div id="call-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-[800px]">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" id="call-status">Calling...</h3>
            <button onclick="endCall()" class="text-red-600 hover:text-red-800">
                End Call
            </button>
        </div>
        <div class="relative">
            <video id="remote-video" class="w-full h-[400px] bg-black rounded-lg" autoplay></video>
            <video id="local-video" class="absolute bottom-4 right-4 w-32 h-24 bg-black rounded-lg" autoplay muted></video>
        </div>
        <div class="flex justify-center space-x-4 mt-4">
            <button onclick="toggleMute()" class="p-3 rounded-full bg-gray-200 hover:bg-gray-300">
                <svg class="w-6 h-6" id="mute-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0-11V3m0 7h4m-4 0H8"/>
                </svg>
            </button>
            <button onclick="toggleVideo()" class="p-3 rounded-full bg-gray-200 hover:bg-gray-300">
                <svg class="w-6 h-6" id="video-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/simple-peer@9.11.0/simplepeer.min.js"></script>
<script src="assets/js/chat.js"></script>
