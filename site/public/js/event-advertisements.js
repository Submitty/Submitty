/* eslint no-undef: "off" */
function updateImageData(imageData) {
    const imgElement = document.getElementById('current-banner');
    if (imgElement) {
        imgElement.src = `data:image/png;base64,${imageData.data}`;
        imgElement.alt = `${imageData.name}_${imageData.id}`;
    }
    else {
        console.error('Image element with id \'current-banner\' not found');
    }
}

function duckTalking(base_url, duck_img, duckGif) {
    const imageElement = document.querySelector('#moorthy-duck');
    if (!imageElement) {
        return;
    }

    const bannerArray = localStorage.getItem('bannerArray');

    if (bannerArray && JSON.parse(bannerArray).length > 0 && localStorage.getItem('open') !== 'true' && localStorage.getItem('duckTalking') === 'true') {
        imageElement.src = `data:image/gif;base64,${duckGif}`;
    }
    else {
        imageElement.src = `${base_url}/img/${duck_img}`;
    }
}

function includesBanner(bannerArray, banner) {
    return bannerArray.some((item) => item.id === banner.id);
}

function initializeBanner(imageDataArray, base_url, duck_img, duckGif) {
    if (localStorage.getItem('open') !== 'true' && localStorage.getItem('open') !== 'false') {
        localStorage.setItem('open', 'false');
    }

    let bannerArray = JSON.parse(localStorage.getItem('bannerArray'));
    const openBanner = localStorage.getItem('open') !== 'false';

    if (!bannerArray) {
        localStorage.setItem('bannerArray', JSON.stringify([]));
        bannerArray = [];
        localStorage.setItem('removedArray', JSON.stringify([]));
        localStorage.setItem('currEventIndex', 0);
        localStorage.setItem('open', 'false');
    }

    let removedArray = JSON.parse(localStorage.getItem('removedArray')) || [];

    bannerArray = bannerArray.filter((item) => imageDataArray.some((img) => img.id === item.id));
    removedArray = removedArray.filter((item) => imageDataArray.some((img) => img.id === item.id));
    localStorage.setItem('bannerArray', JSON.stringify(bannerArray));
    localStorage.setItem('removedArray', JSON.stringify(removedArray));

    let updated = false;
    imageDataArray.forEach((item) => {
        if (!includesBanner(bannerArray, item) && !includesBanner(removedArray, item)) {
            bannerArray.unshift(item);
            updated = true;
        }
    });

    if (updated) {
        localStorage.setItem('bannerArray', JSON.stringify(bannerArray));
        localStorage.setItem('currEventIndex', 0);
        localStorage.setItem('duckTalking', 'true');
    }

    bannerArray = JSON.parse(localStorage.getItem('bannerArray'));
    removedArray = JSON.parse(localStorage.getItem('removedArray'));
    const currEventIndex = parseInt(localStorage.getItem('currEventIndex'), 0);
    const eventsHolder = document.getElementById('event-holder');

    if (bannerArray.length > 0 && openBanner) {
        const imageData = bannerArray[currEventIndex];
        updateImageData(imageData);
        if (eventsHolder) {
            eventsHolder.style.setProperty('display', 'block', 'important');
            localStorage.setItem('duckTalking', 'false');
        }
    }
    else if (bannerArray.length <= 0 || !openBanner) {
        localStorage.setItem('currEventIndex', 0);
        if (eventsHolder) {
            eventsHolder.style.setProperty('display', 'none');
        }
    }

    duckTalking(base_url, duck_img, duckGif);
}

function inquireBanner(imageDataArray, base_url, duck_img, duckGif) {
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];
    const currEventIndex = parseInt(localStorage.getItem('currEventIndex'), 0);
    const imageData = bannerArray[currEventIndex];

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

    const currEventIndex = parseInt(localStorage.getItem('currEventIndex'), 0);

    if (currEventIndex >= 0 && currEventIndex < bannerArray.length) {
        removedArray.push(bannerArray[currEventIndex]);
        bannerArray.splice(currEventIndex, 1);

        localStorage.setItem('bannerArray', JSON.stringify(bannerArray));
        localStorage.setItem('removedArray', JSON.stringify(removedArray));
    }
    else {
        console.log('Invalid index for the banner.');
    }

    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}

function previousBanner(imageDataArray, base_url, duck_img, duckGif) {
    let currEventIndex = parseInt(localStorage.getItem('currEventIndex'), 0);
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];

    currEventIndex = (currEventIndex - 1 + bannerArray.length) % bannerArray.length;
    localStorage.setItem('currEventIndex', currEventIndex);

    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}

function nextBanner(imageDataArray, base_url, duck_img, duckGif) {
    let currEventIndex = parseInt(localStorage.getItem('currEventIndex'), 0);
    const bannerArray = JSON.parse(localStorage.getItem('bannerArray')) || [];

    currEventIndex = (currEventIndex + 1) % bannerArray.length;
    localStorage.setItem('currEventIndex', currEventIndex);

    initializeBanner(imageDataArray, base_url, duck_img, duckGif);
}