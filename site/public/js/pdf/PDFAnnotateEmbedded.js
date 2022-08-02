/* global PDFAnnotate, pdfjsLib, csrfToken, jspdf */
/* exported render_student, download_student, loadPDFToolbar, toggleOtherAnnotations, loadAllAnnotations, loadGraderAnnotations */

if (PDFAnnotate.default) {
    // eslint-disable-next-line no-global-assign
    PDFAnnotate = PDFAnnotate.default;
}

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

pdfjsLib.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';

function buildCourseUrl(parts = []) {
    return `${document.body.dataset.courseUrl}/${parts.join('/')}`;
}

//For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
//as a parameter.
function render_student(gradeable_id, user_id, file_name, file_path, pdf_url) {
    // set the values for default view through submission page
    window.RENDER_OPTIONS.scale = 1;
    window.RENDER_OPTIONS.rotate = 0;
    window.RENDER_OPTIONS.studentPopup = true;
    render(gradeable_id, user_id, '', file_name, file_path, 1, pdf_url);
}

//For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
//as a parameter.
function download_student(gradeable_id, user_id, file_name, file_path, pdf_url, rerender_pdf) {
    // set the values for default view through submission page
    window.RENDER_OPTIONS.scale = 1;
    window.RENDER_OPTIONS.rotate = 0;
    window.RENDER_OPTIONS.studentPopup = true;
    download(gradeable_id, user_id, '', file_name, file_path, 1, pdf_url, rerender_pdf);
}

// eslint-disable-next-line default-param-last, no-unused-vars
function download(gradeable_id, user_id, grader_id, file_name, file_path, page_num, url = '', rerender_pdf) {
    window.GENERAL_INFORMATION = {
        grader_id: grader_id,
        user_id: user_id,
        gradeable_id: gradeable_id,
        file_name: file_name,
        file_path: file_path,
    };
    //TODO: Replace this with rerender_pdf, only rerender if rerender_pdf is set to true
    // eslint-disable-next-line no-constant-condition
    if (true) {
        window.RENDER_OPTIONS.documentId = file_name;
        //TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
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
                PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalUserStoreAdapter(window.GENERAL_INFORMATION.grader_id));
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
        anchor.setAttribute('href','file_path');
        anchor.setAttribute('download', file_path);
        document.body.appendChild(anchor);
        anchor.click();
        anchor.parentNode.removeChild(anchor);
    }
}
function renderPageForDownload(pdf, doc, num, targetNum, file_name) {
    if (num < targetNum) {
        // eslint-disable-next-line eqeqeq
        if (num != 1) {
            doc.addPage();
        }
        pdf.getPage(num).then((page) => {
            const viewport = page.getViewport({scale:1});
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport,
            };

            page.render(renderContext).promise.then(() => {
                PDFAnnotate.getAnnotations(file_name, num).then((annotationsPage) => {
                    const annotations = annotationsPage.annotations;
                    for (let an = 0; an < annotations.length; an++) {
                        const annotation = annotations[an];
                        if (annotation.type === 'drawing') {
                            ctx.lineWidth = annotation.width;
                            ctx.strokeStyle = annotation.color;
                            ctx.beginPath();
                            for (let line = 1; line < annotation.lines.length; line++) {
                                ctx.moveTo(annotation.lines[line - 1][0], annotation.lines[line - 1][1]);
                                ctx.lineTo(annotation.lines[line][0], annotation.lines[line][1]);
                                ctx.stroke();
                            }
                        }

                        if (annotation.type === 'textbox') {
                            ctx.font = `${annotation.size}px sans-serif`;
                            ctx.fillStyle = annotation.color;
                            const text = annotation.content;
                            // eslint-disable-next-line eqeqeq
                            if (text != null) {
                                ctx.fillText(text, annotation.x, annotation.y);
                            }
                        }
                    }
                    const imgData = canvas.toDataURL('image/png');
                    doc.addImage(imgData, 'PNG', 15, 15);
                    renderPageForDownload(pdf, doc, num + 1, targetNum, file_name);
                    //TODO: Get the saving and loading from annotated_pdfs working
                    /*console.log("CHECK2");
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
                    });*/
                });
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
        //TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
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
                PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalUserStoreAdapter(window.GENERAL_INFORMATION.grader_id));
                // documentId = file_name;

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
                    window.RENDER_OPTIONS.pdfDocument = pdf;
                    if (window.GENERAL_INFORMATION.broken) {
                        return;
                    }
                    const viewer = document.getElementById('viewer');
                    $(viewer).on('touchstart touchmove', (e) => {
                        //Let touchscreen work
                        if (currentTool === 'pen' || currentTool === 'text') {
                            e.preventDefault();
                        }
                    });
                    $("a[value='zoomcustom']").text(`${parseInt(window.RENDER_OPTIONS.scale * 100)}%`);
                    viewer.innerHTML = '';
                    NUM_PAGES = pdf.numPages;
                    for (let i = 0; i < NUM_PAGES; i++) {
                        const page = PDFAnnotate.UI.createPage(i + 1);
                        viewer.appendChild(page);
                        const page_id = i + 1;
                        PDFAnnotate.UI.renderPage(page_id, window.RENDER_OPTIONS).then(() => {
                            // eslint-disable-next-line eqeqeq
                            if (i == page_num) {
                                // scroll to page on load
                                const initialPage = $(`#pageContainer${page_id}`);
                                if (initialPage.length) {
                                    $('#submission_browser').scrollTop(initialPage[0].offsetTop);
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
                            document.getElementById(`pageContainer${page_id}`).addEventListener('mouseenter', () => {
                                const selected = $($('.tool-selected')[0]).attr('value');
                                if (selected === 'pen') {
                                    PDFAnnotate.UI.enablePen();
                                }
                            });
                            document.getElementById(`pageContainer${page_id}`).addEventListener('mouseleave', () => {
                                //disable pen when mouse leaves the pdf page to allow for selecting inputs (like pen size)
                                const selected = $($('.tool-selected')[0]).attr('value');
                                if (selected === 'pen') {
                                    PDFAnnotate.UI.disablePen();
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
}


// TODO: Stretch goal, find a better solution to load/unload annotation. Maybe use session storage?
// the code below will remove the annotation info from local storage when a new window pops up
// unload event should not be avioded as well: https://developer.mozilla.org/en-US/docs/Web/API/Window/unload_event
/**
$(window).on('unload', () => {
    for (let i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).includes('annotations')) {
            localStorage.removeItem(localStorage.key(i));
        }
    }
});
**/

function loadPDFToolbar() {
    const init_pen_size = document.getElementById('pen_size_selector').value;
    const init_color = document.getElementById('color_selector').style.backgroundColor;
    localStorage.setItem('pen/size', init_pen_size);
    localStorage.setItem('main_color', init_color);
    PDFAnnotate.UI.setPen(init_pen_size, init_color);
    const init_text_size = document.getElementById('text_size_selector').value;
    localStorage.setItem('text/size', init_text_size);
    PDFAnnotate.UI.setText(init_text_size, init_color);
}

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
            //if the current localStorage property contains annotations
            if (localStorage.key(i).includes('annotations')) {
                const annotator = localStorage.key(i).split('/')[1];
                const from_other_user = annotator !== window.GENERAL_INFORMATION.grader_id;
                const annotations = JSON.parse(localStorage.getItem(localStorage.key(i)));
                //if the annotations are damaged beyond repair (and they belong to the current user)
                if (!Array.isArray(annotations) && !from_other_user) {
                    found_faulty = true;
                    //set broken flag to stop pdf from rendering
                    window.GENERAL_INFORMATION.broken = true;
                    //ask user if they would like to reset their annotations to
                    const irreparable = confirm('The annotations for this pdf are in an irreparable state.\nWould you like to reset them and refresh the page?');
                    if (irreparable) {
                        localStorage.setItem(localStorage.key(i), '[]');
                        saveFile();
                        window.location.reload();
                        return;
                    }
                    else {
                        //if they decline, remove the container for the pdf and show a repair button + warning message
                        $('#viewer').remove();
                        if (!$('#grading-pdf-repair-btn').length) {
                            $('#file-view').find('.file-view-header').append('<button id="grading-pdf-repair-btn" class="btn btn-primary" onclick="repairPDF()">Repair <i class="fas fa-tools"></i></button>');
                        }
                        $('#grading-pdf-repair').show();
                        return;
                    }
                }
                //loop through all annotations
                for (let i = annotations.length-1; i >= 0; i--) {
                    //gather properties with null values
                    const faulty_properties = Object.keys(annotations[i]).filter(prop => annotations[i][prop] === null);
                    if (annotations[i] && faulty_properties.length > 0) {
                        if (from_other_user) {
                            alert(`Faulty annotations from user ${annotator} have been detected. \nThey will be temporarily repaired for you, but please contact them so they can come to this page and repair them fully.`);
                        }
                        //if we haven't asked them about a repair yet and the annotations belong to the current user
                        if (!repair_faulty && !from_other_user) {
                            found_faulty = true;
                            repair_faulty = confirm(`One of your annotations has been detected as faulty which may cause features on this page to not work properly. Would you like to reset all of your faulty annotations to their default values and refresh the page?\n\nFile: ${window.RENDER_OPTIONS.documentId}`);
                            //if they decline to repair, move on to the next set of annotations
                            if (!repair_faulty) {
                                break;
                            }
                        }
                        //if they accepted a repair or the annotations are from another user (which are always temporarily repaired)
                        if (repair_faulty || from_other_user) {
                            //attempt to set a default value for each faulty property
                            for (const faulty_property of faulty_properties) {
                                if (Object.prototype.hasOwnProperty.call(ANNOTATION_DEFAULTS, faulty_property)) {
                                    annotations[i][faulty_property] = ANNOTATION_DEFAULTS[faulty_property];
                                }
                                //if there is no default value for this property, just delete the annotation
                                else {
                                    annotations.splice(i, 1);
                                }
                            }
                        }
                    }
                }
                //update the annotations in storage
                localStorage.setItem(localStorage.key(i), JSON.stringify(annotations));
            }
        }
    }
    catch (e) {
        // Ignore the identifier error
    }
    //if the user specified to repair their faulty annotations, we should save the file for them now.
    if (repair_faulty) {
        saveFile();
        window.location.reload();
    }
    //if faulty annotations from the current user were found but they declined, show Repair button
    //and a warning message
    else if (found_faulty) {
        $('#grading-pdf-repair').show();
        if (!$('#grading-pdf-repair-btn').length) {
            $('#file-view').find('.file-view-header').append('<button id="grading-pdf-repair-btn" class="btn btn-primary" onclick="repairPDF()">Repair <i class="fas fa-tools"></i></button>');
        }
    }
    //if everything looks good
    else {
        $('#grading-pdf-repair').hide();
        $('#grading-pdf-repair-btn').remove();
    }
}

function saveFile () {
    $('#save-pdf-btn').click();
}
