function fetchMessages(baseURL, chatroomId) {
    $.ajax({
        url: `${baseURL}/${chatroomId}/messages`,
        type: 'GET',
        dataType: 'json',
        success: function(responseData) {
            if (responseData.status === "success" && Array.isArray(responseData.data)) {
                responseData.data.forEach(msg => {
                    appendMessage(msg.user_id, msg.timestamp, msg.content);
                });
            }
        },
        error: function() {
            window.alert('Something went wrong with fetching messages');
        },
    });
}

function appendMessage(senderName, timestamp, content) {
    const messages_area = document.querySelector('.messages-area');
    const message = document.createElement('div');
    message.classList.add('message-container');
    message.innerHTML = `
        <div class="message-header">
            <i class="fa-solid fa-circle-user user-icon"></i>
            <span class="username">${senderName}</span>
            <span class="timestamp"> - ${timestamp}</span>
        </div>
        <div class="message-content">
            ${content}
        </div>
    `;
    messages_area.appendChild(message);
    messages_area.scrollTop = messages_area.scrollHeight;
}

function initChatroomSocketClient(chatroomId) {

}

function newChatroomForm() {
    let form = $("#create-chatroom-form");
    form.css("display", "block");
}

function editChatroomForm(chatroom_id, baseUrl) {
    let form= $("#edit-chatroom-form");
    form.css('display', 'block');
    document.getElementById('chatroom-edit-form').action = `${baseUrl}/${chatroom_id}/edit`;
}

document.addEventListener('DOMContentLoaded', () => {
    $('.popup-form').css('display', 'none');
    const pageDataElement = document.getElementById('page-data');
    if (pageDataElement) {
        const pageData = JSON.parse(pageDataElement.textContent);
        const { chatroomId, baseURL, userId } = pageData;

        const client = new WebSocketClient();
        client.onmessage = (msg) => {
            console.log(`Data received from server:`, msg);
        };
        client.onopen = () => {
          client.send({ type: "ping" });
        }
        client.open('some URL');

        fetchMessages(baseURL, chatroomId);

        const sendMsgButton = document.querySelector('.send-message-btn');
        const messageInput = document.querySelector('.message-input');

        sendMsgButton.addEventListener('click', function (e) {
            e.preventDefault();
            const messageContent = messageInput.value.trim();
            if (messageContent === '') {
                alert('Please enter a message.');
                return;
            }
            messageInput.value = '';
        });
    }
});