/* global PDFAnnotate, pdfjsLib, csrfToken, jspdf, displaySuccessMessage, displayErrorMessage */
/* exported render_student, download_student, loadPDFToolbar */
if (PDFAnnotate.default) {
    // eslint-disable-next-line no-global-assign
    PDFAnnotate = PDFAnnotate.default;
}

let currentTool;
let NUM_PAGES = 0;

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

const ANNOTATION_DEFAULTS = {
    size: 20,
    color: "#FF0000",
    class: "Annotation",
    page: 1,
    rotation: 0,
    x: 50,
    y: 50,
    content: "DEFAULT VALUE",
    width: 5
}

pdfjsLib.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';

function buildCourseUrl(parts = []) {
    return `${document.body.dataset.courseUrl}/${parts.join('/')}`;
}

//For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
//as a parameter.
function render_student(gradeable_id, user_id, file_name, file_path, pdf_url) {
    console.log('render student...')
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

            page.render(renderContext).then(() => {
                PDFAnnotate.getAnnotations(file_name, num).then((annotationsPage) => {
                    const annotations = annotationsPage.annotations;
                    for (let an = 0; an < annotations.length; an++) {
                        const annotation = annotations[an];
                        if (annotation.type == 'drawing') {
                            ctx.lineWidth = annotation.width;
                            ctx.strokeStyle = annotation.color;
                            for (let line = 1; line < annotation.lines.length; line++) {
                                ctx.moveTo(annotation.lines[line - 1][0], annotation.lines[line - 1][1]);
                                ctx.lineTo(annotation.lines[line][0], annotation.lines[line][1]);
                                ctx.stroke();
                            }
                        }

                        if (annotation.type == 'textbox') {
                            ctx.font = `${annotation.size}px sans-serif`;
                            ctx.fillStyle = annotation.color;
                            const text = annotation.content;
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
                if(window.GENERAL_INFORMATION.broken) {
                    return;
                }
                const viewer = document.getElementById('viewer');
                $(viewer).on('touchstart touchmove', (e) => {
                    //Let touchscreen work
                    if (currentTool == 'pen' || currentTool == 'text') {
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
                        if (i == page_num) {
                            // scroll to page on load
                            const initialPage = $(`#pageContainer${page_id}`);
                            if (initialPage.length) {
                                $('#file-content').animate({scrollTop: initialPage[0].offsetTop}, 500);
                            }
                        }
                        document.getElementById(`pageContainer${page_id}`).addEventListener('pointerdown', () => {
                            const selected = $('.tool-selected');
                            if (selected.length != 0 && $(selected[0]).attr('value') != 'cursor') {
                                $('#save_status').text('Changes not saved');
                                $('#save_status').css('color', 'red');
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
                    })
                }
            });
        },
    });
    repairPDF();
}

// TODO: Stretch goal, find a better solution to load/unload
// annotation. Maybe use session storage?
$(window).on('unload', () => {
    for (let i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).includes('annotations')) {
            localStorage.removeItem(localStorage.key(i));
        }
    }
});

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

function toggleOtherAnnotations(hide_others) {
    for (let i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).includes('annotations')) {
            const annotator = localStorage.key(i).split('/')[1];
            const from_other_user = annotator !== window.GENERAL_INFORMATION.grader_id;
            if (from_other_user) {
                if (hide_others) {
                    if (!window.GENERAL_INFORMATION.hidden_annotations) {
                        window.GENERAL_INFORMATION.hidden_annotations = {}
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
    let remove_faulty = false;
    let found_faulty = false;
    let found_faulty_other = false;
    $('#grading-pdf-repair').hide();
    for (let i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).includes('annotations')) {
            const annotator = localStorage.key(i).split('/')[1];
            const from_other_user = annotator !== window.GENERAL_INFORMATION.grader_id;
            let annotations = JSON.parse(localStorage.getItem(localStorage.key(i)));
            if (!Array.isArray(annotations) && !from_other_user) {
                found_faulty = true;
                window.GENERAL_INFORMATION.broken = true;
                const irreparable = confirm('The annotations for this pdf are in an irreparable state.\nWould you like to reset them and refresh the page?');
                if (irreparable) {
                    //clearAllAnnotations();
                    localStorage.setItem(localStorage.key(i), "[]");
                    saveFile();
                    window.location.reload();
                    return;
                }
                else {
                    $('#viewer').remove();
                    if(!$('#grading-pdf-repair-btn').length) {
                        $('#file-view').find('.file-view-header').append('<button id="grading-pdf-repair-btn" class="btn btn-primary" onclick="repairPDF()">Repair <i class="fas fa-tools"></i></button>');
                    }
                    $('#grading-pdf-repair').show();
                    return;
                }
            }
            for (let i = annotations.length-1; i >= 0; i--) {
                const faulty_properties = Object.keys(annotations[i]).filter(prop => annotations[i][prop] === null);
                if(annotations[i] && faulty_properties.length > 0) {
                    if (from_other_user) {
                        found_faulty_other = true;
                        alert(`Faulty annotations from user ${annotator} have been detected. \nThey will be temporarily repaired for you, but please contact them so they can come to this page and repair them fully.`);
                    }
                    if (!remove_faulty && !from_other_user) {
                        found_faulty = true;
                        remove_faulty = confirm(`One of your annotations has been detected as faulty which may cause features on this page to not work properly. Would you like to reset all of your faulty annotations to their default values and refresh the page?\n\nFile: ${window.RENDER_OPTIONS.documentId}`);
                        if (!remove_faulty) {
                            break;
                        }
                    }
                    if (remove_faulty || from_other_user) {
                        console.log('repairing...')
                        for(const faulty_property of faulty_properties) {
                            if (ANNOTATION_DEFAULTS.hasOwnProperty(faulty_property)) {
                                annotations[i][faulty_property] = ANNOTATION_DEFAULTS[faulty_property];
                            } 
                            //if there is no default value for this property, just dedlete the annotation
                            else {
                                annotations.splice(i, 1);
                            }
                        }
                    }
                }
            }
            localStorage.setItem(localStorage.key(i), JSON.stringify(annotations))
        }
    }
    //if the user specified to remove faulty annotations, we should save the file for them now.
    if (remove_faulty) {
        saveFile();
        window.location.reload();
    } 
    else if (found_faulty) {
        $('#grading-pdf-repair').show();
        if(!$('#grading-pdf-repair-btn').length) {
            $('#file-view').find('.file-view-header').append('<button id="grading-pdf-repair-btn" class="btn btn-primary" onclick="repairPDF()">Repair <i class="fas fa-tools"></i></button>');
        }
    }
    else {
        $('#grading-pdf-repair').hide();
        $('#grading-pdf-repair-btn').remove();    
    }
}

function saveFile () {
    const save_button_bbox = $('.toolbar-action[value=save]')[0].getBoundingClientRect()
    document.elementFromPoint(save_button_bbox.left, save_button_bbox.top).click();
}