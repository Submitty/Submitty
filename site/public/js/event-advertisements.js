/* eslint no-undef: "off" */

const eventLS = {
    open: 'open',
    bArr: 'bannerArray',
    rArr: 'removedArray',
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
    if (localStorage.getItem(eventLS.open) === null) {
        localStorage.setItem(eventLS.open, 'false');
    }
    if (localStorage.getItem(eventLS.bArr) === null) {
        localStorage.setItem(eventLS.bArr, JSON.stringify([]));
    }
    if (localStorage.getItem(eventLS.rArr) === null) {
        localStorage.setItem(eventLS.rArr, JSON.stringify([]));
    }
    if (localStorage.getItem(eventLS.index) === null) {
        localStorage.setItem(eventLS.index, 0);
    }
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
    const bannerArray = filterRemovedBanners(eventLS.bArr, imageDataArray);
    const removedArray = filterRemovedBanners(eventLS.rArr, imageDataArray);

    let updated = false;

    imageDataArray.forEach((item) => {
        if (!includesBanner(bannerArray, item) && !includesBanner(removedArray, item)) {
            bannerArray.unshift(item);
            updated = true;
        }
    });

    if (updated) {
        localStorage.setItem(eventLS.index, 0);
        localStorage.setItem(eventLS.duckTalk, 'true');
    }

    localStorage.setItem(eventLS.bArr, JSON.stringify(bannerArray));
    localStorage.setItem(eventLS.rArr, JSON.stringify(removedArray));
}

function init() {
    setupLocalStorage();
    updateLocalStorage(imageDataArray);
}
$(init);
