/*
imgElement  contains -> src, alt
*/



/* eslint no-undef: "off" */
function updateImageData(imageData) {
    const imgElement = document.getElementById('current-banner');
    if (imgElement) {
        //changing the image for the banner and the alt
        imgElement.src = `data:image/png;base64,${imageData.data}`;
        imgElement.alt = `${imageData.name}_${imageData.id}`;
    }
    else {
        console.error('Image element with id \'current-banner\' not found');
    }
}

function setupLocalStorage() {
    if (localStorage.getItem('open') = null) {
        localStorage.setItem('open', 'false');
    }
    if (localStorage.getItem('bannerArray') == null) {
        localStorage.setItem('bannerArray', JSON.stringify([]));
    }
    if (localStorage.getItem('removeArray') == null) {
        localStorage.setItem('removeArray', JSON.stringify([]));
    }
    if (localStorage.getItem('eventIndex') == null) {
        localStorage.setItem('eventIndex', 0);
    }
}


function duckTalking(base_url, duck_img, duckGif) {
    //this function gets updated once the duck image work is solidified
    return true;

}

function includesBanner(bannerArray, banner) {
    return bannerArray.some((item) => item.id === banner.id);
}

function filterRemovedBanners(localStorageKey, newArray) {
    let currentArray = JSON.parse(localStorage.getItem(localStorageKey)) || [];
    currentArray = currentArray.filter((banner) => includesBanner(newArray, banner));
    return currentArray;
}

function updateLocalStorage(imageDataArray) {

    setupLocalStorage(); //incase any of the localstorage isn't set up yet

    let bannerArray = filterRemovedBanners('bannerArray', imageDataArray);
    let removedArray = filterRemovedBanners('removedArray', imageDataArray);

    let updated = false;

    imageDataArray.forEach((item) => {
        if (!includesBanner(bannerArray, item) && !includesBanner(removedArray, item)) {
            bannerArray.unshift(item);
            updated = true;
        }
    });

    if (updated) {
        localStorage.setItem('eventIndex', 0);
        localStorage.setItem('duckTalking', 'true');
    }

    localStorage.setItem('bannerArray', JSON.stringify(bannerArray));
    localStorage.setItem('removedArray', JSON.stringify(bannerArray));

}


function initializeBanner(imageDataArray, base_url, duck_img, duckGif) {

    updateLocalStorage(imageDataArray); // remove deleted banners and add in newly created banners

    const openBanner = localStorage.getItem('open') !== 'false';
    const bannerArray = JSON.parse(localStorage.getItem(localStorageKey)) || [];


    const eventIndex = parseInt(localStorage.getItem('eventIndex'), 0);
    const eventsHolder = document.getElementById('event-holder');

    if (bannerArray.length > 0 && openBanner) {
        const imageData = bannerArray[eventIndex];
        updateImageData(imageData);
        if (eventsHolder) {
            eventsHolder.style.setProperty('display', 'block', 'important');
            localStorage.setItem('duckTalking', 'false');
        }
    }
    else if (bannerArray.length <= 0 || !openBanner) {
        localStorage.setItem('eventIndex', 0);
        if (eventsHolder) {
            eventsHolder.style.setProperty('display', 'none');
        }
    }

    duckTalking(base_url, duck_img, duckGif);
}

function inquireBanner(imageDataArray, base_url, duck_img, duckGif) {
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];
    const eventIndex = parseInt(localStorage.getItem('eventIndex'), 0);
    const imageData = bannerArray[eventIndex];

    if (imageData.extra_info === '' && imageData.link_name !== '') {
        window.open(imageData.link_name, '_blank');
    }
    else if (imageData.extra_info !== '') {
        displayBigBanner(imageData.extra_info, imageData.link_name);
    }
}

function closeBanner(imageDataArray, base_url, duck_img, duckGif) {
    localStorage.setItem('open', 'false');
    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}

function removeBanner(imageDataArray, base_url, duck_img, duckGif) {
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];
    const removedArray = JSON.parse(localStorage.getItem('removedArray')) || [];

    const eventIndex = parseInt(localStorage.getItem('eventIndex'), 0);

    if (eventIndex >= 0 && eventIndex < bannerArray.length) {
        removedArray.push(bannerArray[eventIndex]);
        bannerArray.splice(eventIndex, 1);

        localStorage.setItem('bannerArray', JSON.stringify(bannerArray));
        localStorage.setItem('removedArray', JSON.stringify(removedArray));
    }
    else {
        console.log('Invalid index for the banner.');
    }

    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}

function previousBanner(imageDataArray, base_url, duck_img, duckGif) {
    let eventIndex = parseInt(localStorage.getItem('eventIndex'), 0);
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];

    eventIndex = (eventIndex - 1 + bannerArray.length) % bannerArray.length;
    localStorage.setItem('eventIndex', eventIndex);

    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}

function nextBanner(imageDataArray, base_url, duck_img, duckGif) {
    let eventIndex = parseInt(localStorage.getItem('eventIndex'), 0);
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];

    eventIndex = (eventIndex + 1) % bannerArray.length;
    localStorage.setItem('eventIndex', eventIndex);

    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}