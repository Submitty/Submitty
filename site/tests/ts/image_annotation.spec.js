import '../../ts/ImageAnnotationEmbedded';

function addImage({ width = 200, height = 100, loaded = true, panel = 'file-content' } = {}) {
    const container = document.getElementById(panel);
    const img = document.createElement('img');
    img.id = 'annotatable-image';
    img.style.width = `${width}px`;
    img.style.height = `${height}px`;
    Object.defineProperties(img, {
        naturalWidth: { configurable: true, value: loaded ? 1000 : 0 },
        naturalHeight: { configurable: true, value: loaded ? 500 : 0 },
        complete: { configurable: true, value: loaded },
    });
    container.replaceChildren(img);
    return img;
}

beforeEach(() => {
    window.cleanupAnnotationEditor();
    document.body.replaceChildren();
    for (const id of ['file-content', 'notebook-file-content', 'file-zoom-display', 'annotation-status']) {
        const element = document.createElement('div');
        element.id = id;
        document.body.appendChild(element);
    }
    localStorage.clear();
});

afterEach(() => {
    jest.restoreAllMocks();
});

test.each([null, '', 'invalid', '1.3junk', 'NaN', 'Infinity', '-Infinity', '0', '0.9', '5.1'])('defaults to 100%% for saved preference %s', (saved) => {
    if (saved !== null) {
        localStorage.setItem('image-scale', saved);
    }
    const img = addImage();
    window.initImageZoom();

    expect(img.dataset.zoom).toBe('1');
    expect(img.style.width).toBe('');
    expect(document.getElementById('file-zoom-display').textContent).toBe('100%');
});

test('restores cached images from their fitted dimensions, independently of PDF zoom', () => {
    localStorage.setItem('image-scale', '1.3');
    localStorage.setItem('pdf-scale', '2');
    const img = addImage();
    window.initImageZoom();

    expect(img.dataset.fitWidth).toBe('200px');
    expect(img.dataset.fitHeight).toBe('100px');
    expect(img.style.width).toBe('260px');
    expect(img.style.height).toBe('130px');
    expect(document.getElementById('file-zoom-display').textContent).toBe('130%');
    window.zoomImage(0.1);
    expect(localStorage.getItem('image-scale')).toBe('1.4');
    expect(localStorage.getItem('pdf-scale')).toBe('2');

    // Initializing the same image again must not measure its enlarged dimensions.
    window.initImageZoom();
    expect(img.dataset.fitWidth).toBe('200px');
    expect(img.style.width).toBe('280px');

    const nextImg = addImage({ width: 300, height: 400 });
    window.initImageZoom();
    expect(nextImg.dataset.fitWidth).toBe('300px');
    expect(nextImg.dataset.fitHeight).toBe('400px');
    expect(nextImg.style.width).toBe('420px');
    expect(nextImg.style.height).toBe('560px');
});

test('waits for an asynchronous image load before measuring and restoring zoom', () => {
    localStorage.setItem('image-scale', '1.3');
    const img = addImage({ loaded: false });
    window.initImageZoom();
    expect(img.dataset.fitWidth).toBeUndefined();
    expect(window.zoomImage(0.1)).toBe(true);
    expect(localStorage.getItem('image-scale')).toBe('1.3');

    Object.defineProperty(img, 'naturalWidth', { value: 1000 });
    img.style.width = '400px';
    img.dispatchEvent(new Event('load'));
    expect(img.dataset.fitWidth).toBe('400px');
    expect(img.style.width).toBe('520px');
    expect(document.getElementById('file-zoom-display').textContent).toBe('130%');
});

test('ignores a load event from an image that has been replaced', () => {
    const oldImg = addImage({ loaded: false });
    window.initImageZoom();
    const nextImg = addImage();
    Object.defineProperty(oldImg, 'naturalWidth', { value: 1000 });
    oldImg.dispatchEvent(new Event('load'));
    expect(nextImg.dataset.zoom).toBeUndefined();
    expect(nextImg.style.width).toBe('200px');
});

test('keeps the minimum, maximum, and ten percent button increments', () => {
    const img = addImage();
    window.initImageZoom();
    window.zoomImage(-0.1);
    expect(img.dataset.zoom).toBe('1');
    window.zoomImage(0.1);
    expect(img.dataset.zoom).toBe('1.1');
    expect(parseFloat(img.style.width)).toBeCloseTo(220);
    for (let i = 0; i < 40; i++) {
        window.zoomImage(0.1);
    }
    expect(img.dataset.zoom).toBe('5');
    expect(img.style.width).toBe('1000px');
    expect(localStorage.getItem('image-scale')).toBe('5');
});

test('keeps zoom controls usable when storage cannot be read or written', () => {
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
        throw new DOMException('Storage disabled', 'SecurityError');
    });
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new DOMException('Storage disabled', 'QuotaExceededError');
    });
    const img = addImage();
    window.initImageZoom();
    window.zoomImage(0.1);
    expect(img.dataset.zoom).toBe('1.1');
    expect(document.getElementById('file-zoom-display').textContent).toBe('110%');
});

test('does not change notebook images or unrelated viewers', () => {
    localStorage.setItem('image-scale', '1.3');
    const notebookImg = addImage({ panel: 'notebook-file-content' });
    window.initImageZoom();
    expect(window.zoomImage(0.1)).toBe(false);
    expect(notebookImg.style.width).toBe('200px');
    expect(notebookImg.dataset.zoom).toBeUndefined();
    expect(localStorage.getItem('image-scale')).toBe('1.3');
});

test('resizes the MarkerView target and retains zoom when cleaning up annotations', () => {
    localStorage.setItem('image-scale', '1.3');
    const img = addImage();
    window.initImageZoom();
    const markerView = document.createElement('div');
    markerView.id = 'annotation-marker-view';
    markerView.targetImage = img;
    img.dataset.originalId = img.id;
    img.replaceWith(markerView);

    window.zoomImage(0.1);
    expect(markerView.targetWidth).toBe(280);
    expect(markerView.targetHeight).toBe(140);
    expect(markerView.style.width).toBe('280px');
    expect(markerView.style.height).toBe('140px');
    expect(img.dataset.fitWidth).toBe('200px');

    window.cleanupAnnotationEditor();
    expect(document.getElementById('annotatable-image')).toBe(img);
    expect(img.style.width).toBe('280px');
    expect(img.style.height).toBe('140px');
    expect(localStorage.getItem('image-scale')).toBe('1.4');
});
