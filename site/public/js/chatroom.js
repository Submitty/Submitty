/* global csrfToken, buildCourseUrl, displayErrorMessage, WebSocketClient */

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
    window.socketClient.open('chatrooms', {
        chatroom_id: chatroomId,
    });
}

function initChatroomListSocketClient() {
    window.chatroomListSocketClient = new WebSocketClient();
    window.chatroomListSocketClient.onmessage = (msg) => {
        console.log('Received message from chatroom socket:', msg.type, msg);
        switch (msg.type) {
            case 'chat_open':
                handleChatOpen(msg);
                break;
            case 'chat_close': {
                const row = document.getElementById(`${msg.id}`);
                if (row) {
                    row.remove();
                }
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

function toggle_chatroom(chatroomId, active) {
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

function handleChatOpen(msg) {
    const tableBody = document.querySelector('#chatrooms-table tbody');
    if (!tableBody) {
        return;
    }
    if (document.getElementById(`${msg.id}`)) {
        return;
    }
    const tr = document.createElement('tr');
    tr.id = `${msg.id}`;

    const tdTitle = document.createElement('td');
    const spanTitle = document.createElement('span');
    spanTitle.className = 'display-short';
    spanTitle.title = msg.title;
    spanTitle.textContent = msg.title.length > 30 ? `${msg.title.slice(0, 30)}...` : msg.title;
    tdTitle.appendChild(spanTitle);
    tr.appendChild(tdTitle);

    const tdHostName = document.createElement('td');
    tdHostName.textContent = msg.host_name;
    tr.appendChild(tdHostName);

    const tdDescription = document.createElement('td');
    const spanDescription = document.createElement('span');
    spanDescription.className = 'display-short';
    spanDescription.title = msg.description;
    spanDescription.textContent = msg.description.length > 45 ? `${msg.description.slice(0, 45)}...` : msg.description;
    tdDescription.appendChild(spanDescription);
    tr.appendChild(tdDescription);

    const tdLinks = document.createElement('td');
    const joinLink = document.createElement('a');
    joinLink.href = `${msg.base_url}/${msg.id}`;
    joinLink.className = 'btn btn-primary';
    joinLink.textContent = 'Join';
    tdLinks.appendChild(joinLink);

    if (msg.allow_anon) {
        tdLinks.appendChild(document.createTextNode(' '));
        const iTag = document.createElement('i');
        iTag.textContent = 'or';
        tdLinks.appendChild(iTag);
        tdLinks.appendChild(document.createTextNode(' '));

        const anonJoinLink = document.createElement('a');
        anonJoinLink.href = `${msg.base_url}/${msg.id}/anonymous`;
        anonJoinLink.className = 'btn btn-default';
        anonJoinLink.textContent = 'Join As Anon.';
        tdLinks.appendChild(anonJoinLink);
    }
    tr.appendChild(tdLinks);

    tableBody.appendChild(tr);
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
    if (chatroomsTable) {
        initChatroomListSocketClient();
    }
});
