/* eslint no-undef: "off" */

const eventLS = {
    open: 'bannerOpen',
    bannerArr: 'bannerArray',
    removedArr: 'removedArray',
    index: 'eventIndex',
    duckTalk: 'duckTalking',
};

function updateImageData(imageData) {
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
    // incase the local storage for these variables are undefined
    if (localStorage.getItem(eventLS.open) === null) {
        localStorage.setItem(eventLS.open, 'false');
    }
    if (localStorage.getItem(eventLS.bannerArr) === null) {
        localStorage.setItem(eventLS.bannerArr, JSON.stringify([]));
    }
    if (localStorage.getItem(eventLS.removedArr) === null) {
        localStorage.setItem(eventLS.removedArr, JSON.stringify([]));
    }
    if (localStorage.getItem(eventLS.index) === null) {
        localStorage.setItem(eventLS.index, 0);
    }
}

function includesBanner(bannerArray, banner) {
    return bannerArray.some((item) => item.id === banner.id);
}

function filterRemovedBanners(localStorageKey, newArray) {
    // if the instructor removes arrays we don't want their data saved
    let currentArray = JSON.parse(localStorage.getItem(localStorageKey)) || [];
    currentArray = currentArray.filter((banner) => includesBanner(newArray, banner));
    return currentArray;
}

function updateLocalStorage(imageDataArray) {
    const bannerArray = filterRemovedBanners(eventLS.bannerArr, imageDataArray);
    const removedArray = filterRemovedBanners(eventLS.removedArr, imageDataArray);

    // here new banners are added to the front of bannerArray so they are displayed first
    let updated = false;

    imageDataArray.forEach((item) => {
        if (!includesBanner(bannerArray, item) && !includesBanner(removedArray, item)) {
            bannerArray.unshift(item);
            updated = true;
        }
    });

    // change index to 0 so that new events are shown first, and we want the ducktalk to be true to display the talking animation
    if (updated) {
        localStorage.setItem(eventLS.index, 0);
        localStorage.setItem(eventLS.duckTalk, 'true');
    }

    localStorage.setItem(eventLS.bannerArr, JSON.stringify(bannerArray));
    localStorage.setItem(eventLS.removedArr, JSON.stringify(removedArray));
}

// initiation script
function init() {
    setupLocalStorage();
    updateLocalStorage(imageDataArray);
}
$(init);
