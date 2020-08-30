let swRegistration = null;
const applicationPublicKey = "BFIIEZA1CinQVgXoJd_wSupTtmf6y09BZYDfEM2-pkonjFJeRQHh0EDGKyICwWMbdwD3OBmpsVKK8yL_LpiUtLE";

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');

  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

//This code loads in a service worker if this page is a pwa
//checks if the browser supports serviceWorker
if ("serviceWorker" in navigator) {
  if (navigator.serviceWorker.controller) {
    console.log("[PWA] active service worker found, no need to register");
    navigator.serviceWorker.ready.then(reg => {
        swRegistration = reg;
    });
  } else {
    // Register the service worker
    navigator.serviceWorker
      .register("/serviceWorker.js", {
        scope: "/"
      })
      .then(function (reg) {
        swRegistration = reg;
        console.log("[PWA] Service worker has been registered for scope: " + swRegistration.scope);
        // Subscribing for the push notification
        swRegistration.pushManager.getSubscription().then(function(sub) {
          if (sub === null) {
            // Update UI to ask user to register for Push
            console.log('Not subscribed to push service!');
          } else {
            // We have a subscription, update the database
            console.log('Subscription object: ', sub);
          }
        });
      }).catch(function(err) {
        console.log('Service Worker registration failed: ', err);
      });
  }
}
//Install button script
let deferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  // Prevent Chrome 67 and earlier from automatically showing the prompt
  e.preventDefault();
  // Stash the event so it can be triggered later.
  deferredPrompt = e;
});

async function install() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(choiceResult){
      if (choiceResult.outcome === 'accepted') {
        console.log('Your PWA has been installed');
      } else {
        console.log('User chose to not install your PWA');
      }
      deferredPrompt = null;
    });
  }
}

function updatePushSubscription(enable) {
  if ('serviceWorker' in navigator && 'PushManager' in window) {
    console.log(enable);
    if (enable) {
      swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(applicationPublicKey)
      })
        .then(function (subscription) {
          console.log('User is subscribed.', subscription);

          updateSubscription(subscription);
          //
          // isSubscribed = true;
          //
          // updateBtn();
        })
        .catch(function (err) {
          if (Notification.permission === 'denied') {
            console.warn('Permission for notifications was denied');
          } else {
            console.error('Failed to subscribe the user: ', err);
          }
          // updateBtn();
        });
    }
    else {
      // Unsubscribe the Push service
      console.log("User is unsubscribed from Push service!      ");
    }
  }
  else {
    console.log("Push notification is not available :(");
  }
}
