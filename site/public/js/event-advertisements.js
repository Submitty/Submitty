/* eslint no-undef: 'off' */

const eventLS = {
    open: 'bannerOpen',
    bannerArr: 'bannerArray',
    removedArr: 'removedArray',
    index: 'eventIndex',
    duckTalk: 'duckTalking',
};

function parseStorage(key) {
    if (key === eventLS.open || key === eventLS.duckTalk) {
        return localStorage.getItem(key) === 'true';
    }
    else if (key === eventLS.bannerArr || key === eventLS.removedArr) {
        return JSON.parse(localStorage.getItem(key)) || [];
    }
    else if (key === eventLS.index) {
        return parseInt(localStorage.getItem(key), 0);
    }
    else {
        console.error('Invalid key passed to parseStorage:', key);
        return null;
    }
}

function setStorage(key, value) {
    if (key === eventLS.open || key === eventLS.duckTalk) {
        localStorage.setItem(key, value ? 'true' : 'false');
    }
    else if (key === eventLS.bannerArr || key === eventLS.removedArr) {
        localStorage.setItem(key, JSON.stringify(value));
    }
    else if (key === eventLS.index) {
        localStorage.setItem(key, value.toString());
    }
    else {
        console.error('Invalid key passed to setStorage:', key);
    }
}

function updateImageData() {
    const currEventIndex = parseStorage(eventLS.index);
    const bannerArray = parseStorage(eventLS.bannerArr);

    const imageData = bannerArray[currEventIndex];

    const imgElement = $('#current-banner');
    if (imgElement.length) {
        imgElement.attr('src', `data:image/png;base64,${imageData.data}`)
            .attr('alt', `${imageData.name}_${imageData.id}`);
    }
    else {
        console.error('Image element with id \'current-banner\' not found');
    }
}

function setupLocalStorage() {
    if (localStorage.getItem(eventLS.open) === null) {
        setStorage(eventLS.open, false);
    }
    if (localStorage.getItem(eventLS.bannerArr) === null) {
        setStorage(eventLS.bannerArr, []);
    }
    if (localStorage.getItem(eventLS.removedArr) === null) {
        setStorage(eventLS.removedArr, []);
    }
    if (localStorage.getItem(eventLS.index) === null) {
        setStorage(eventLS.index, 0);
    }
}

function includesBanner(bannerArray, banner) {
    return bannerArray.some((item) => item.id === banner.id);
}

function filterRemovedBanners(key, newArray) {
    let currentArray = parseStorage(key);
    currentArray = currentArray.filter((banner) => includesBanner(newArray, banner));
    return currentArray;
}

function updateLocalStorage(imageDataArray) {
    const bannerArray = filterRemovedBanners(eventLS.bannerArr, imageDataArray);
    const removedArray = filterRemovedBanners(eventLS.removedArr, imageDataArray);

    let updated = false;

    imageDataArray.forEach((item) => {
        if (!includesBanner(bannerArray, item) && !includesBanner(removedArray, item)) {
            bannerArray.unshift(item);
            updated = true;
        }
    });

    if (updated) {
        setStorage(eventLS.index, 0);
        setStorage(eventLS.duckTalk, true);
    }

    setStorage(eventLS.bannerArr, bannerArray);
    setStorage(eventLS.removedArr, removedArray);
}

// initiation script
function init() {
    setupLocalStorage();
    updateLocalStorage(imageDataArray);
}
$(init);

function previousBanner() {
    let currEventIndex = parseStorage(eventLS.index);
    const bannerArray = parseStorage(eventLS.bannerArr);

    currEventIndex = (currEventIndex - 1 + bannerArray.length) % bannerArray.length;
    setStorage(eventLS.index, currEventIndex);

    updateImageData();
}

function nextBanner() {
    let currEventIndex = parseStorage(eventLS.index);
    const bannerArray = parseStorage(eventLS.bannerArr);

    currEventIndex = (currEventIndex + 1) % bannerArray.length;
    setStorage(eventLS.index, currEventIndex);

    updateImageData();
}

function inquireBanner() {
    const currEventIndex = parseStorage(eventLS.index);
    const bannerArray = parseStorage(eventLS.bannerArr);
    const imageData = bannerArray[currEventIndex];

    if (imageData.extra_info === '' && imageData.link_name !== '') {
        window.open(imageData.link_name, '_blank');
    }
    else if (imageData.extra_info !== '') {
        displayBigBanner(imageData.extra_info, imageData.link_name);
    }
}

function hideBanner() {
    const eventsHolder = document.getElementById('event-holder');
    if (eventsHolder) {
        eventsHolder.style.setProperty('display', 'none');
    }
}

function closeBanner() {
    setStorage(eventLS.open, false);

    hideBanner();
    setStorage(eventLS.index, 0);
}

function removeBanner() {
    const bannerArray = parseStorage(eventLS.bannerArr);
    const removedArray = parseStorage(eventLS.removedArr);
    const currEventIndex = parseStorage(eventLS.index);

    if (currEventIndex >= 0 && currEventIndex < bannerArray.length) {
        removedArray.push(bannerArray[currEventIndex]);
        bannerArray.splice(currEventIndex, 1);

        setStorage(eventLS.bannerArr, bannerArray);
        setStorage(eventLS.removedArr, removedArray);
    }
    else {
        console.log('Invalid index for the banner.');
    }

    if (bannerArray.length > 0) {
        if (currEventIndex >= bannerArray.length) {
            setStorage(eventLS.index, 0);
        }
        nextBanner();
    }
    else {
        hideBanner();
        setStorage(eventLS.index, 0);
    }
}
