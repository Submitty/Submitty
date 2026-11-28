import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const PDF_EMBEDDED_PATH = path.resolve(process.cwd(), 'public/js/pdf/PDFEmbedded.js');
const PDF_EMBEDDED_SOURCE = fs.readFileSync(PDF_EMBEDDED_PATH, 'utf8');

function loadPDFEmbedded() {
    // Run the classic browser script in a fresh sandbox bound to Jest's jsdom globals
    // so each test gets isolated module state without changing PDFEmbedded.js's script semantics.
    const wrapper = `(function () {\n${PDF_EMBEDDED_SOURCE}\nreturn { renderPDF, rescalePDF, RENDER_OPTIONS };\n})()`;
    return vm.runInNewContext(wrapper, {
        window,
        document,
        localStorage,
        $,
        pdfjsLib: global.pdfjsLib,
        csrfToken: global.csrfToken,
        setTimeout,
        clearTimeout,
        atob,
        alert: jest.fn(),
        console,
    }, { filename: PDF_EMBEDDED_PATH });
}

function parsePixelBase(value, fallback) {
    if (!value) {
        return fallback;
    }
    const match = /([0-9]+(?:\.[0-9]+)?)px/.exec(value);
    return match ? parseFloat(match[1]) : fallback;
}

function setupPDFEnvironment({
    numPages = 5,
    baseWidth = 800,
    baseHeight = 1000,
    pageGap = 10,
    viewportWidth = 800,
    viewportHeight = 600,
    renderGate = null,
} = {}) {
    document.body.replaceChildren();
    document.body.dataset.courseUrl = 'http://localhost/s20/sample';
    global.csrfToken = 'test-csrf';
    localStorage.clear();

    const submissionBrowser = document.createElement('div');
    submissionBrowser.id = 'submission_browser';

    const fileView = document.createElement('div');
    fileView.id = 'file-view';

    const zoomDisplay = document.createElement('span');
    zoomDisplay.id = 'file-zoom-display';

    const fileContent = document.createElement('div');
    fileContent.id = 'file-content';

    const viewer = document.createElement('div');
    viewer.id = 'viewer';
    viewer.className = 'pdfViewer';

    fileContent.appendChild(viewer);
    fileView.appendChild(zoomDisplay);
    fileView.appendChild(fileContent);
    submissionBrowser.appendChild(fileView);
    document.body.appendChild(submissionBrowser);

    // #submission_browser does not scroll when #file-view fills 100% height with overflow: hidden.
    Object.defineProperties(submissionBrowser, {
        clientWidth: { configurable: true, value: viewportWidth },
        clientHeight: { configurable: true, value: viewportHeight },
        scrollWidth: { configurable: true, value: viewportWidth },
        scrollHeight: { configurable: true, value: viewportHeight },
    });
    submissionBrowser.scrollLeft = 0;
    submissionBrowser.scrollTop = 0;

    function getCssScale() {
        const raw = viewer.style.getPropertyValue('--pdf-scale');
        const parsed = parseFloat(raw);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
    }

    function getPageMetrics(pageEl) {
        const cssScale = getCssScale();
        const rawWidth = parsePixelBase(pageEl.style.width, baseWidth);
        const rawHeight = parsePixelBase(pageEl.style.height, baseHeight);
        const usesCalc = pageEl.style.width.includes('calc(');
        return {
            offsetWidth: rawWidth,
            offsetHeight: rawHeight,
            visualWidth: usesCalc ? rawWidth * cssScale : rawWidth,
            visualHeight: usesCalc ? rawHeight * cssScale : rawHeight,
        };
    }

    function getPages() {
        return [...viewer.querySelectorAll('.page')];
    }

    function attachPageLayout(pageEl) {
        if (pageEl.dataset.layoutAttached === 'true') {
            return;
        }
        pageEl.dataset.layoutAttached = 'true';
        Object.defineProperties(pageEl, {
            offsetWidth: {
                configurable: true,
                get: () => getPageMetrics(pageEl).offsetWidth,
            },
            offsetHeight: {
                configurable: true,
                get: () => getPageMetrics(pageEl).offsetHeight,
            },
        });
        pageEl.getBoundingClientRect = () => {
            const pages = getPages();
            let topInDoc = 0;
            for (const p of pages) {
                if (p === pageEl) {
                    break;
                }
                topInDoc += getPageMetrics(p).visualHeight + pageGap;
            }
            const { visualWidth, visualHeight } = getPageMetrics(pageEl);
            const leftInDoc = Math.max(0, (viewportWidth - visualWidth) / 2);
            const top = topInDoc - fileContent.scrollTop;
            const left = leftInDoc - fileContent.scrollLeft;
            return {
                left,
                top,
                right: left + visualWidth,
                bottom: top + visualHeight,
                width: visualWidth,
                height: visualHeight,
            };
        };
    }

    Object.defineProperties(fileContent, {
        clientWidth: { configurable: true, value: viewportWidth },
        clientHeight: { configurable: true, value: viewportHeight },
        scrollWidth: {
            configurable: true,
            get: () => {
                const pages = getPages();
                if (!pages.length) {
                    return viewportWidth;
                }
                const maxPageWidth = Math.max(...pages.map((p) => getPageMetrics(p).visualWidth));
                return Math.max(viewportWidth, maxPageWidth);
            },
        },
        scrollHeight: {
            configurable: true,
            get: () => {
                const pages = getPages();
                if (!pages.length) {
                    return viewportHeight;
                }
                const totalHeight = pages.reduce((sum, p) => sum + getPageMetrics(p).visualHeight, 0)
                    + (pages.length - 1) * pageGap;
                return Math.max(viewportHeight, totalHeight);
            },
        },
    });
    fileContent.scrollLeft = 0;
    fileContent.scrollTop = 0;
    fileContent.getBoundingClientRect = () => ({
        left: 0,
        top: 0,
        right: viewportWidth,
        bottom: viewportHeight,
        width: viewportWidth,
        height: viewportHeight,
    });

    const origAppendChild = viewer.appendChild.bind(viewer);
    viewer.appendChild = (node) => {
        if (node && node.classList && node.classList.contains('page')) {
            attachPageLayout(node);
            const canvas = node.querySelector('canvas');
            if (canvas) {
                canvas.getContext = () => ({});
            }
        }
        return origAppendChild(node);
    };

    const mockPdf = {
        numPages,
        getPage: (pageNum) => Promise.resolve({
            pageNumber: pageNum,
            rotate: 0,
            getViewport: ({ scale }) => ({
                width: baseWidth * scale,
                height: baseHeight * scale,
            }),
            render: ({ viewport }) => ({
                promise: renderGate ? renderGate(pageNum, viewport.width / baseWidth) : Promise.resolve(),
            }),
        }),
    };

    global.pdfjsLib = {
        getDocument: () => ({
            promise: Promise.resolve(mockPdf),
        }),
    };

    jest.spyOn($, 'ajax').mockImplementation(({ success }) => {
        success(JSON.stringify({ status: 'success', data: btoa('fake-pdf') }));
    });

    const api = loadPDFEmbedded();
    return {
        api,
        submissionBrowser,
        fileContent,
        viewer,
        getPages,
        getCenteredPageInfo: () => {
            const centerY = viewportHeight / 2;
            const centerX = viewportWidth / 2;
            const page = getPages().find((p) => {
                const r = p.getBoundingClientRect();
                return centerY >= r.top && centerY <= r.bottom;
            });
            if (!page) {
                return null;
            }
            const r = page.getBoundingClientRect();
            return {
                pageNumber: Number(page.getAttribute('data-page-number')),
                relX: (centerX - r.left) / r.width,
                relY: (centerY - r.top) / r.height,
            };
        },
    };
}

async function flushPromises() {
    for (let i = 0; i < 6; i++) {
        await Promise.resolve();
    }
}

beforeEach(() => {
    jest.useFakeTimers();
});

afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
    delete global.pdfjsLib;
    delete global.csrfToken;
    delete window.triggerPDFScale;
});

test('keeps the current page centered when zooming in and out on a multi-page PDF', async () => {
    const { api, fileContent, getCenteredPageInfo } = setupPDFEnvironment();
    api.renderPDF('hw1', 'student1', 'grader1', 'upload.pdf', '/tmp/upload.pdf', 0);
    await flushPromises();

    // Scroll to the vertical midpoint of page 4 (index 3): 3 * (1000 + 10) + 500 - 300 = 3230
    fileContent.scrollTop = 3230;
    const before = getCenteredPageInfo();
    expect(before.pageNumber).toBe(4);
    expect(before.relY).toBeCloseTo(0.5, 5);
    expect(before.relX).toBeCloseTo(0.5, 5);

    // Zoom in from 100% to 200% via toolbar zoom trigger
    window.triggerPDFScale(1.0);
    jest.advanceTimersByTime(100);
    await flushPromises();

    const afterZoomIn = getCenteredPageInfo();
    expect(document.getElementById('file-zoom-display').textContent).toBe('200%');
    expect(afterZoomIn.pageNumber).toBe(4);
    expect(afterZoomIn.relY).toBeCloseTo(0.5, 5);
    expect(afterZoomIn.relX).toBeCloseTo(0.5, 5);

    // Zoom back out from 200% to 100%
    window.triggerPDFScale(-1.0);
    jest.advanceTimersByTime(100);
    await flushPromises();

    const afterZoomOut = getCenteredPageInfo();
    expect(document.getElementById('file-zoom-display').textContent).toBe('100%');
    expect(afterZoomOut.pageNumber).toBe(4);
    expect(afterZoomOut.relY).toBeCloseTo(0.5, 5);
    expect(afterZoomOut.relX).toBeCloseTo(0.5, 5);
});

test('anchors to the nearest page when the viewport center falls in the gap between pages', async () => {
    const { api, fileContent, getCenteredPageInfo } = setupPDFEnvironment();
    api.renderPDF('hw1', 'student1', 'grader1', 'upload.pdf', '/tmp/upload.pdf', 0);
    await flushPromises();

    // Page 3 ends at y = 3020, page 4 starts at y = 3030; put centerY=300 at doc y = 3028 (inside gap, closer to page 4)
    fileContent.scrollTop = 3028 - 300;
    expect(getCenteredPageInfo()).toBeNull();

    api.rescalePDF(1.5);
    await flushPromises();

    // Should remain right around the top edge of page 4 (not jump back toward page 1)
    const page4Rect = document.getElementById('pageContainer4').getBoundingClientRect();
    expect(Math.abs(page4Rect.top - 300)).toBeLessThan(20);
});

test('ignores stale async rescale completion when a newer zoom is triggered', async () => {
    const resolversByScale = new Map();
    const { api, fileContent, getCenteredPageInfo } = setupPDFEnvironment({
        renderGate: (_pageNum, scale) => new Promise((resolve) => {
            if (!resolversByScale.has(scale)) {
                resolversByScale.set(scale, []);
            }
            resolversByScale.get(scale).push(resolve);
        }),
    });

    api.renderPDF('hw1', 'student1', 'grader1', 'upload.pdf', '/tmp/upload.pdf', 0);
    await flushPromises();
    for (const resolve of resolversByScale.get(1) || []) {
        resolve();
    }
    await flushPromises();

    // Scroll to middle of page 4
    fileContent.scrollTop = 3230;

    // Start zoom 1 (1.5x), then before it resolves start zoom 2 (2.0x)
    api.rescalePDF(1.5);
    await flushPromises();
    api.rescalePDF(2.0);
    await flushPromises();

    // Newer zoom (2.0x) finishes first
    for (const resolve of resolversByScale.get(2) || []) {
        resolve();
    }
    await flushPromises();
    const scrollAfterLatest = fileContent.scrollTop;
    expect(getCenteredPageInfo().pageNumber).toBe(4);

    // Older zoom (1.5x) resolves late — must NOT overwrite scrollTop
    for (const resolve of resolversByScale.get(1.5) || []) {
        resolve();
    }
    await flushPromises();
    expect(fileContent.scrollTop).toBe(scrollAfterLatest);
});

test('scrolls #file-content on Ctrl+Wheel zoom and keeps scale synced after toolbar zoom', async () => {
    const { api, fileContent, submissionBrowser, getCenteredPageInfo } = setupPDFEnvironment();
    api.renderPDF('hw1', 'student1', 'grader1', 'upload.pdf', '/tmp/upload.pdf', 0);
    await flushPromises();

    fileContent.scrollTop = 3230;

    // First zoom via toolbar button to 150%
    api.rescalePDF(1.5);
    await flushPromises();
    expect(getCenteredPageInfo().pageNumber).toBe(4);

    const scrollBeforeWheel = fileContent.scrollTop;

    // Now Ctrl+Wheel zoom in at viewport center
    const wheelEvent = new WheelEvent('wheel', {
        deltaY: -40,
        clientX: 400,
        clientY: 300,
        ctrlKey: true,
        cancelable: true,
    });
    fileContent.dispatchEvent(wheelEvent);

    // #file-content should have been scrolled (not #submission_browser)
    expect(submissionBrowser.scrollTop).toBe(0);
    expect(fileContent.scrollTop).toBeGreaterThan(scrollBeforeWheel);

    // Scale should advance from 150% (not reset back to initial 100%)
    const displayedPercent = parseInt(document.getElementById('file-zoom-display').textContent, 10);
    expect(displayedPercent).toBeGreaterThan(150);

    // Debounced rescale finishes cleanly while staying on page 4
    jest.advanceTimersByTime(100);
    await flushPromises();
    expect(getCenteredPageInfo().pageNumber).toBe(4);
});

test('clamps scroll offsets at top of page 1 and skips restore if viewer disconnects mid-rescale', async () => {
    const pending = [];
    let defer = false;
    const { api, fileContent, viewer } = setupPDFEnvironment({
        renderGate: () => (defer ? new Promise((resolve) => pending.push(resolve)) : Promise.resolve()),
    });

    api.renderPDF('hw1', 'student1', 'grader1', 'upload.pdf', '/tmp/upload.pdf', 0);
    await flushPromises();

    // Zoom in at top of page 1, then zoom back out — scrollTop/scrollLeft must never be negative
    fileContent.scrollTop = 0;
    fileContent.scrollLeft = 0;
    api.rescalePDF(2.0);
    await flushPromises();
    api.rescalePDF(1.0);
    await flushPromises();
    expect(fileContent.scrollTop).toBeGreaterThanOrEqual(0);
    expect(fileContent.scrollLeft).toBeGreaterThanOrEqual(0);

    // Detach viewer while rescale is in flight — should exit cleanly without throwing
    defer = true;
    api.rescalePDF(1.5);
    await flushPromises();
    viewer.remove();
    for (const resolve of pending) {
        resolve();
    }
    await flushPromises();
    expect(viewer.isConnected).toBe(false);
});
