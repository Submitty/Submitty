/* exported changeImage */
let currentImageIndex = 0;
let hiddenImages = undefined;
let seenImages = undefined;
let images = undefined;
let bannerElement = undefined;
let bubble  = undefined;
const currentImages = [];

$(() => {
    currentImageIndex = 0;
    hiddenImages = getHiddenImages();
    seenImages = [];
    images = document.getElementsByClassName('club-banners');
    bannerElement = document.getElementById('banner');
    bannerElement.style.display = 'none';
    bannerElement.style.width = '1%';



    for (let i = 0; i < images.length; i++) {
        const className = images[i].className.split(' ')[1];
        if (!hiddenImages.includes(className)) {
            currentImages.push(images[i]);
        }
        else {
            seenImages.push(images[i]);
        }
    }

    bubble = document.getElementById('speech-bubble-container');
    if (bubble !== null) {
        if (currentImages.length === 0) {

            bubble.style.display = 'none';
        }
        else {
            bubble.style.display = 'block';
        }
    }
    images = currentImages.concat(seenImages);


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
    if (bannerElement.style.display === 'none' && images.length > 0) {
        currentImageIndex = 0;
        Cookies.set('display-banner', 'yes');

        document.getElementById('breadcrumbs').style.flexWrap = 'inherit';

        if (currentImages.length > 0 && !noMove) {
            images[currentImageIndex].classList.add('active');

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
            images[currentImageIndex].classList.add('active');
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
        if (images.length > 0) {
            images[currentImageIndex].classList.remove('active');
        }


        bannerElement.style.width = '1%';
        bannerElement.style.display = 'none';
        movingUnit.style.left = '';
        movingUnit.style.right = '20%';

        if (currentImages.length >0) {
            const className = currentImages[currentImageIndex].className.split(' ')[1];
            hiddenImages.push(className);
            seenImages.push(currentImages[currentImageIndex]);
            currentImageIndex = seenImages.length; // the last currentImage we were at
            currentImages.shift();
            images = currentImages.concat(seenImages);

        }
        Cookies.set('hiddenImages', JSON.stringify(hiddenImages));
        if (currentImages.length > 0) {
            document.getElementById('triangle').style.display = 'block';
            document.getElementById('speech-bubble').style.display = 'block';
        }
        if (currentImages.length === 0) {

            bubble.style.display = 'none';
        }
        else {
            bubble.style.display = 'block';
        }


    }

}

function changeImage(n) {

    const originalIndex = currentImageIndex;

    if (currentImageIndex < 0 || currentImageIndex >= images.length) {
        console.log('Issue of index');
        return;
    }


    images[currentImageIndex].classList.remove('active');
    if (currentImageIndex < currentImages.length) {

        const className = currentImages[originalIndex].className.split(' ')[1];
        hiddenImages.push(className);
        seenImages.push(currentImages[originalIndex]);
        currentImageIndex = seenImages.length -1;
        currentImages.shift();
        images = currentImages.concat(seenImages);
        Cookies.set('hiddenImages', JSON.stringify(hiddenImages));

    }
    currentImageIndex += n;

    if (currentImageIndex < 0) {
        currentImageIndex = images.length - 1;
    }
    else if (currentImageIndex >= images.length) {
        currentImageIndex = 0;
    }

    images[currentImageIndex].classList.add('active');

}




function getHiddenImages() {
    const hiddenImagesCookie = Cookies.get('hiddenImages');
    return hiddenImagesCookie ? JSON.parse(hiddenImagesCookie) : [];
}


