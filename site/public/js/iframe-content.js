document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const theme = urlParams.get('theme');
    if (theme === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
    }
});
