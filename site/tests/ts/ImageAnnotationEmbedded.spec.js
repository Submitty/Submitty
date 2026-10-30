import '../../ts/ImageAnnotationEmbedded';

// jsdom has no ResizeObserver, so keep the callback and call it by hand
let resizeCallback;
global.ResizeObserver = class {
    constructor(callback) {
        resizeCallback = callback;
    }

    observe() {}
    disconnect() {}
};

// jsdom doesn't load images or lay them out
function loadImage(img) {
    Object.defineProperty(img, 'naturalWidth', { value: 800 });
    Object.defineProperty(img, 'offsetWidth', { value: 400 });
}

let img;

beforeEach(() => {
    localStorage.clear();
    resizeCallback = undefined;
    document.body.innerHTML = `
    <span id="file-zoom-display">100%</span>
    <div id="file-content">
        <div><img id="annotatable-image" style="width: 400px; height: 300px;"></div>
    </div>
    `;
    img = document.getElementById('annotatable-image');
});

test('zooming saves the zoom', () => {
    loadImage(img);
    window.zoomImage(0.2);
    expect(localStorage.getItem('image-zoom')).toEqual('1.2');
    expect(img.style.width).toEqual('480px');
    expect(document.getElementById('file-zoom-display').textContent).toEqual('120%');
});

test('a new image is zoomed to the saved zoom once it loads', () => {
    localStorage.setItem('image-zoom', '1.5');
    window.restoreImageZoom();

    // not loaded yet, so it can't be zoomed
    resizeCallback();
    expect(img.style.width).toEqual('400px');

    loadImage(img);
    resizeCallback();
    expect(img.style.width).toEqual('600px');
    expect(document.getElementById('file-zoom-display').textContent).toEqual('150%');
});

test('the saved zoom waits until the image has finished loading', () => {
    localStorage.setItem('image-zoom', '1.5');
    window.restoreImageZoom();

    // Firefox can know the size before the image is laid out at that size
    Object.defineProperty(img, 'complete', { value: false, configurable: true });
    loadImage(img);
    resizeCallback();
    expect(img.style.width).toEqual('400px');

    // the size might not change again once it has loaded
    Object.defineProperty(img, 'complete', { value: true });
    img.dispatchEvent(new Event('load'));
    expect(img.style.width).toEqual('600px');
});

test('nothing happens without a saved zoom', () => {
    window.restoreImageZoom();
    expect(resizeCallback).toBeUndefined();
    expect(img.style.width).toEqual('400px');
});
