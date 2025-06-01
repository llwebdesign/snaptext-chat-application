let currentChatUser = null;
let peer = null;
let localStream = null;
let remoteStream = null;

// WebSocket connection
const ws = new WebSocket('ws://' + window.location.hostname + ':8080');

ws.onopen = () => {
    console.log('Connected to WebSocket server');
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    handleWebSocketMessage(data);
};

function handleWebSocketMessage(data) {
    switch (data.type) {
        case 'message':
            appendMessage(data.message);
            break;
        case 'friend_request':
            handleFriendRequest(data);
            break;
        case 'call_offer':
            handleCallOffer(data);
            break;
        case 'call_answer':
            handleCallAnswer(data);
            break;
        case 'ice_candidate':
            handleIceCandidate(data);
            break;
    }
}

// Chat Functions
function loadFriendsList() {
    fetch('controllers/friends.php?action=list')
        .then(response => response.json())
        .then(friends => {
            const friendsList = document.getElementById('friends-list');
            friendsList.innerHTML = '';
            
            friends.forEach(friend => {
                const friendElement = document.createElement('div');
                friendElement.className = 'flex items-center space-x-3 p-2 hover:bg-gray-100 rounded-md cursor-pointer';
                friendElement.onclick = () => startChat(friend);
                
                friendElement.innerHTML = `
                    <img src="${friend.avatar || 'assets/images/default-avatar.png'}" 
                        alt="${friend.username}" class="w-8 h-8 rounded-full">
                    <span class="font-medium">${friend.username}</span>
                `;
                
                friendsList.appendChild(friendElement);
            });
        });
}

function startChat(user) {
    currentChatUser = user;
    document.getElementById('chat-user-name').textContent = user.username;
    document.getElementById('chat-user-avatar').src = user.avatar || 'assets/images/default-avatar.png';
    loadMessages(user.id);
}

function loadMessages(userId) {
    fetch(`controllers/messages.php?action=list&user_id=${userId}`)
        .then(response => response.json())
        .then(messages => {
            const container = document.getElementById('messages-container');
            container.innerHTML = '';
            messages.forEach(appendMessage);
            container.scrollTop = container.scrollHeight;
        });
}

function appendMessage(message) {
    const container = document.getElementById('messages-container');
    const messageElement = document.createElement('div');
    const isOwn = message.sender_id === parseInt(document.body.dataset.userId);
    
    messageElement.className = `flex ${isOwn ? 'justify-end' : 'justify-start'}`;
    
    const contentClass = isOwn ? 
        'bg-indigo-600 text-white' : 
        'bg-gray-200 text-gray-900';
    
    let content = '';
    if (message.image) {
        content = `<img src="${message.image}" alt="Shared image" class="max-w-xs rounded-lg">`;
    } else {
        content = message.text;
    }
    
    messageElement.innerHTML = `
        <div class="max-w-sm rounded-lg px-4 py-2 ${contentClass}">
            ${content}
            <div class="text-xs mt-1 ${isOwn ? 'text-indigo-200' : 'text-gray-500'}">
                ${new Date(message.created_at).toLocaleTimeString()}
            </div>
        </div>
    `;
    
    container.appendChild(messageElement);
    container.scrollTop = container.scrollHeight;
}

// Friend Request Functions
function showAddFriendModal() {
    document.getElementById('add-friend-modal').classList.remove('hidden');
}

function closeAddFriendModal() {
    document.getElementById('add-friend-modal').classList.add('hidden');
}

function sendFriendRequest() {
    const email = document.getElementById('friend-email').value;
    
    fetch('controllers/friends.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'send_request',
            email: email
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Friend request sent successfully!');
            closeAddFriendModal();
        } else {
            alert(data.error || 'Failed to send friend request');
        }
    });
}

// Message Functions
document.getElementById('message-form').onsubmit = (e) => {
    e.preventDefault();
    if (!currentChatUser) return;
    
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    
    if (message) {
        sendMessage({
            type: 'text',
            content: message,
            recipient_id: currentChatUser.id
        });
        input.value = '';
    }
};

function sendMessage(messageData) {
    fetch('controllers/messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(messageData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendMessage(data.message);
        }
    });
}

function attachImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('recipient_id', currentChatUser.id);
        
        fetch('controllers/messages.php?action=upload_image', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendMessage(data.message);
            }
        });
    };
    
    input.click();
}

// Call Functions
async function startCall(withVideo = false) {
    if (!currentChatUser) return;
    
    try {
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: withVideo
        });
        
        document.getElementById('local-video').srcObject = localStream;
        document.getElementById('call-modal').classList.remove('hidden');
        
        peer = new SimplePeer({
            initiator: true,
            stream: localStream
        });
        
        peer.on('signal', data => {
            ws.send(JSON.stringify({
                type: 'call_offer',
                recipient_id: currentChatUser.id,
                signal: data
            }));
        });
        
        peer.on('stream', stream => {
            document.getElementById('remote-video').srcObject = stream;
        });
        
    } catch (err) {
        console.error('Failed to get media devices:', err);
        alert('Failed to access camera/microphone');
    }
}

function handleCallOffer(data) {
    if (confirm(`Incoming call from ${data.sender_name}. Accept?`)) {
        startCall(data.withVideo).then(() => {
            peer.signal(data.signal);
        });
    }
}

function endCall() {
    if (peer) {
        peer.destroy();
        peer = null;
    }
    
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }
    
    document.getElementById('call-modal').classList.add('hidden');
    document.getElementById('local-video').srcObject = null;
    document.getElementById('remote-video').srcObject = null;
}

function toggleMute() {
    if (localStream) {
        const audioTrack = localStream.getAudioTracks()[0];
        audioTrack.enabled = !audioTrack.enabled;
        document.getElementById('mute-icon').classList.toggle('text-red-600');
    }
}

function toggleVideo() {
    if (localStream) {
        const videoTrack = localStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            document.getElementById('video-icon').classList.toggle('text-red-600');
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadFriendsList();
});
