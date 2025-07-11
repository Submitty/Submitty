/* global pdfjsLib, csrfToken, jspdf */
/* exported render_student, download_student, loadPDFToolbar, toggleOtherAnnotations, loadAllAnnotations, loadGraderAnnotations */

window.RENDER_OPTIONS = {
    documentId: '',
    userId: '',
    pdfDocument: null,
    scale: parseFloat(localStorage.getItem('scale')) || 1,
    rotate: parseInt(localStorage.getItem('rotate')) || 0,
    studentPopup: false,
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

    // Load the page and annotations
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

pdfjsLib.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';

function buildCourseUrl(parts = []) {
    return `${document.body.dataset.courseUrl}/${parts.join('/')}`;
}

// For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
// as a parameter.
function render_student(gradeable_id, user_id, file_name, file_path, pdf_url) {
    // set the values for default view through submission page
    window.RENDER_OPTIONS.scale = 1;
    window.RENDER_OPTIONS.rotate = 0;
    window.RENDER_OPTIONS.studentPopup = true;
    render(gradeable_id, user_id, '', file_name, file_path, 1, pdf_url);
}

// For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
// as a parameter.
function download_student(gradeable_id, user_id, file_name, file_path, pdf_url, rerender_pdf) {
    // set the values for default view through submission page
    window.RENDER_OPTIONS.scale = 1;
    window.RENDER_OPTIONS.rotate = 0;
    window.RENDER_OPTIONS.studentPopup = true;
    download(gradeable_id, user_id, '', file_name, file_path, 1, pdf_url, rerender_pdf);
}

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
                pdfjsLib.getDocument({
                    data: pdfData,
                    cMapUrl: '../../vendor/pdfjs/cmaps/',
                    cMapPacked: true,
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
                // TODO: Get the saving and loading from annotated_pdfs working
                /* console.log("CHECK2");
                    var fd = new FormData();
                    var pdfToSave = btoa(doc.output());
                    let GENERAL_INFORMATION = window.GENERAL_INFORMATION;
                    let annotation_layer = localStorage.getItem(`${window.RENDER_OPTIONS.documentId}/${GENERAL_INFORMATION.grader_id}/annotations`);
                    fd.append('annotation_layer', JSON.stringify(annotation_layer));
                    fd.append('GENERAL_INFORMATION', JSON.stringify(GENERAL_INFORMATION));
                    fd.append('csrf_token', csrfToken);
                    fd.append('pdf', pdfToSave);
                    let url = buildCourseUrl(['gradeable', GENERAL_INFORMATION['gradeable_id'], 'pdf', 'annotated_pdfs']);
                    //localStorage.setItem('rotate', rotateVal);
                    //render(window.GENERAL_INFORMATION.gradeable_id, window.GENERAL_INFORMATION.user_id, window.GENERAL_INFORMATION.grader_id, window.GENERAL_INFORMATION.file_name, window.GENERAL_INFORMATION.file_path);
                    $.ajax({
                        type: 'POST',
                        url: url,
                        data: fd,
                        processData: false,
                        contentType: false,
                        success: function(data){
                            console.log("CHECK4");
                            //let response = JSON.parse(data);
                            console.log("CHECK3");
                            var anchor=document.createElement('a');
                            anchor.setAttribute('href', encodeURIComponent(file_path));
                            anchor.setAttribute('download', file_path);
                            document.body.appendChild(anchor);
                            anchor.click();
                            anchor.parentNode.removeChild(anchor);
                        },
                        error: function(){
                            alert("Something went wrong, please contact a administrator.");
                        }
                    }); */
            });
        });
    }
    else {
        doc.save(file_name);
    }
}

function render(gradeable_id, user_id, grader_id, file_name, file_path, page_num, url = '') {
    try {
        updateAnnotations();
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
                pdfjsLib.getDocument({
                    data: pdfData,
                    cMapUrl: '../../vendor/pdfjs/cmaps/',
                    cMapPacked: true,
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
                    for (let i = 0; i < NUM_PAGES; i++) {
                        const page = createPage(i + 1);
                        viewer.appendChild(page);
                        const page_id = i + 1;
                        renderPage(page_id, window.RENDER_OPTIONS).then(() => {
                            // eslint-disable-next-line eqeqeq
                            if (i == page_num) {
                                // scroll to page on load
                                const initialPage = $(`#pageContainer${page_id}`);
                                if (initialPage.length) {
                                    $('#submission_browser').scrollTop(Math.max(page.offsetTop - $('#file-view > .sticky-file-info').first().height(), 0));
                                }
                            }
                            document.getElementById(`pageContainer${page_id}`).addEventListener('pointerdown', () => {
                                const selected = $('.tool-selected');
                                if (selected.length !== 0 && $(selected[0]).attr('value') !== 'cursor') {
                                    $('#save_status').text('Changes not saved');
                                    $('#save_status').css('color', 'red');
                                    $('#save-pdf-btn').removeClass('btn-default');
                                    $('#save-pdf-btn').addClass('btn-primary');
                                }
                            });
                        });
                    }
                });
            },
        });
    }
    catch (e) {
        // ignore the identifier error
    }
    repairPDF();
    updateAnnotations();
}

// TODO: Stretch goal, find a better solution to load/unload annotation. Maybe use session storage?
// the code below will remove the annotation info from local storage when a new window pops up
// unload event should not be avoided as well: https://developer.mozilla.org/en-US/docs/Web/API/Window/unload_event
/**
$(window).on('unload', () => {
    for (let i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).includes('annotations')) {
            localStorage.removeItem(localStorage.key(i));
        }
    }
});
**/

function loadAllAnnotations(annotations, file_name) {
    for (const grader in annotations) {
        if (annotations[grader] !== '') {
            localStorage.setItem(`${file_name}/${grader}/annotations`, annotations[grader]);
        }
    }
}

function loadGraderAnnotations(annotations, file_name, grader_id) {
    for (const grader in annotations) {
        if (annotations[grader] !== '') {
            if (grader === grader_id) {
                localStorage.setItem(`${file_name}/${grader}/annotations`, annotations[grader]);
            }
            else {
                if (!window.GENERAL_INFORMATION.hidden_annotations) {
                    window.GENERAL_INFORMATION.hidden_annotations = {};
                }
                window.GENERAL_INFORMATION.hidden_annotations[grader] = annotations[grader];
                localStorage.setItem(`${file_name}/${grader}/annotations`, '[]');
            }
        }
    }
}

function toggleOtherAnnotations(hide_others) {
    for (let i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).includes('annotations')) {
            const annotator = localStorage.key(i).split('/')[1];
            const from_other_user = annotator !== window.GENERAL_INFORMATION.grader_id;
            if (from_other_user) {
                if (hide_others) {
                    if (!window.GENERAL_INFORMATION.hidden_annotations) {
                        window.GENERAL_INFORMATION.hidden_annotations = {};
                    }
                    window.GENERAL_INFORMATION.hidden_annotations[annotator] = localStorage.getItem(localStorage.key(i));
                    localStorage.setItem(localStorage.key(i), '[]');
                }
                else {
                    localStorage.setItem(localStorage.key(i), window.GENERAL_INFORMATION.hidden_annotations[annotator]);
                }
            }
        }
    }
    render(window.GENERAL_INFORMATION.gradeable_id, window.GENERAL_INFORMATION.user_id, window.GENERAL_INFORMATION.grader_id, window.GENERAL_INFORMATION.file_name, window.GENERAL_INFORMATION.file_path);
}

function updateAnnotations() {
    const elements = document.querySelectorAll('path, g');

    elements.forEach((element) => {
        let transform = element.getAttribute('transform');
        let hasTranslate = false;

        if (transform) {
            const updatedTransform = transform.replace(/translate\(\s*(-?[\d.]+)?\s*,\s*(-?[\d.]+)?\s*\)/, (match, x, y) => {
                hasTranslate = true;

                x = x !== undefined ? parseFloat(x) : 0;
                y = y !== undefined ? parseFloat(y) : 0;

                return `translate(${x}, ${y})`;
            });

            transform = updatedTransform;
        }

        // Add translate(0, 0) if there's no translate transformation
        if (!hasTranslate) {
            transform = transform ? `${transform.trim()} translate(0, 0)` : 'translate(0, 0)';
        }

        // Update the transform attribute of the element, removing unnecessary spaces
        element.setAttribute('transform', transform.replace(/\s+/g, ' '));
    });
}

function repairPDF() {
    let repair_faulty = false;
    let found_faulty = false;

    try {
        const ANNOTATION_DEFAULTS = {
            size: 12,
            color: '#000000',
            class: 'Annotation',
            page: 1,
            rotation: 0,
            x: 50,
            y: 50,
            content: 'DEFAULT VALUE',
            width: 5,
        };

        $('#grading-pdf-repair').hide();
        for (let i = 0; i < localStorage.length; i++) {
            // if the current localStorage property contains annotations
            if (localStorage.key(i).includes('annotations')) {
                const annotator = localStorage.key(i).split('/')[1];
                const from_other_user = annotator !== window.GENERAL_INFORMATION.grader_id;
                const annotations = JSON.parse(localStorage.getItem(localStorage.key(i)));
                // if the annotations are damaged beyond repair (and they belong to the current user)
                if (!Array.isArray(annotations) && !from_other_user) {
                    found_faulty = true;
                    // set broken flag to stop pdf from rendering
                    window.GENERAL_INFORMATION.broken = true;
                    // ask user if they would like to reset their annotations to
                    const irreparable = confirm('The annotations for this pdf are in an irreparable state.\nWould you like to reset them and refresh the page?');
                    if (irreparable) {
                        localStorage.setItem(localStorage.key(i), '[]');
                        saveFile();
                        window.location.reload();
                        return;
                    }
                    else {
                        // if they decline, remove the container for the pdf and show a repair button + warning message
                        $('#viewer').remove();
                        if (!$('#grading-pdf-repair-btn').length) {
                            $('#file-view').find('.file-view-header').append('<button id="grading-pdf-repair-btn" class="btn btn-primary" onclick="repairPDF()">Repair <i class="fas fa-tools"></i></button>');
                        }
                        $('#grading-pdf-repair').show();
                        return;
                    }
                }
                // loop through all annotations
                for (let i = annotations.length - 1; i >= 0; i--) {
                    // gather properties with null values
                    const faulty_properties = Object.keys(annotations[i]).filter((prop) => annotations[i][prop] === null);
                    if (annotations[i] && faulty_properties.length > 0) {
                        if (from_other_user) {
                            alert(`Faulty annotations from user ${annotator} have been detected. \nThey will be temporarily repaired for you, but please contact them so they can come to this page and repair them fully.`);
                        }
                        // if we haven't asked them about a repair yet and the annotations belong to the current user
                        if (!repair_faulty && !from_other_user) {
                            found_faulty = true;
                            repair_faulty = confirm(`One of your annotations has been detected as faulty which may cause features on this page to not work properly. Would you like to reset all of your faulty annotations to their default values and refresh the page?\n\nFile: ${window.RENDER_OPTIONS.documentId}`);
                            // if they decline to repair, move on to the next set of annotations
                            if (!repair_faulty) {
                                break;
                            }
                        }
                        // if they accepted a repair or the annotations are from another user (which are always temporarily repaired)
                        if (repair_faulty || from_other_user) {
                            // attempt to set a default value for each faulty property
                            for (const faulty_property of faulty_properties) {
                                if (Object.prototype.hasOwnProperty.call(ANNOTATION_DEFAULTS, faulty_property)) {
                                    annotations[i][faulty_property] = ANNOTATION_DEFAULTS[faulty_property];
                                }
                                // if there is no default value for this property, just delete the annotation
                                else {
                                    annotations.splice(i, 1);
                                }
                            }
                        }
                    }
                }
                // update the annotations in storage
                localStorage.setItem(localStorage.key(i), JSON.stringify(annotations));
            }
        }
    }
    catch (e) {
        // Ignore the identifier error
    }
    // if the user specified to repair their faulty annotations, we should save the file for them now.
    if (repair_faulty) {
        saveFile();
        window.location.reload();
    }
    // if faulty annotations from the current user were found but they declined, show Repair button
    // and a warning message
    else if (found_faulty) {
        $('#grading-pdf-repair').show();
        if (!$('#grading-pdf-repair-btn').length) {
            $('#file-view').find('.file-view-header').append('<button id="grading-pdf-repair-btn" class="btn btn-primary" onclick="repairPDF()">Repair <i class="fas fa-tools"></i></button>');
        }
    }
    // if everything looks good
    else {
        $('#grading-pdf-repair').hide();
        $('#grading-pdf-repair-btn').remove();
    }
}

function saveFile() {
    $('#save-pdf-btn').click();
}
