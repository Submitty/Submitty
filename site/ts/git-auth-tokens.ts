function openCreateGitAuthTokenForm() {
    const form = document.getElementById('new-auth-token-form');
    if (form !== null) {
        form.style.display = 'block';
    }
}

function init() {
    const button = document.getElementById('new-auth-token-button');
    if (button !== null) {
        button.addEventListener('click', openCreateGitAuthTokenForm);
    }
}

document.addEventListener('DOMContentLoaded', () => init());
