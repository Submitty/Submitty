/* global csrfToken userId */
function fetchMessages(chatroomId, my_id) {
    $.ajax({
        url: buildCourseUrl(['chat', chatroomId, 'messages']),
        type: 'GET',
        dataType: 'json',
        success: function(responseData) {
            if (responseData.status === 'success' && Array.isArray(responseData.data)) {
                responseData.data.forEach(msg => {
                    if (msg.user_id === my_id) {
                        appendMessage("me", msg.timestamp, msg.content);
                    }
                    else {
                        appendMessage(msg.display_name, msg.timestamp, msg.content);
                    }
                });
            }
        },
        error: function() {
            window.alert('Something went wrong with fetching messages');
        },
    });
}

function sendMessage(chatroomId, userId, displayName, content) {
    $.ajax({
        url: buildCourseUrl(['chat', chatroomId, 'send']),
        type: 'POST',
        data: {
            'csrf_token': csrfToken,
            'user_id': userId,
            'content': content,
            'display_name': displayName
        },
        error: function() {
            window.alert('Something went wrong with storing message');
        },
    })
    window.socketClient.send({'type': "chat_message", 'content': content, 'user_id': userId, 'display_name': displayName, 'timestamp': new Date(Date.now()).toLocaleString()})
    appendMessage("me", null, content);
}

function appendMessage(displayName, ts, content) {
    let timestamp = ts;
    if (!timestamp) {
        timestamp = new Date(Date.now()).toLocaleString('en-us',  { year:"numeric", month:"short", day:"numeric", hour:"numeric", minute:"numeric"});
    }
    else {
        timestamp = new Date(ts).toLocaleString('en-us', { year:"numeric", month:"short", day:"numeric", hour:"numeric", minute:"numeric"});
    }

    const messages_area = document.querySelector('.messages-area');
    const message = document.createElement('div');
    message.classList.add('message-container');
    message.innerHTML = `
        <div class="message-header">
            <span class="sender-name">${displayName}</span>
            <span class="timestamp">${timestamp}</span>
        </div>
        <div class="message-content">
            ${content}
        </div>
    `;
    messages_area.appendChild(message);
    messages_area.scrollTop = messages_area.scrollHeight;
}

function initChatroomSocketClient(chatroomId) {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        if (msg.type === "chat_message") {
            let sender = msg.display_name;
            appendMessage(sender, msg.timestamp, msg.content);
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
        const { chatroomId, userId, displayName } = pageData;

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
            sendMessage(chatroomId, userId, displayName, messageContent);
            messageInput.value = '';
        });
    }
});
