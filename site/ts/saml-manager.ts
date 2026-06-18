function openNewProxyUserForm() {
    const form = document.getElementById('new-proxy-user-form');
    if (form !== null) {
        form.style.display = 'block';
    }
}

function openProxyMappingForm() {
    const form = document.getElementById('new-proxy-mapping-form');
    if (form !== null) {
        form.style.display = 'block';
    }
}

function init() {
    const newProxyUserButton = document.getElementById('new-proxy-user-btn');
    if (newProxyUserButton !== null) {
        newProxyUserButton.addEventListener('click', openNewProxyUserForm);
    }
    const newProxyMappingButton = document.getElementById('new-proxy-mapping-btn');
    if (newProxyMappingButton !== null) {
        newProxyMappingButton.addEventListener('click', openProxyMappingForm);
    }
}

document.addEventListener('DOMContentLoaded', () => init());

export {};
