/* eslint no-undef: "off" */
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
    if (localStorage.getItem('open') === null) {
        localStorage.setItem('open', 'false');
    }
    if (localStorage.getItem('bannerArray') === null) {
        localStorage.setItem('bannerArray', JSON.stringify([]));
    }
    if (localStorage.getItem('removedArray') === null) {
        localStorage.setItem('removedArray', JSON.stringify([]));
    }
    if (localStorage.getItem('eventIndex') === null) {
        localStorage.setItem('eventIndex', 0);
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
    const bannerArray = filterRemovedBanners('bannerArray', imageDataArray);
    const removedArray = filterRemovedBanners('removedArray', imageDataArray);

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

$(function() {
    setupLocalStorage();
    updateLocalStorage(imageDataArray);
});
