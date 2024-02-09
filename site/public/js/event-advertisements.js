/* exported changeImage */

const eventAdvertisements = {
    currentImageIndex: 0,
    hiddenImages: undefined,
    seenImages: undefined,
    images: undefined,
    bannerElement: undefined,
    moveDuck: undefined,
    originalDuck: undefined,
    currentImages: [],
    chatBox: undefined,
};


$(() => {
    eventAdvertisements.chatBox = document.getElementById('chat-box');
    if (eventAdvertisements.chatBox !== null) {
        eventAdvertisements.chatBox.style.display = 'none';
    }

    if (document.getElementById('abanner') === null) {
        return;
    }

    eventAdvertisements.currentImageIndex = 0;
    eventAdvertisements.hiddenImages = getHiddenImages();
    eventAdvertisements.seenImages = [];
    eventAdvertisements.images = document.getElementsByClassName('club-banners');
    eventAdvertisements.bannerElement = document.getElementById('abanner');
    eventAdvertisements.bannerElement.style.display = 'none';
    eventAdvertisements.moveDuck = document.getElementById('duckdivmove');
    eventAdvertisements.moveDuck.style.display = 'none';
    eventAdvertisements.originalDuck = document.getElementById('duckdiv');


    for (let i = 0; i < eventAdvertisements.images.length; i++) {
        const className = eventAdvertisements.images[i].className.split(' ')[1];
        if (!eventAdvertisements.hiddenImages.includes(className)) {
            eventAdvertisements.currentImages.push(eventAdvertisements.images[i]);
        }
        else {
            eventAdvertisements.seenImages.push(eventAdvertisements.images[i]);
        }
    }

    eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);
    if (eventAdvertisements.currentImages.length > 0 && eventAdvertisements.chatBox !== null) {
        eventAdvertisements.chatBox.style.display = 'block';
    }

    if (localStorage.getItem('display-banner') !== null && localStorage.getItem('display-banner') === 'yes') {
        showBanners(true); // don't want to make duck move
    }
    else {
        localStorage.setItem('display-banner', 'no');
    }

    if (eventAdvertisements.images.length > 0) {
        eventAdvertisements.originalDuck.style.cursor = 'pointer';
    }
});
function showBanners(noMove = false) {
    const movingUnit = document.getElementById('moving-unit');
    const bannerElement = document.getElementById('abanner');
    if (eventAdvertisements.chatBox !== null) {
        eventAdvertisements.chatBox.style.display = 'none';
    }

    if (bannerElement.style.display === 'none' && eventAdvertisements.images.length > 0) {
        localStorage.setItem('display-banner', 'yes');

        eventAdvertisements.images[0].classList.add('active-banner');
        if (eventAdvertisements.currentImages.length > 0 && !noMove) {
            eventAdvertisements.moveDuck.style.animation = 'rocking 2s linear infinite';
            eventAdvertisements.originalDuck.style.display = 'none';

            setTimeout(() => {
                eventAdvertisements.moveDuck.style.display = 'block';
                bannerElement.style.display = 'block';
                movingUnit.style.animation = 'slide 2s linear forwards';
            }, 500);

        }
        else {
            eventAdvertisements.moveDuck.style.display = 'block';
            bannerElement.style.display = 'block';
            eventAdvertisements.images[0].classList.add('active-banner');
        }


    }
    else {

        localStorage.setItem('display-banner', 'no');

        //Css changes necessary
        eventAdvertisements.moveDuck.style.display = 'none';
        movingUnit.style.animation = 'unset';
        eventAdvertisements.moveDuck.style.animation = 'unset';
        eventAdvertisements.moveDuck.style.transform = 'rotate(0deg)';
        eventAdvertisements.originalDuck.style.display = 'block';


        if (eventAdvertisements.images.length > 0) {
            eventAdvertisements.images[0].classList.remove('active-banner');
        }
        else {
            return; //incase open and we just want to close
        }


        bannerElement.style.display = 'none';

        if (eventAdvertisements.currentImages.length >0) {
            const className = eventAdvertisements.currentImages[0].className.split(' ')[1];
            eventAdvertisements.hiddenImages.push(className);
            eventAdvertisements.seenImages.push(eventAdvertisements.currentImages[0]);
            eventAdvertisements.currentImages.shift();
            eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);
            if (eventAdvertisements.currentImages.length > 0) {
                eventAdvertisements.chatBox.style.display = "block";
            }

        }
        else {
            eventAdvertisements.images.push(eventAdvertisements.images[0]);
            eventAdvertisements.images.shift();
            eventAdvertisements.seenImages = eventAdvertisements.images;
        }

        Cookies.set('hiddenImages', JSON.stringify(eventAdvertisements.hiddenImages));
    }
    return;

}

function changeImage(n) {
    //IMPLEMENT FOR WHEN WE ARE GOING BACKWARDS
    eventAdvertisements.images[0].classList.remove('active-banner');
    if (n > 0) {
        if (eventAdvertisements.currentImages.length) {

            const className = eventAdvertisements.currentImages[0].className.split(' ')[1];
            eventAdvertisements.hiddenImages.push(className);
            eventAdvertisements.seenImages.push(eventAdvertisements.currentImages[0]);
            eventAdvertisements.currentImages.shift();
            eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);
            Cookies.set('hiddenImages', JSON.stringify(eventAdvertisements.hiddenImages));
        }
        else {
            eventAdvertisements.images.push(eventAdvertisements.images[0]);
            eventAdvertisements.images.shift();
            eventAdvertisements.seenImages = eventAdvertisements.images;
        }
    }
    else {
        if (eventAdvertisements.currentImages.length) {

            const className = eventAdvertisements.currentImages[0].className.split(' ')[1];
            eventAdvertisements.hiddenImages.push(className);
            eventAdvertisements.seenImages.push(eventAdvertisements.currentImages[eventAdvertisements.images.length -1]);
            eventAdvertisements.images.pop();
            eventAdvertisements.images = eventAdvertisements.currentImages.concat(eventAdvertisements.seenImages);
            Cookies.set('hiddenImages', JSON.stringify(eventAdvertisements.hiddenImages));
        }
        else {
            eventAdvertisements.images.push(eventAdvertisements.images[eventAdvertisements.images.length -1]);
            eventAdvertisements.images.pop();
            eventAdvertisements.seenImages = eventAdvertisements.images;
        }        
    }
    

        eventAdvertisements.images[0].classList.add('active-banner');
    return;
}

function getHiddenImages() {
    const hiddenImagesCookie = Cookies.get('hiddenImages');
    return hiddenImagesCookie ? JSON.parse(hiddenImagesCookie) : [];
}


