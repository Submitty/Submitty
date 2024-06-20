let storedPrompt;
const pwaBtn = $('.addpwa');
const browser_lable = $('#browser-support');
const pwalable = $('#pwa-uninstall-lable');

pwaBtn[0].style.display = 'none';

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    storedPrompt = e;
    pwalable[0].style.display = 'none';
    pwaBtn[0].style.display = 'block';
    pwaBtn[0].addEventListener('click', () => {
        pwaBtn[0].style.display = 'none';

        storedPrompt.prompt();
        storedPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                pwalable[0].style.display = 'block';
            }
            storedPrompt = null;
        });
    });
});

function isInstalled() {
    if (navigator.userAgent.match(/chrome|chromium|crios/i)) {
        pwalable[0].style.display = 'block';
        browser_lable[0].style.display = 'none';
    }
    else {
        pwalable[0].style.display = 'none';
        browser_lable[0].style.display = 'block';
    }
}

isInstalled();
