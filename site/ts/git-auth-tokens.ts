function openCreateGitAuthTokenForm() {
    const form = document.getElementById('new-auth-token-form');
    if (form !== null) {
        form.style.display = 'block';
    }
}

function copyToken() {
    const icon = document.getElementById('token-copy-button');
    if (icon !== null) {
        const token = icon.dataset.val;
        navigator.clipboard.writeText(token || '');
    }
}

function init() {
    const button = document.getElementById('new-auth-token-button');
    if (button !== null) {
        button.addEventListener('click', openCreateGitAuthTokenForm);
    }
    const copyIcon = document.getElementById('token-copy-button');
    if (copyIcon !== null) {
        copyIcon.addEventListener('click', copyToken);
    }
}

document.addEventListener('DOMContentLoaded', () => init());
