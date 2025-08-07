/* global csrfToken, buildCourseUrl, displayErrorMessage, WebSocketClient, Twig */

/**
 * Asynchronously load the chatroom row template
 * @return {Promise}
 */
function loadChatroomTemplate(chatroomId) {
    return new Promise((resolve, reject) => {
        Twig.twig({
            id: 'ChatroomRow',
            href: '/templates/chat/ChatroomRow.twig',
            allowInlineIncludes: true,
            async: true,
            error: function () {
                reject();
            },
            load: function () {
                resolve();
            },
        });
    });
}

function renderChatroomRow(chatroomId, description, title, hostName, isAllowAnon, isAdmin, isActive, base_url) {
    return Twig.twig({ ref: 'ChatroomRow' }).render({
        id: chatroomId,
        description: description,
        title: title,
        hostName: hostName,
        isAllowAnon: isAllowAnon,
        isAdmin: isAdmin,
        isActive: isActive,
        baseUrl: base_url,
    });
}

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
    appendMessage(msg.display_name, msg.role, msg.timestamp, msg.content, msg.id);
}

function initChatroomSocketClient(chatroomId) {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        console.log('Received message from chatroom socket:', msg.type, msg);
        switch (msg.type) {
            case 'chat_message':
                socketChatMessageHandler(msg);
                break;
            case 'chat_close':
                window.alert('Chatroom has been closed by the instructor.');
                window.location.href = buildCourseUrl(['chat']);
                break;
            default:
                console.error(msg);
        }
    };
    window.socketClient.open(`chatroom_${chatroomId}`);
}

function initChatroomListSocketClient(user_admin, base_url) {
    window.chatroomListSocketClient = new WebSocketClient();
    window.chatroomListSocketClient.onmessage = (msg) => {
        console.log('Received message from chatroom socket:', msg.type, msg);
        switch (msg.type) {
            case 'chat_open':
                handleChatToggle(msg, user_admin, true, base_url);
                break;
            case 'chat_close': {
                handleChatToggle(msg, user_admin, false), base_url;
                break;
            }
            default:
                console.error(msg);
        }
    };
    window.chatroomListSocketClient.open('chatrooms');
}

function newChatroomForm() {
    const form = $('#create-chatroom-form');
    form.css('display', 'block');
    document.getElementById('chatroom-allow-anon').checked = true;
}

function editChatroomForm(chatroom_id, baseUrl, title, description, allow_anon) {
    const form = $('#edit-chatroom-form');
    form.css('display', 'block');
    document.getElementById('chatroom-edit-form').action = `${baseUrl}/${chatroom_id}/edit`;
    document.getElementById('chatroom-title-input').value = title;
    document.getElementById('chatroom-description-input').value = description;
    document.getElementById('chatroom-anon-allow').checked = allow_anon;
}

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

function toggleChatroom(chatroomId, active) {
    const form = document.getElementById(`chatroom_toggle_form_${chatroomId}`);
    if (!active || confirm('This will close the chatroom. Are you sure?')) {
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

function handleChatToggle(msg, user_admin, isOpening, base_url) {
    const tableBody = document.querySelector('#chatrooms-table tbody');
    if (!tableBody) {
        return;
    }
    const row = document.getElementById(`chatroom-row-${msg.id}`);
    if (row) {
        row.remove();
    }

    const rowHtml = renderChatroomRow(msg.id, msg.description, msg.title, msg.host_name, msg.allow_anon, user_admin, isOpening, base_url);
    // eslint-disable-next-line no-unsanitized/property
    // This should be safe because the Twig template escapes all passed variables.
    tableBody.insertAdjacentHTML('beforeend', rowHtml);
}

document.addEventListener('DOMContentLoaded', () => {
    const pageDataElement = document.getElementById('page-data');
    if (pageDataElement) {
        const pageData = JSON.parse(pageDataElement.textContent);
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
            sendMessage(chatroomId, userId, displayName, role, messageContent, isAnonymous);

            messageInput.value = '';
        });
    }
    const chatroomsTable = document.getElementById('chatrooms-table');
    const allChatroomData = document.getElementById('all-chatroom-data');
    if (chatroomsTable && allChatroomData) {
        loadChatroomTemplate();
        const pageData = JSON.parse(allChatroomData.textContent);
        const { user_admin, base_url } = pageData;
        initChatroomListSocketClient(user_admin, base_url);
    }
});
