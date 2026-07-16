/* global pdfjsLib, csrfToken, jspdf */
/* exported render_student, download_student */

window.RENDER_OPTIONS = {
    documentId: '',
    userId: '',
    pdfDocument: null,
    scale: parseFloat(localStorage.getItem('pdf-scale')) || 1,
    rotate: parseInt(localStorage.getItem('pdf-rotate')) || 0,
    studentPopup: false,
    minScale: 1,
    maxScale: 5,
};

window.GENERAL_INFORMATION = {
    grader_id: '',
    user_id: '',
    gradeable_id: '',
    file_name: '',
    broken: false,
};

function createPage(pageNumber) {
    const PAGE_TEMPLATE = `
  <div style="visibility: hidden;" class="page" data-loaded="false">
    <div class="canvasWrapper">
      <canvas></canvas>
    </div>
  </div>
`;
    const temp = document.createElement('div');

    temp.innerHTML = PAGE_TEMPLATE;

    const page = temp.children[0];
    const canvas = page.querySelector('canvas');

    page.setAttribute('id', `pageContainer${pageNumber}`);
    page.setAttribute('data-page-number', pageNumber);

    canvas.mozOpaque = true;
    canvas.setAttribute('id', `page${pageNumber}`);

    return page;
}

function scalePage(pageNumber, viewport, context) {
    const page = document.getElementById(`pageContainer${pageNumber}`);
    const canvas = page.querySelector('.canvasWrapper canvas');
    const outputScale = getOutputScale(context);
    const transform = !outputScale.scaled ? null : [outputScale.sx, 0, 0, outputScale.sy, 0, 0];
    const sfx = approximateFraction(outputScale.sx);
    const sfy = approximateFraction(outputScale.sy);

    // Adjust width/height for scale
    page.style.visibility = '';
    canvas.width = roundToDivide(viewport.width * outputScale.sx, sfx[0]);
    canvas.height = roundToDivide(viewport.height * outputScale.sy, sfy[0]);
    canvas.style.width = `${roundToDivide(viewport.width, sfx[1])}px`;
    canvas.style.height = `${roundToDivide(viewport.height, sfx[1])}px`;
    page.style.width = `${viewport.width}px`;
    page.style.height = `${viewport.height}px`;

    return transform;
}

/**
 * Approximates a float number as a fraction using Farey sequence (max order of 8).
 *
 * @param {Number} x Positive float number
 * @return {Array} Estimated fraction: the first array item is a numerator,
 *                 the second one is a denominator.
 */
function approximateFraction(x) {
    // Fast path for int numbers or their inversions.
    if (Math.floor(x) === x) {
        return [x, 1];
    }

    const xinv = 1 / x;
    const limit = 8;
    if (xinv > limit) {
        return [1, limit];
    }
    else if (Math.floor(xinv) === xinv) {
        return [1, xinv];
    }

    const x_ = x > 1 ? xinv : x;

    // a/b and c/d are neighbours in Farey sequence.
    let a = 0;
    let b = 1;
    let c = 1;
    let d = 1;

    // Limit search to order 8.
    while (true) {
    // Generating next term in sequence (order of q).
        const p = a + c;
        const q = b + d;
        if (q > limit) {
            break;
        }
        if (x_ <= p / q) {
            c = p;
            d = q;
        }
        else {
            a = p;
            b = q;
        }
    }

    // Select closest of neighbours to x.
    if (x_ - a / b < c / d - x_) {
        return x_ === x ? [a, b] : [b, a];
    }
    else {
        return x_ === x ? [c, d] : [d, c];
    }
}

function getOutputScale(ctx) {
    const devicePixelRatio = window.devicePixelRatio || 1;
    const backingStoreRatio = ctx.webkitBackingStorePixelRatio
        || ctx.mozBackingStorePixelRatio
        || ctx.msBackingStorePixelRatio
        || ctx.oBackingStorePixelRatio
        || ctx.backingStorePixelRatio || 1;
    const pixelRatio = devicePixelRatio / backingStoreRatio;
    return {
        sx: pixelRatio,
        sy: pixelRatio,
        scaled: pixelRatio !== 1,
    };
}

function roundToDivide(x, div) {
    const r = x % div;
    return r === 0 ? x : Math.round(x - r + div);
}

function renderPage(pageNumber, renderOptions) {
    const {
        documentId,
        pdfDocument,
        scale,
        rotate,
    } = renderOptions;

    // Load the page
    return pdfDocument.getPage(pageNumber).then((pdfPage) => {
        const page = document.getElementById(`pageContainer${pageNumber}`);
        const canvas = page.querySelector('.canvasWrapper canvas');
        const canvasContext = canvas.getContext('2d', { alpha: false });
        const totalRotation = (rotate + pdfPage.rotate) % 360;
        const viewport = pdfPage.getViewport({ scale: scale, rotation: totalRotation });
        const transform = scalePage(pageNumber, viewport, canvasContext);

        // Render the page
        return pdfPage.render({ canvasContext, viewport, transform }).promise.then(() => {
            // Indicate that the page was loaded
            page.setAttribute('data-loaded', 'true');

            return pdfPage;
        });
    });
}

function buildCourseUrl(parts = []) {
    return `${document.body.dataset.courseUrl}/${parts.join('/')}`;
}

// For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
// as a parameter.
// function render_student(gradeable_id, user_id, file_name, file_path, pdf_url) {
//     // set the values for default view through submission page
//     window.RENDER_OPTIONS.scale = 1;
//     window.RENDER_OPTIONS.rotate = 0;
//     window.RENDER_OPTIONS.studentPopup = true;
//     render(gradeable_id, user_id, '', file_name, file_path, 1, pdf_url);
// }

// For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
// as a parameter.
// function download_student(gradeable_id, user_id, file_name, file_path, pdf_url, rerender_pdf) {
//     // set the values for default view through submission page
//     window.RENDER_OPTIONS.scale = 1;
//     window.RENDER_OPTIONS.rotate = 0;
//     window.RENDER_OPTIONS.studentPopup = true;
//     download(gradeable_id, user_id, '', file_name, file_path, 1, pdf_url, rerender_pdf);
// }

// eslint-disable-next-line default-param-last
function download(gradeable_id, user_id, grader_id, file_name, file_path, page_num, url = '', rerender_pdf) {
    window.GENERAL_INFORMATION = {
        grader_id: grader_id,
        user_id: user_id,
        gradeable_id: gradeable_id,
        file_name: file_name,
        file_path: file_path,
    };
    // TODO: Replace this with rerender_pdf, only rerender if rerender_pdf is set to true
    // eslint-disable-next-line no-constant-condition
    if (true) {
        window.RENDER_OPTIONS.documentId = file_name;
        // TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
        window.RENDER_OPTIONS.userId = grader_id;
        if (url === '') {
            url = buildCourseUrl(['gradeable', gradeable_id, 'encode_pdf']);
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                user_id: user_id,
                filename: file_name,
                file_path: file_path,
                csrf_token: csrfToken,
            },
            success: (data) => {
                let pdfData;
                try {
                    pdfData = JSON.parse(data)['data'];
                    pdfData = atob(pdfData);
                }
                catch (err) {
                    console.log(err);
                    console.log(data);
                    alert('Something went wrong, please try again later.');
                }
                const pdfJsBaseUrl = `${window.location.origin}/vendor/pdfjs/`;
                pdfjsLib.getDocument({
                    data: pdfData,
                    cMapUrl: `${pdfJsBaseUrl}cmaps/`,
                    cMapPacked: true,
                    wasmUrl: `${pdfJsBaseUrl}wasm/`,
                    iccUrl: `${pdfJsBaseUrl}iccs/`,
                    standardFontDataUrl: `${pdfJsBaseUrl}standard_fonts/`,
                }).promise.then((pdf) => {
                    const doc = new jspdf.jsPDF('p', 'mm');
                    renderPageForDownload(pdf, doc, 1, pdf.numPages + 1, file_name);
                });
            },
        });
    }
    else {
        const anchor = document.createElement('a');
        anchor.setAttribute('href', 'file_path');
        anchor.setAttribute('download', file_path);
        document.body.appendChild(anchor);
        anchor.click();
        anchor.parentNode.removeChild(anchor);
    }
}
function renderPageForDownload(pdf, doc, num, targetNum, file_name) {
    const scale = 3; // Define the scale factor here
    if (num < targetNum) {
        if (num !== 1) {
            doc.addPage();
        }
        pdf.getPage(num).then((page) => {
            const viewport = page.getViewport({ scale: scale });
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport,
            };

            page.render(renderContext).promise.then(() => {
                const imgData = canvas.toDataURL('image/jpeg', 0.98);
                const width = doc.internal.pageSize.getWidth();
                const height = doc.internal.pageSize.getHeight();
                doc.addImage(imgData, 'JPEG', 0, 0, width, height);
                renderPageForDownload(pdf, doc, num + 1, targetNum, file_name);
            });
        });
    }
    else {
        doc.save(file_name);
    }
}

function render(gradeable_id, user_id, grader_id, file_name, file_path, page_num, url = '') {
    try {
        let currentTool;
        let NUM_PAGES = 0;

        Object.assign(window.GENERAL_INFORMATION, {
            grader_id: grader_id,
            user_id: user_id,
            gradeable_id: gradeable_id,
            file_name: file_name,
            file_path: file_path,
        });

        window.RENDER_OPTIONS.documentId = file_name;
        // TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
        window.RENDER_OPTIONS.userId = grader_id;
        if (url === '') {
            url = buildCourseUrl(['gradeable', gradeable_id, 'encode_pdf']);
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                user_id: user_id,
                filename: file_name,
                file_path: file_path,
                csrf_token: csrfToken,
            },
            success: (data) => {
                // documentId = file_name;

                let pdfData;
                try {
                    pdfData = JSON.parse(data);
                    // Checking if the response is a failure due to large file size
                    if (pdfData.status === 'fail') {
                        $('#pdf-error-message').text(pdfData.message).show();
                        return;
                    }
                    pdfData = atob(pdfData['data']);
                }
                catch (err) {
                    console.log(err);
                    console.log(data);
                    alert('Something went wrong, please try again later.');
                }
                const pdfJsBaseUrl = `${window.location.origin}/vendor/pdfjs/`;
                pdfjsLib.getDocument({
                    data: pdfData,
                    cMapUrl: `${pdfJsBaseUrl}cmaps/`,
                    cMapPacked: true,
                    wasmUrl: `${pdfJsBaseUrl}wasm/`,
                    iccUrl: `${pdfJsBaseUrl}iccs/`,
                    standardFontDataUrl: `${pdfJsBaseUrl}standard_fonts/`,
                }).promise.then((pdf) => {
                    window.RENDER_OPTIONS.pdfDocument = pdf;
                    if (window.GENERAL_INFORMATION.broken) {
                        return;
                    }
                    const viewer = document.getElementById('viewer');
                    $(viewer).on('touchstart touchmove', (e) => {
                        // Let touchscreen work
                        if (currentTool === 'pen' || currentTool === 'text') {
                            e.preventDefault();
                        }
                    });
                    $('a[value=\'zoomcustom\']').text(`${parseInt(window.RENDER_OPTIONS.scale * 100)}%`);
                    viewer.innerHTML = '';
                    NUM_PAGES = pdf.numPages;

                    const renderPagePromises = [];
                    for (let i = 0; i < NUM_PAGES; i++) {
                        const page = createPage(i + 1);
                        viewer.appendChild(page);
                        const page_id = i + 1;
                        renderPagePromises.push(renderPage(page_id, window.RENDER_OPTIONS).then(() => {
                            // eslint-disable-next-line eqeqeq
                            if (i == page_num) {
                                // scroll to page on load
                                const initialPage = $(`#pageContainer${page_id}`);
                                if (initialPage.length) {
                                    const scrollContainer = $('#file-content').length ? $('#file-content') : $('#submission_browser');
                                    scrollContainer.scrollTop(Math.max(page.offsetTop, 0));
                                }
                            }
                        }));
                    }

                    Promise.all(renderPagePromises).then(() => {
                        $('.pdfViewer .page').each(function () {
                            $(this).css('width', `calc(${$(this).css('width')} * var(--pdf-scale))`);
                            $(this).css('height', `calc(${$(this).css('height')} * var(--pdf-scale))`);
                        });

                        let scale = window.RENDER_OPTIONS.scale;
                        let zoomTimeout = null;

                        $('#file-zoom-display').text(`${Math.round(scale * 100)}%`);

                        function handleWheel(e) {
                            if (!e.ctrlKey && !e.metaKey) {
                                return;
                            }
                            e.preventDefault();

                            const k = 0.0065;
                            const factor = Math.exp(-k * e.deltaY);
                            const newScale = Math.min(window.RENDER_OPTIONS.maxScale, Math.max(window.RENDER_OPTIONS.minScale, scale * factor));

                            const viewer = $('#viewer');
                            const scroller = $('#submission_browser');
                            let page = $('#viewer > .page:hover');

                            if (!page.length) {
                                const pages = [...$('#viewer > .page')];
                                for (const p of pages) {
                                    const bounds = p.getBoundingClientRect();
                                    if (e.clientY > bounds.top && e.clientY < bounds.bottom) {
                                        page = $(p);
                                        break;
                                    }
                                }
                            }

                            if (page.length) {
                                const pageBounds = page[0].getBoundingClientRect();
                                viewer.css('--pdf-scale', newScale / window.RENDER_OPTIONS.scale);
                                const newPageBounds = page[0].getBoundingClientRect();

                                const xoff = (e.clientX - pageBounds.left) / pageBounds.width;
                                const yoff = (e.clientY - pageBounds.top) / pageBounds.height;

                                const newXoff = (e.clientX - newPageBounds.left) / newPageBounds.width;
                                const newYoff = (e.clientY - newPageBounds.top) / newPageBounds.height;

                                scroller[0].scrollLeft -= (newXoff - xoff) * newPageBounds.width;
                                scroller[0].scrollTop -= (newYoff - yoff) * newPageBounds.height;
                            }
                            else {
                                viewer.css('--pdf-scale', newScale / window.RENDER_OPTIONS.scale);
                            }

                            scale = newScale;
                            $('#file-zoom-display').text(`${Math.round(scale * 100)}%`);

                            clearTimeout(zoomTimeout);
                            zoomTimeout = setTimeout(rescale, 100);
                        }

                        function rescale() {
                            rescalePDF(scale);
                        }

                        $('#file-content')[0].removeEventListener('wheel', handleWheel);
                        $('#file-content')[0].addEventListener('wheel', handleWheel, { passive: false });
                    });
                });
            },
        });
    }
    catch (e) {
        // ignore the identifier error
    }
}

function rescalePDF(scale) {
    if (scale < window.RENDER_OPTIONS.minScale || scale > window.RENDER_OPTIONS.maxScale) {
        return;
    }

    const viewer = $('#viewer');
    window.RENDER_OPTIONS.scale = scale;
    localStorage.setItem('pdf-scale', scale);
    const pdf = window.RENDER_OPTIONS.pdfDocument;
    const NUM_PAGES = pdf.numPages;
    const renderPagePromises = [];
    for (let i = 1; i <= NUM_PAGES; i++) {
        renderPagePromises.push(renderPage(i, window.RENDER_OPTIONS));
    }
    viewer.css('--pdf-scale', 1);
    $('#file-zoom-display').text(`${Math.round(scale * 100)}%`);

    Promise.all(renderPagePromises).then(() => {
        $('.pdfViewer .page').each(function () {
            $(this).css('width', `calc(${this.offsetWidth}px * var(--pdf-scale))`);
            $(this).css('height', `calc(${this.offsetHeight}px * var(--pdf-scale))`);
        });
    });
}

{
    let timeout = null;
    window.triggerPDFScale = (delta) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => rescalePDF(window.RENDER_OPTIONS.scale + delta), 100);
    };
}
