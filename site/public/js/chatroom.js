/* global csrfToken, buildCourseUrl, displayErrorMessage, WebSocketClient, displaySuccessMessage */

function fetchMessages(chatroomId) {
    $.ajax({
        url: buildCourseUrl(['chat', chatroomId, 'messages']),
        type: 'GET',
        dataType: 'json',
        success: function (responseData) {
            if (responseData.status === 'success' && Array.isArray(responseData.data)) {
                responseData.data.forEach((msg) => {
                    appendMessage(msg.display_name, msg.role, msg.timestamp, msg.content, msg.id);
                });
                const messages_area = document.querySelector('.messages-area');
                messages_area.scrollTop = messages_area.scrollHeight;
            }
        },
        error: function () {
            window.alert('Something went wrong with fetching messages');
        },
    });
}

function sendMessage(chatroomId, userId, displayName, role, content, isAnonymous = false) {
    const toBuild = isAnonymous ? ['chat', chatroomId, 'send', 'anonymous'] : ['chat', chatroomId, 'send'];
    $.ajax({
        url: buildCourseUrl(toBuild),
        type: 'POST',
        data: {
            csrf_token: csrfToken,
            user_id: userId,
            display_name: displayName,
            role: role,
            content: content,
        },
        success: function (response) {
            const msg = JSON.parse(response);
            if (msg.status !== 'success') {
                displayErrorMessage(msg.message);
                return;
            }
        },
        error: function () {
            window.alert('Something went wrong with storing message');
        },
    });
}

function appendMessage(displayName, role, ts, content, msgID) {
    let display_name = displayName;
    if (role && role !== 'student' && display_name.substring(0, 9) !== 'Anonymous') {
        display_name = `${displayName} [${role}]`;
    }

    const messages_area = document.querySelector('.messages-area');
    const message = document.createElement('div');
    message.classList.add('message-container');
    if (role === 'instructor') {
        message.classList.add('admin-message');
    }
    message.setAttribute('data-testid', 'message-container');
    message.setAttribute('id', msgID);

    const messageHeader = document.createElement('div');
    messageHeader.classList.add('message-header');
    messageHeader.setAttribute('data-testid', 'message-header');

    const senderName = document.createElement('span');
    senderName.classList.add('sender-name');
    senderName.setAttribute('data-testid', 'sender-name');
    senderName.innerText = display_name;

    const timestampSpan = document.createElement('span');
    timestampSpan.classList.add('timestamp');
    timestampSpan.innerText = ts;

    messageHeader.appendChild(senderName);
    messageHeader.appendChild(timestampSpan);

    const messageContent = document.createElement('div');
    messageContent.classList.add('message-content');
    messageContent.setAttribute('data-testid', 'message-content');
    messageContent.innerText = content;

    message.appendChild(messageHeader);
    message.appendChild(messageContent);

    messages_area.appendChild(message);

    // automatically scroll to bottom for new messages, if close to bottom
    const distanceFromBottom = messages_area.scrollHeight - messages_area.scrollTop - messages_area.clientHeight;
    if (distanceFromBottom < 110) {
        messages_area.scrollTop = messages_area.scrollHeight;
    }
}

function socketChatMessageHandler(msg) {
    appendMessage(msg.display_name, msg.role, msg.timestamp, msg.content, msg.message_id);
}

function initChatroomSocketClient(chatroomId) {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        switch (msg.type) {
            case 'chat_message':
                socketChatMessageHandler(msg);
                break;
            case 'chat_close':
                if (msg.allow_read_only_after_end) {
                    const messageInput = document.querySelector('.message-input');
                    const sendButton = document.querySelector('.send-message-btn');

                    messageInput.disabled = true;
                    messageInput.placeholder = 'This chat session has ended. Messages are read-only.';
                    sendButton.disabled = true;
                }
                else {
                    window.alert('Chatroom has been closed by the instructor.');
                    window.location.href = buildCourseUrl(['chat']);
                }
                break;
            case 'message_delete': {
                const msgElement = document.getElementById(msg.message_id);
                if (msgElement) {
                    msgElement.remove();
                }
                break;
            }
            default:
                console.error(msg);
        }
    };
    window.socketClient.open('chatrooms', {
        chatroom_id: chatroomId,
    });
}

function showJoinMessage(message) {
    const toast = document.querySelector('.chatroom-toast');
    toast.textContent = message;
    toast.style.visibility = 'visible';
    toast.style.opacity = '0.85';
    setTimeout(() => {
        toast.style.opacity = '0'; // fade out
    }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    const pageDataElement = document.getElementById('page-data');
    if (pageDataElement) {
        const pageData = JSON.parse(pageDataElement.textContent);
        const { chatroomId, userId, displayName, user_admin, isAnonymous, read_only } = pageData;

        showJoinMessage(`You have successfully joined as ${displayName}.`);

        initChatroomSocketClient(chatroomId);

        fetchMessages(chatroomId);

        const sendButton = document.querySelector('.send-message-btn');
        const messageInput = document.querySelector('.message-input');

        if (!read_only) {
            messageInput.addEventListener('keypress', (event) => {
                if (event.keyCode === 13 && !event.shiftKey) {
                    event.preventDefault();
                    sendButton.click();
                }
            });
        }
        if (!read_only) {
            sendButton.addEventListener('click', (event) => {
                event.preventDefault();
                const messageContent = messageInput.value.trim();
                if (messageContent === '') {
                    alert('Please enter a message.');
                    return;
                }

                const role = user_admin ? 'instructor' : 'student';
                sendMessage(chatroomId, userId, displayName, role, messageContent, isAnonymous);

                messageInput.value = '';
            });
        }
        if (read_only) {
            messageInput.disabled = true;
            messageInput.placeholder = 'This chat session has ended. Messages are read-only.';
            sendButton.disabled = true;
        }
    }
});
