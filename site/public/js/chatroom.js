/* global csrfToken */

// eslint-disable-next-line no-unused-vars
function fetchMessages(chatroomId) {
    $.ajax({
        // eslint-disable-next-line no-undef
        url: buildCourseUrl(['chat', chatroomId, 'messages']),
        type: 'GET',
        dataType: 'json',
        success: function (responseData) {
            if (responseData.status === 'success' && Array.isArray(responseData.data)) {
                responseData.data.forEach((msg) => {
                    appendMessage(msg.display_name, msg.role, msg.timestamp, msg.content);
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

// eslint-disable-next-line no-unused-vars
function sendMessage(chatroomId, userId, displayName, role, content) {
    $.ajax({
        // eslint-disable-next-line no-undef
        url: buildCourseUrl(['chat', chatroomId, 'send']),
        type: 'POST',
        data: {
            csrf_token: csrfToken,
            user_id: userId,
            display_name: displayName,
            role: role,
            content: content,
        },
        success: function (response) {
            try {
                // eslint-disable-next-line no-unused-vars
                const json = JSON.parse(response);
            }
            catch (e) {
                // eslint-disable-next-line no-undef
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
        },
        error: function () {
            window.alert('Something went wrong with storing message');
        },
    });
}

function appendMessage(displayName, role, ts, content) {
    let timestamp = ts;
    if (!timestamp) {
        timestamp = new Date(Date.now()).toLocaleString('en-us', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' });
    }
    else {
        timestamp = new Date(ts).toLocaleString('en-us', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' });
    }

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

    const messageHeader = document.createElement('div');
    messageHeader.classList.add('message-header');

    const senderName = document.createElement('span');
    senderName.classList.add('sender-name');
    senderName.innerText = display_name;

    const timestampSpan = document.createElement('span');
    timestampSpan.classList.add('timestamp');
    timestampSpan.innerText = timestamp;

    messageHeader.appendChild(senderName);
    messageHeader.appendChild(timestampSpan);

    const messageContent = document.createElement('div');
    messageContent.classList.add('message-content');
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
    appendMessage(msg.display_name, msg.role, msg.timestamp, msg.content);
}

function initChatroomSocketClient(chatroomId) {
    // eslint-disable-next-line no-undef
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        switch (msg.type) {
            case 'chat_message':
                socketChatMessageHandler(msg);
            default:
                console.error(msg);
        }
    };
    window.socketClient.open(`chatroom_${chatroomId}`);
}

// eslint-disable-next-line no-unused-vars
function newChatroomForm() {
    const form = $('#create-chatroom-form');
    form.css('display', 'block');
    document.getElementById('chatroom-allow-anon').checked = true;
}

// eslint-disable-next-line no-unused-vars
function editChatroomForm(chatroom_id, baseUrl, title, description, allow_anon) {
    const form = $('#edit-chatroom-form');
    form.css('display', 'block');
    document.getElementById('chatroom-edit-form').action = `${baseUrl}/${chatroom_id}/edit`;
    document.getElementById('chatroom-title-input').value = title;
    document.getElementById('chatroom-description-input').value = description;
    document.getElementById('chatroom-anon-allow').checked = allow_anon;
}

// eslint-disable-next-line no-unused-vars
function deleteChatroomForm(chatroom_id, chatroom_name, base_url) {
    if (confirm(`This will delete chatroom '${chatroom_name}'. Are you sure?`)) {
        const url = `${base_url}/delete`;
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
            success: function (data) {
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
            error: function (err) {
                console.error(err);
                window.alert('Something went wrong. Please try again.');
            },
        });
    }
}

function toggle_chatroom(chatroomId, active) {
    const form = document.getElementById(`chatroom_toggle_form_${chatroomId}`);
    if (active && confirm('This will terminate this chatroom session. Are you sure?')) {
        form.submit();
    }
    else {
        form.submit();
    }
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
        // eslint-disable-next-line no-unused-vars
        const { chatroomId, userId, displayName, user_admin, isAnonymous } = pageData;

        showJoinMessage(`You have successfully joined as ${displayName}.`);

        initChatroomSocketClient(chatroomId);

        fetchMessages(chatroomId);

        const sendButton = document.querySelector('.send-message-btn');
        const messageInput = document.querySelector('.message-input');

        messageInput.addEventListener('keypress', (event) => {
            if (event.keyCode === 13 && !event.shiftKey) {
                event.preventDefault();
                sendButton.click();
            }
        });

        sendButton.addEventListener('click', (event) => {
            event.preventDefault();
            const messageContent = messageInput.value.trim();
            if (messageContent === '') {
                alert('Please enter a message.');
                return;
            }

            const role = user_admin ? 'instructor' : 'student';
            sendMessage(chatroomId, userId, displayName, role, messageContent);

            messageInput.value = '';
        });
    }
});
