/* exported changeImage */

const eventAdvertisements = {
    currentImageIndex: 0,
    hiddenImages: undefined,
    seenImages: undefined,
    images: undefined,
    bannerElement: undefined,
    bubble: undefined,
    currentImages: []
}

$(() => {
    eventAdvertisements.currentImageIndex = 0;
    eventAdvertisements.hiddenImages = getHiddenImages();
    eventAdvertisements.seenImages = [];
    eventAdvertisements.images = document.getElementsByClassName('club-banners');
    eventAdvertisements.bannerElement = document.getElementById('banner');
    eventAdvertisements.bannerElement.style.display = 'none';
    eventAdvertisements.bannerElement.style.width = '1%';

    eventAdvertisements.bubble = document.getElementById('speech-bubble-container');
    if (eventAdvertisements.bubble !== null) {
        eventAdvertisements.bubble.style.display = 'none';
    }



    for (let i = 0; i < eventAdvertisements.images.length; i++) {
        const className = eventAdvertisements.images[i].className.split(' ')[1];
        if (!eventAdvertisements.hiddenImages.includes(className)) {
            eventAdvertisements.currentImages.push(eventAdvertisements.images[i]);
        }
        else {
            eventAdvertisements.seenImages.push(eventAdvertisements.images[i]);
        }
    }

    eventAdvertisements.bubble = document.getElementById('speech-bubble-container');
    if (eventAdvertisements.bubble !== null) {
        if (eventAdvertisements.currentImages.length > 0) {

            eventAdvertisements.bubble.style.display = 'block';
        }
    }
    eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);


    if (Cookies.get('display-banner') === 'yes') {
        showBanners(true); // don't want to make duck move
    }
    else {
        Cookies.set('display-banner', 'no');
    }
});
function showBanners(noMove = false) {

    const movingUnit = document.getElementById('moving-unit');
    const bannerElement = document.getElementById('banner');
    if (bannerElement.style.display === 'none' && eventAdvertisements.images.length > 0) {
        eventAdvertisements.currentImageIndex = 0;
        Cookies.set('display-banner', 'yes');

        $('#breadcrumbs').css('flex-wrap', 'inherit');

        if (eventAdvertisements.currentImages.length > 0 && !noMove) {
            eventAdvertisements.images[eventAdvertisements.currentImageIndex].classList.add('active');

            const duckdivElement = document.getElementById('moorthy-duck');
            duckdivElement.style.animation = 'rocking 2s linear infinite';

            setTimeout(() => {
                bannerElement.style.width = '100%';
                bannerElement.style.display = 'block';
                movingUnit.style.left = '10%';
                movingUnit.style.animation = 'slide 2s linear forwards';
            }, 500);

        }
        else {
            bannerElement.style.width = '100%';
            bannerElement.style.display = 'block';
            eventAdvertisements.images[eventAdvertisements.currentImageIndex].classList.add('active');
        }
        document.getElementById('triangle').style.display = 'none';
        document.getElementById('speech-bubble').style.display = 'none';
    }
    else {

        Cookies.set('display-banner', 'no');
        const duckdivElement = document.getElementById('moorthy-duck');
        movingUnit.style.animation = 'none';
        duckdivElement.style.animation = 'none';

        duckdivElement.style.transform = 'rotate(0deg)';
        document.getElementById('breadcrumbs').style.flexWrap = 'wrap';
        if (eventAdvertisements.images.length > 0) {
            eventAdvertisements.images[eventAdvertisements.currentImageIndex].classList.remove('active');
        }


        bannerElement.style.width = '1%';
        bannerElement.style.display = 'none';
        movingUnit.style.left = '';
        movingUnit.style.right = '20%';

        if (eventAdvertisements.currentImages.length >0) {
            const className = eventAdvertisements.currentImages[eventAdvertisements.currentImageIndex].className.split(' ')[1];
            eventAdvertisements.hiddenImages.push(className);
            eventAdvertisements.seenImages.push(eventAdvertisements.currentImages[eventAdvertisements.currentImageIndex]);
            eventAdvertisements.currentImageIndex = eventAdvertisements.seenImages.length; // the last currentImage we were at
            eventAdvertisements.currentImages.shift();
            eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);

        }
        Cookies.set('hiddenImages', JSON.stringify(eventAdvertisements.hiddenImages));
        if (eventAdvertisements.currentImages.length > 0) {
            document.getElementById('triangle').style.display = 'block';
            document.getElementById('speech-bubble').style.display = 'block';
        }
        if (eventAdvertisements.currentImages.length === 0) {

            eventAdvertisements.bubble.style.display = 'none';
        }
        else {
            eventAdvertisements.bubble.style.display = 'block';
        }


    }

}

function changeImage(n) {

    const originalIndex = eventAdvertisements.currentImageIndex;

    if (eventAdvertisements.currentImageIndex < 0 || eventAdvertisements.currentImageIndex >= eventAdvertisements.images.length) {
        console.log('Issue of index, you are out of range: ');
        console.log(eventAdvertisements.currentImageIndex)
        return;
    }


    eventAdvertisements.images[eventAdvertisements.currentImageIndex].classList.remove('active');
    if (eventAdvertisements.currentImageIndex < eventAdvertisements.currentImages.length) {

        const className = eventAdvertisements.currentImages[originalIndex].className.split(' ')[1];
        eventAdvertisements.hiddenImages.push(className);
        eventAdvertisements.seenImages.push(eventAdvertisements.currentImages[originalIndex]);
        eventAdvertisements.currentImageIndex = eventAdvertisements.seenImages.length -1;
        eventAdvertisements.currentImages.shift();
        eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);
        Cookies.set('hiddenImages', JSON.stringify(eventAdvertisements.hiddenImages));

    }
    eventAdvertisements.currentImageIndex += n;

    if (eventAdvertisements.currentImageIndex < 0) {
        eventAdvertisements.currentImageIndex = eventAdvertisements.images.length - 1;
    }
    else if (eventAdvertisements.currentImageIndex >= eventAdvertisements.images.length) {
        eventAdvertisements.currentImageIndex = 0;
    }

    eventAdvertisements.images[eventAdvertisements.currentImageIndex].classList.add('active');

}




function getHiddenImages() {
    const hiddenImagesCookie = Cookies.get('hiddenImages');
    return hiddenImagesCookie ? JSON.parse(hiddenImagesCookie) : [];
}

