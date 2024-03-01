/* global csrfToken userId */
function fetchMessages(chatroomId, my_id) {
    $.ajax({
        url: buildCourseUrl(['chat', chatroomId, 'messages']),
        type: 'GET',
        dataType: 'json',
        success: function(responseData) {
            if (responseData.status === 'success' && Array.isArray(responseData.data)) {
                responseData.data.forEach(msg => {
                    let display_name = msg.display_name;
                    if (msg.user_id === my_id) {
                        display_name = "me";
                    }
                    appendMessage(display_name, msg.role, msg.timestamp, msg.content);
                });
                const messages_area = document.querySelector(".messages-area");
                messages_area.scrollTop = messages_area.scrollHeight;
            }
        },
        error: function() {
            window.alert('Something went wrong with fetching messages');
        },
    });
}

function sendMessage(chatroomId, userId, displayName, role, content) {
    $.ajax({
        url: buildCourseUrl(['chat', chatroomId, 'send']),
        type: 'POST',
        data: {
            'csrf_token': csrfToken,
            'user_id': userId,
            'display_name': displayName,
            'role': role,
            'content': content
        },
        error: function() {
            window.alert('Something went wrong with storing message');
        },
    })
    window.socketClient.send({'type': "chat_message", 'content': content, 'user_id': userId, 'display_name': displayName, 'role': role, 'timestamp': new Date(Date.now()).toLocaleString()})
    appendMessage("me", role, null, content);
}

function appendMessage(displayName, role, ts, content) {
    let timestamp = ts;
    if (!timestamp) {
        timestamp = new Date(Date.now()).toLocaleString('en-us',  { year:"numeric", month:"short", day:"numeric", hour:"numeric", minute:"numeric"});
    }
    else {
        timestamp = new Date(ts).toLocaleString('en-us', { year:"numeric", month:"short", day:"numeric", hour:"numeric", minute:"numeric"});
    }

    let display_name = displayName;
    if (role && role !== 'student' && display_name !== 'me') {
        display_name = `${displayName}[instructor]`;
    }

    const messages_area = document.querySelector('.messages-area');
    const message = document.createElement('div');
    message.classList.add('message-container');
    if (role === "instructor") {
        message.classList.add('admin-message')
    }
    message.innerHTML = `
        <div class="message-header">
            <span class="sender-name">${display_name}</span>
            <span class="timestamp">${timestamp}</span>
        </div>
        <div class="message-content">
            ${content}
        </div>
    `;

    messages_area.appendChild(message);
    const distanceFromBottom = messages_area.scrollHeight - messages_area.scrollTop - messages_area.clientHeight;
    if ( distanceFromBottom < 110) {
        messages_area.scrollTop = messages_area.scrollHeight;
    }
}

function initChatroomSocketClient(chatroomId) {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        if (msg.type === "chat_message") {
            let sender_name = msg.display_name;
            let role = msg.role;
            appendMessage(sender_name, role, msg.timestamp, msg.content);
        }
    };
    window.socketClient.open(`chatroom_${chatroomId}`);
}

function newChatroomForm() {
    const form = $('#create-chatroom-form');
    form.css('display', 'block');
}

function editChatroomForm(chatroom_id, baseUrl) {
    const form = $('#edit-chatroom-form');
    form.css('display', 'block');
    document.getElementById('chatroom-edit-form').action = `${baseUrl}/${chatroom_id}/edit`;
}

function deleteChatroomForm(chatroom_id, chatroom_name, base_url) {
    if (confirm(`This will delete chatroom '${chatroom_name}'. Are you sure?`)) {
        const url = `${base_url}/deleteChatroom`;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('chatroom_id', chatroom_id);
        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            cache: false,
            contentType: false,
            success: function(data) {
                try {
                    const msg = JSON.parse(data);
                    if (msg.status !== 'success') {
                        console.error(msg);
                        window.alert('Something went wrong. Please try again.');
                    }
                    else {
                        window.location.reload();
                    }
                }
                catch (err) {
                    console.error(err);
                    window.alert('Something went wrong. Please try again.');
                }
            },
            error: function(err) {
                console.error(err);
                window.alert('Something went wrong. Please try again.');
            },
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    $('.popup-form').css('display', 'none');
    const pageDataElement = document.getElementById('page-data');
    if (pageDataElement) {
        const pageData = JSON.parse(pageDataElement.textContent);
        const { chatroomId, userId, displayName, user_admin } = pageData;

        initChatroomSocketClient(chatroomId)
        fetchMessages(chatroomId, userId);

        const sendMsgButton = document.querySelector('.send-message-btn');
        const messageInput = document.querySelector('.message-input');

        messageInput.addEventListener("keypress", function(event) {
            if (event.keyCode === 13 && !event.shiftKey) {
                event.preventDefault();
                sendMsgButton.click();
            }
        });

        sendMsgButton.addEventListener('click', (event) => {
            event.preventDefault();
            const messageContent = messageInput.value.trim();
            if (messageContent === '') {
                alert('Please enter a message.');
                return;
            }

            let role = user_admin ? 'instructor' : 'student';
            sendMessage(chatroomId, userId, displayName, role, messageContent);

            messageInput.value = '';
        });
    }
});
