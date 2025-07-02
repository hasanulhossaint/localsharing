<?php
$file = 'shared_list.txt';
$users_file = 'online_users.json';
$uploads_dir = 'uploads';

// Ensure uploads directory exists
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0777, true);

// Add/update online user (called via AJAX every 20s)
if (isset($_POST['online_id']) && isset($_POST['online_name']) && isset($_POST['online_color'])) {
    $id = $_POST['online_id'];
    $name = $_POST['online_name'];
    $color = $_POST['online_color'];
    $now = time();

    $users = file_exists($users_file) ? json_decode(file_get_contents($users_file), true) : [];
    $users[$id] = ['name' => $name, 'color' => $color, 'last' => $now];
    foreach ($users as $uid => $u) {
        if ($now - $u['last'] > 40) unset($users[$uid]);
    }
    file_put_contents($users_file, json_encode($users));
    exit;
}

// Get online users
if (isset($_GET['get_online'])) {
    $users = file_exists($users_file) ? json_decode(file_get_contents($users_file), true) : [];
    $now = time();
    foreach ($users as $uid => $u) {
        if ($now - $u['last'] > 40) unset($users[$uid]);
    }
    echo json_encode(array_values($users));
    exit;
}

// Add new text or file message to the list
if (isset($_POST['text']) || isset($_POST['file'])) {
    $msgid = uniqid('msg', true);
    $time = date('H:i');
    $device = trim($_POST['device']);
    $color = trim($_POST['color']);
    $avatar = trim($_POST['avatar']);
    $id = trim($_POST['id']);
    $type = isset($_POST['type']) ? $_POST['type'] : 'text';
    $entry = [
        'msgid' => $msgid,
        'time' => $time,
        'device' => $device,
        'color' => $color,
        'avatar' => $avatar,
        'id' => $id,
        'type' => $type
    ];
    if ($type === 'text') {
        $text = trim($_POST['text']);
        if ($text !== '') {
            $entry['text'] = $text;
        }
    } elseif ($type === 'file' && isset($_POST['file'])) {
        $entry['file'] = $_POST['file'];
        $entry['filename'] = $_POST['filename'];
        $entry['filetype'] = $_POST['filetype'];
    }
    file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    exit;
}

// Delete a message by unique msgid (only if sent by the same device)
if (isset($_POST['delete_msgid']) && isset($_POST['my_id'])) {
    $delete_msgid = trim($_POST['delete_msgid']);
    $my_id = trim($_POST['my_id']);
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_lines = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            if (isset($entry['msgid']) && $entry['msgid'] === $delete_msgid && isset($entry['id']) && $entry['id'] === $my_id) {
                // If file, also remove the file
                if (isset($entry['file']) && file_exists($entry['file'])) {
                    @unlink($entry['file']);
                }
                continue;
            }
            $new_lines[] = $line;
        }
        file_put_contents($file, implode("\n", $new_lines) . "\n");
    }
    exit;
}

// File upload handler (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    $allowed = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'application/pdf' => 'pdf'
    ];
    if (isset($allowed[$f['type']])) {
        $ext = $allowed[$f['type']];
        $base = uniqid('f', true) . '.' . $ext;
        $dest = "$uploads_dir/$base";
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            echo json_encode([
                'success' => true,
                'url' => $dest,
                'filename' => $f['name'],
                'filetype' => $f['type']
            ]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// Get the full list
if (isset($_GET['get'])) {
    if (file_exists($file)) {
        echo file_get_contents($file);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LAN Text Share</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --primary: #4f8cff;
            --background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            --bubble-me: #e3f1ff;
            --bubble-other: #fff;
            --border: #e0e0e0;
            --device-gray: #888;
            --delete-bg: #ffb4b4;
            --copy-btn-bg: #f3f6fb;
            --copy-btn-hover: #e0e7ff;
            --copy-btn-active: #4f8cff;
            --copy-btn-icon: #4f8cff;
            --copy-btn-icon-copied: #28b463;
        }
        body {
            background: var(--background);
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .container {
            max-width: 540px;
            margin: 32px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            min-height: 600px;
            overflow: hidden;
        }
        h2 {
            margin: 0;
            padding: 28px 28px 0 28px;
            color: #222;
            font-weight: 700;
            font-size: 1.7em;
            letter-spacing: 1px;
        }
        .online-bar {
            background: #f0f7ff;
            padding: 8px 24px;
            font-size: 1em;
            border-bottom: 1px solid #e0e0e0;
        }
        .online-user {
            display: inline-block;
            margin-right: 10px;
            padding: 2px 10px 2px 6px;
            border-radius: 12px;
            font-weight: 500;
            color: #fff;
            font-size: 0.98em;
        }
        .nickname-area {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px 10px 28px;
            background: #f8fafc;
            border-bottom: 1px solid #e0e0e0;
        }
        .nickname-area label {
            font-size: 1em;
            color: #555;
            font-weight: 500;
        }
        #nickname {
            font-size: 1em;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1.5px solid #b0c4ff;
            width: 130px;
            background: #fff;
            margin-right: 8px;
            box-shadow: 0 2px 6px rgba(79, 140, 255, 0.08);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        #nickname:focus {
            border-color: #4f8cff;
            box-shadow: 0 4px 12px rgba(79, 140, 255, 0.16);
        }
        #saveNick {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 6px 18px;
            font-size: 1em;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        #saveNick:active {
            background: #2e6fd8;
        }
        #nickStatus {
            margin-left: 12px;
            color: #4f8cff;
            font-size: 0.97em;
            min-width: 60px;
        }
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 28px;
            background: var(--background);
            border-radius: 0 0 18px 18px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            scroll-behavior: smooth;
        }
        .bubble {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            max-width: 82%;
            padding: 14px 20px 14px 16px;
            border-radius: 20px 20px 20px 8px;
            background: var(--bubble-other);
            border: 1.5px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            font-size: 1.1em;
            word-break: break-word;
            position: relative;
            animation: popin 0.3s cubic-bezier(.4,1.4,.7,1.01);
            transition: box-shadow 0.25s, background 0.25s;
        }
        .bubble.me {
            align-self: flex-end;
            background: var(--bubble-me);
            border-color: var(--primary);
            border-radius: 20px 20px 8px 20px;
        }
        .bubble:hover {
            box-shadow: 0 4px 16px rgba(79,140,255,0.15);
            background: #f0f8ff;
        }
        .bubble.me:hover {
            background: #d9ecff;
        }
        @keyframes popin {
            0% { transform: scale(0.95) translateY(10px); opacity: 0.3; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }
        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #ccc;
            color: #fff;
            font-weight: 700;
            font-size: 1.18em;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 3px;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border: 2px solid #fff;
        }
        .bubble.me .avatar {
            border: 2px solid var(--primary);
        }
        .bubble-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .meta {
            font-size: 0.95em;
            color: #888;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .device-label {
            display: inline-block;
            min-width: 68px;
            text-align: center;
            padding: 2px 10px;
            font-size: 0.92em;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: var(--device-gray);
            margin-left: 0;
            margin-right: 0;
        }
        .device-label.me {
            background: var(--primary);
        }
        .delete-btn {
            background: var(--delete-bg);
            color: #a00;
            border: none;
            border-radius: 6px;
            padding: 2px 10px;
            margin-left: 10px;
            font-size: 0.9em;
            cursor: pointer;
            transition: background 0.15s;
        }
        .delete-btn:hover {
            background: #ff7f7f;
        }
        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--copy-btn-bg);
            border: none;
            border-radius: 999px;
            padding: 4px 14px 4px 10px;
            cursor: pointer;
            font-size: 0.98em;
            color: #4f8cff;
            font-weight: 500;
            box-shadow: 0 1px 4px rgba(79,140,255,0.06);
            transition: background 0.16s, color 0.16s;
            margin-left: 10px;
            outline: none;
            opacity: 1;
        }
        .copy-btn:hover, .copy-btn:focus {
            background: #e0e7ff;
            color: #2563eb;
        }
        .copy-btn.copied {
            background: #eafaf1;
            color: #28b463;
        }
        .copy-btn svg {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 0;
        }
        .file-upload-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px 12px 28px;
            background: #f8fafc;
            border-bottom: 1px solid #e0e0e0;
        }
        .file-upload-bar input[type="file"] {
            display: none;
        }
        .file-upload-label {
            background: #e0e7ff;
            color: #2563eb;
            border: none;
            border-radius: 8px;
            padding: 7px 20px;
            font-size: 1em;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .file-upload-label:hover {
            background: #c7d8ff;
        }
        .file-upload-status {
            font-size: 0.98em;
            color: #4f8cff;
            min-width: 60px;
        }
        .file-thumb {
            max-width: 220px;
            max-height: 180px;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.12);
            margin-top: 6px;
            margin-bottom: 2px;
            display: block;
        }
        .file-link {
            color: #4f8cff;
            text-decoration: underline;
            font-weight: 500;
        }
        .input-area {
            display: flex;
            gap: 10px;
            padding: 18px 28px 18px 28px;
            background: #fff;
            border-radius: 0 0 18px 18px;
            box-shadow: 0 -1px 6px rgba(0,0,0,0.03);
        }
        textarea {
            width: 100%;
            max-width: 100%;
            min-height: 60px;
            max-height: 150px;
            padding: 14px 18px;
            font-size: 1.1em;
            border-radius: 12px;
            border: 1.8px solid #b0c4ff;
            box-shadow: 0 2px 6px rgba(79, 140, 255, 0.15);
            resize: vertical;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        textarea:focus {
            border-color: #4f8cff;
            box-shadow: 0 4px 12px rgba(79, 140, 255, 0.4);
        }
        button {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0 26px;
            font-size: 1.08em;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        button:active {
            background: #2e6fd8;
        }
        #status {
            text-align: right;
            color: #4f8cff;
            font-size: 1em;
            padding-right: 28px;
            min-height: 20px;
        }
        @media (max-width: 700px) {
            .container { max-width: 100vw; margin: 0; border-radius: 0; }
            .messages, .input-area, .nickname-area, .online-bar, .file-upload-bar { padding: 10px !important; }
            .copy-btn { font-size: 1em; }
            textarea { font-size: 1em; min-height: 50px; padding: 12px 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>LAN Text Share</h2>
        <div class="online-bar">
            <strong>Online:</strong>
            <span id="onlineUsers"></span>
        </div>
        <div class="nickname-area">
            <label for="nickname">Your name:</label>
            <input id="nickname" maxlength="16" autocomplete="off" />
            <button id="saveNick">Save</button>
            <span id="nickStatus"></span>
        </div>
        <div class="file-upload-bar">
            <label class="file-upload-label" for="fileInput">Share Image/PDF</label>
            <input type="file" id="fileInput" accept="image/*,application/pdf" />
            <span id="fileStatus"></span>
        </div>
        <div class="messages" id="messages"></div>
        <div class="input-area">
            <textarea id="shared" placeholder="Type your message..."></textarea>
            <button id="update">Send</button>
        </div>
        <div id="status"></div>
        <audio id="notifySound" src="notify.mp3" preload="auto"></audio>
    </div>
    <script>
        // Distinctive color palette for devices
        const deviceColors = [
            "#4f8cff", "#ff7c5e", "#00bc99", "#ffb347", "#a259ff", "#ff5eae",
            "#00b7ff", "#ff6b81", "#43e97b", "#f7b267", "#2ec4b6", "#ff6f61"
        ];

        // Assign device name, color, avatar, and unique id
        let deviceId = localStorage.getItem('lantextshare_id');
        if (!deviceId) {
            deviceId = Math.random().toString(36).substr(2, 10);
            localStorage.setItem('lantextshare_id', deviceId);
        }

        let nickname = localStorage.getItem('lantextshare_nick');
        if (!nickname) {
            const n = Math.floor(Math.random() * 9000 + 1000);
            nickname = "Device " + n;
            localStorage.setItem('lantextshare_nick', nickname);
        }

        let deviceColor = localStorage.getItem('lantextshare_color');
        if (!deviceColor) {
            const n = Math.floor(Math.random() * 9000 + 1000);
            deviceColor = deviceColors[n % deviceColors.length];
            localStorage.setItem('lantextshare_color', deviceColor);
        }

        function getAvatar(name) {
            let initials = name.split(' ').map(w => w[0]).join('').substr(0,2).toUpperCase();
            return initials;
        }

        document.getElementById('nickname').value = nickname;
        document.getElementById('saveNick').onclick = function() {
            const newNick = document.getElementById('nickname').value.trim() || 'Device';
            nickname = newNick;
            localStorage.setItem('lantextshare_nick', nickname);
            document.getElementById('nickStatus').textContent = "Saved!";
            setTimeout(() => document.getElementById('nickStatus').textContent = "", 1200);
            loadList();
            updateOnlineStatus();
        };

        const shared = document.getElementById('shared');
        const status = document.getElementById('status');
        const messages = document.getElementById('messages');
        const updateBtn = document.getElementById('update');
        const notifySound = document.getElementById('notifySound');
        const fileInput = document.getElementById('fileInput');
        const fileStatus = document.getElementById('fileStatus');

        // --- Online Users ---
        function updateOnlineStatus() {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'online_id=' + encodeURIComponent(deviceId) +
                      '&online_name=' + encodeURIComponent(nickname) +
                      '&online_color=' + encodeURIComponent(deviceColor)
            });
        }
        function fetchOnlineUsers() {
            fetch('?get_online=1')
                .then(r => r.json())
                .then(users => {
                    let html = '';
                    users.forEach(u => {
                        html += `<span class="online-user" style="background:${u.color}">${u.name}</span>`;
                    });
                    document.getElementById('onlineUsers').innerHTML = html || '<em>No one online</em>';
                });
        }
        setInterval(updateOnlineStatus, 20000); // every 20s
        setInterval(fetchOnlineUsers, 5000);    // every 5s
        updateOnlineStatus();
        fetchOnlineUsers();

        // --- Copy Message ---
        function copyMessage(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#28b463" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Copied
                `;
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#4f8cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="9" y="9" width="13" height="13" rx="2.5"/>
                            <path d="M5 15V5a2 2 0 0 1 2-2h10"/>
                        </svg>
                        Copy
                    `;
                }, 1200);
            });
        }

        // --- Delete Message ---
        function deleteMessage(msgid) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'delete_msgid=' + encodeURIComponent(msgid) + '&my_id=' + encodeURIComponent(deviceId)
            }).then(() => {
                loadList();
            });
        }
        window.deleteMessage = deleteMessage;

        // --- Sound Notification ---
        let lastMsgCount = 0;

        function renderMessages(list) {
            messages.innerHTML = '';
            list.forEach((msg, i) => {
                if (!msg.device) return;
                const isMe = msg.id === deviceId;
                const div = document.createElement('div');
                div.className = 'bubble' + (isMe ? ' me' : '');
                div.style.background = isMe ? 'var(--bubble-me)' : msg.color + '22';
                div.style.borderColor = isMe ? 'var(--primary)' : msg.color;

                // Avatar
                const avatar = document.createElement('div');
                avatar.className = 'avatar';
                avatar.style.background = msg.color;
                avatar.textContent = msg.avatar || '?';

                // Content
                const content = document.createElement('div');
                content.className = 'bubble-content';

                // Message body
                if (msg.type === 'file' && msg.file && msg.filename) {
                    if (msg.filetype && msg.filetype.startsWith('image/')) {
                        content.innerHTML = `<a href="${msg.file}" target="_blank"><img class="file-thumb" src="${msg.file}" alt="image"/></a>`;
                    } else if (msg.filetype === 'application/pdf') {
                        content.innerHTML = `<a class="file-link" href="${msg.file}" target="_blank" download="${msg.filename}">📄 ${msg.filename}</a>`;
                    }
                } else if (msg.type === 'text') {
                    content.innerHTML = `<div>${msg.text.replace(/</g,"&lt;")}</div>`;
                }

                // Copy button for text
                if (msg.type === 'text') {
                    const copyBtn = document.createElement('button');
                    copyBtn.className = 'copy-btn';
                    copyBtn.title = "Copy message";
                    copyBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#4f8cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="9" y="9" width="13" height="13" rx="2.5"/>
                            <path d="M5 15V5a2 2 0 0 1 2-2h10"/>
                        </svg>
                        Copy
                    `;
                    copyBtn.onclick = () => copyMessage(msg.text, copyBtn);
                    content.appendChild(copyBtn);
                }

                // Meta
                content.innerHTML += `<div class="meta">
                        <span>${msg.time}</span>
                        <span class="device-label${isMe ? ' me' : ''}" style="background:${isMe ? 'var(--primary)' : msg.color}">
                            ${isMe ? 'You' : msg.device}
                        </span>
                        ${isMe ? `<button class="delete-btn" title="Delete this message" onclick="deleteMessage('${msg.msgid}')">&#10006;</button>` : ''}
                    </div>`;

                div.appendChild(avatar);
                div.appendChild(content);
                messages.appendChild(div);
            });
            // --- Sound notification if new message from others ---
            if (list.length > lastMsgCount && list.length > 0) {
                const lastMsg = list[list.length - 1];
                if (lastMsg.id !== deviceId) {
                    notifySound.play();
                    if (window.Notification && Notification.permission === "granted") {
                        new Notification("New message from " + lastMsg.device, { body: lastMsg.text || lastMsg.filename });
                    }
                }
            }
            lastMsgCount = list.length;
            scrollToBottom();
        }

        // Fetch and display the shared list
        function loadList() {
            fetch('?get=1')
                .then(response => response.text())
                .then(text => {
                    if (text.trim() === '') {
                        messages.innerHTML = '<em>No messages yet.</em>';
                        return;
                    }
                    const list = text.trim().split('\n').map(line => {
                        try { return JSON.parse(line); } catch { return {}; }
                    });
                    renderMessages(list);
                });
        }

        // Smooth scroll to bottom
        function scrollToBottom() {
            messages.scrollTo({top: messages.scrollHeight, behavior: 'smooth'});
        }

        setInterval(loadList, 2000);
        loadList();

        // Send new text to the server
        updateBtn.onclick = function() {
            const text = shared.value.trim();
            if (text === '') return;
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'text=' + encodeURIComponent(text) +
                      '&device=' + encodeURIComponent(nickname) +
                      '&color=' + encodeURIComponent(deviceColor) +
                      '&avatar=' + encodeURIComponent(getAvatar(nickname)) +
                      '&id=' + encodeURIComponent(deviceId) +
                      '&type=text'
            }).then(() => {
                shared.value = '';
                status.textContent = "Sent!";
                setTimeout(() => status.textContent = "", 1000);
                setTimeout(scrollToBottom, 200);
            });
        };

        // Send on Enter (with Shift+Enter for newline)
        shared.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                updateBtn.click();
            }
        });

        // File upload
        document.querySelector('.file-upload-label').onclick = () => fileInput.click();
        fileInput.onchange = function() {
            if (!fileInput.files.length) return;
            const file = fileInput.files[0];
            if (!/^(image\/(jpeg|png|gif|webp)|application\/pdf)$/.test(file.type)) {
                fileStatus.textContent = "Only images or PDF!";
                fileInput.value = '';
                setTimeout(() => fileStatus.textContent = '', 1500);
                return;
            }
            fileStatus.textContent = "Uploading...";
            const fd = new FormData();
            fd.append('file', file);
            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // Send as a file message
                        fetch('', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'file=' + encodeURIComponent(res.url) +
                                  '&filename=' + encodeURIComponent(res.filename) +
                                  '&filetype=' + encodeURIComponent(res.filetype) +
                                  '&device=' + encodeURIComponent(nickname) +
                                  '&color=' + encodeURIComponent(deviceColor) +
                                  '&avatar=' + encodeURIComponent(getAvatar(nickname)) +
                                  '&id=' + encodeURIComponent(deviceId) +
                                  '&type=file'
                        }).then(() => {
                            fileStatus.textContent = "Shared!";
                            setTimeout(() => fileStatus.textContent = '', 1200);
                            loadList();
                        });
                    } else {
                        fileStatus.textContent = "Upload failed!";
                        setTimeout(() => fileStatus.textContent = '', 1500);
                    }
                });
            fileInput.value = '';
        };

        // Paste image support
        document.body.addEventListener('paste', function(e) {
            if (e.clipboardData && e.clipboardData.files.length) {
                const file = e.clipboardData.files[0];
                if (!/^(image\/(jpeg|png|gif|webp))$/.test(file.type)) return;
                fileStatus.textContent = "Uploading...";
                const fd = new FormData();
                fd.append('file', file);
                fetch('', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            fetch('', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'file=' + encodeURIComponent(res.url) +
                                      '&filename=' + encodeURIComponent(res.filename) +
                                      '&filetype=' + encodeURIComponent(res.filetype) +
                                      '&device=' + encodeURIComponent(nickname) +
                                      '&color=' + encodeURIComponent(deviceColor) +
                                      '&avatar=' + encodeURIComponent(getAvatar(nickname)) +
                                      '&id=' + encodeURIComponent(deviceId) +
                                      '&type=file'
                            }).then(() => {
                                fileStatus.textContent = "Shared!";
                                setTimeout(() => fileStatus.textContent = '', 1200);
                                loadList();
                            });
                        } else {
                            fileStatus.textContent = "Upload failed!";
                            setTimeout(() => fileStatus.textContent = '', 1500);
                        }
                    });
            }
        });

        // Ask for notification permission on load
        if (window.Notification && Notification.permission !== "granted") {
            Notification.requestPermission();
        }
    </script>
</body>
</html>
