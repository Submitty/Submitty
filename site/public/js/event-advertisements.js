

let currentImageIndex = 0;
let hiddenImages = getHiddenImages();
let seenImages = [];
let images = $('.club-banners'); 
let bannerElement = $('#banner');
bannerElement.style.display = 'none';
bannerElement.style.width = "1%";



let currentImages = [];
for (let i = 0; i < images.length; i++) {
    let className = images[i].className.split(' ')[1];
    if (!hiddenImages.includes(className)) {
        currentImages.push(images[i]);
    }
    else {
        seenImages.push(images[i]);
    }
}

let bubble = document.getElementById("speech-bubble-container");
if (bubble != null) {
    if (currentImages.length == 0) {

        bubble.style.display = "none";
    }
    else {
        bubble.style.display = "block";
    }
}
images = currentImages.concat(seenImages);


if (getCookie("display-banner") === "yes") showBanners();

function showBanners() {

    let movingUnit = document.getElementById("moving-unit");
    let bannerElement = document.getElementById("banner");
    if (bannerElement.style.display === "none") {
        setCookie('display-banner', "yes");

        document.getElementById("breadcrumbs").style.flexWrap = "inherit";

        if (currentImages.length > 0) {
            images[0].classList.add("active");

            let duckdivElement = document.getElementById("moorthy-duck");
            let wholeBannerElement = document.getElementById("banner");
            duckdivElement.style.animation = "rocking 2s linear infinite";
            setTimeout(function() {
                bannerElement.style.width = "100%";
                bannerElement.style.display = "block";
                movingUnit.style.left = "10%";
                movingUnit.style.animation = "slide 2s linear forwards";
            }, 500);
            document.getElementById("triangle").style.display = "none";
            document.getElementById("speech-bubble").style.display = "none";
        

            bannerElement.addEventListener("animationend", function() {

            if (currentImages.length > 0 ) {
                document.getElementById("triangle").style.display = "block";
                document.getElementById("speech-bubble").style.display = "block";
            }

                        }, { once: true });
        }
        else {
            bannerElement.style.width = "100%";
            bannerElement.style.display = "block";
            images[currentImageIndex].classList.add("active");

        }
    }
    else { 

        setCookie('display-banner', "no");
        let duckdivElement = document.getElementById("moorthy-duck");
        movingUnit.style.animation = "none";
        duckdivElement.style.animation = "none";
        if (currentImages.length > 0) {
            document.getElementById("triangle").style.display = "block";
            document.getElementById("speech-bubble").style.display = "block";
        }
        else {
            currentImageIndex ++;
        }

        duckdivElement.style.transform = "rotate(0deg)";
        document.getElementById("breadcrumbs").style.flexWrap = "wrap";
        images[currentImageIndex].classList.remove("active");

        if (currentImageIndex < 0) {
            currentImageIndex = images.length - 1;
        } else if (currentImageIndex >= images.length) {
            currentImageIndex = 0;
        }
        
        bannerElement.style.width = "1%";
        bannerElement.style.display = "none";
        movingUnit.style.left = ""
        movingUnit.style.right = "20%";


        let className = currentImages[currentImageIndex].className.split(' ')[1]
        hiddenImages.push(className);
        seenImages.push(currentImages[currentImageIndex]);
        currentImages.shift();
        images = currentImages.concat(seenImages);
        setCookie('hiddenImages', JSON.stringify(hiddenImages)); 

    }

}

function changeImage(n) {

    let originalIndex = currentImageIndex;

    if (currentImageIndex < 0 || currentImageIndex >= images.length) {
        console.log("Issue of index");
        return;
    }


    images[currentImageIndex].classList.remove("active");
    if (currentImageIndex < currentImages.length) {
        console.log("index");
        console.log(currentImageIndex);
        let className = currentImages[originalIndex].className.split(' ')[1]
        hiddenImages.push(className);
        seenImages.push(currentImages[originalIndex]);
        currentImages.shift();
        images = currentImages.concat(seenImages);
        setCookie('hiddenImages', JSON.stringify(hiddenImages));  
        currentImageIndex --;

    }
    currentImageIndex += n;

    if (currentImageIndex < 0) {
        currentImageIndex = images.length - 1;
    } else if (currentImageIndex >= images.length) {
        currentImageIndex = 0;
    }

    images[currentImageIndex].classList.add("active");

}




function getHiddenImages() {
    let hiddenImagesCookie = getCookie('hiddenImages');
    return hiddenImagesCookie ? JSON.parse(hiddenImagesCookie) : [];
}

function setCookie(name, value) {
    let date = new Date();
    date.setFullYear(date.getFullYear() + 100); 
    document.cookie = name + '=' + (value || '') + '; path=/';
}

function getCookie(name) {
    let nameEQ = name + '=';
    let cookies = document.cookie.split(';');
    for (let i = 0; i < cookies.length; i++) {
        let cookie = cookies[i];
        while (cookie.charAt(0) === ' ') {
            cookie = cookie.substring(1, cookie.length);
        }
        if (cookie.indexOf(nameEQ) === 0) {
            return cookie.substring(nameEQ.length, cookie.length);
        }
    }
    return null;
}

