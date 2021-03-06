if (PDFAnnotate.default) {
  PDFAnnotate = PDFAnnotate.default;
}

var currentTool;
var documentId = '';
var PAGE_HEIGHT;
var NUM_PAGES = 0;

window.RENDER_OPTIONS = {
    documentId,
    userId: "",
    pdfDocument: null,
    scale: parseFloat(localStorage.getItem('scale')) || 1,
    rotate: parseInt(localStorage.getItem('rotate')) || 0,
    studentPopup: false
};

window.GENERAL_INFORMATION = {
    grader_id: "",
    user_id: "",
    gradeable_id: "",
    file_name: "",
}

pdfjsLib.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';

function buildCourseUrl(parts = []) {
    return document.body.dataset.courseUrl + '/' + parts.join('/');
}

//For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
//as a parameter.
function render_student(gradeable_id, user_id, file_name, file_path, pdf_url) {
    // set the values for default view through submission page
    window.RENDER_OPTIONS.scale = 1;
    window.RENDER_OPTIONS.rotate = 0;
    window.RENDER_OPTIONS.studentPopup = true;
    render(gradeable_id, user_id, "", file_name, file_path, 1, pdf_url)
}

//For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
//as a parameter.
function download_student(gradeable_id, user_id, file_name, file_path, pdf_url, rerender_pdf) {
    // set the values for default view through submission page
    window.RENDER_OPTIONS.scale = 1;
    window.RENDER_OPTIONS.rotate = 0;
    window.RENDER_OPTIONS.studentPopup = true;
    download(gradeable_id, user_id, "", file_name, file_path, 1, pdf_url, rerender_pdf);
}

function download(gradeable_id, user_id, grader_id, file_name, file_path, page_num, url = "", rerender_pdf) {
    window.GENERAL_INFORMATION = {
        grader_id: grader_id,
        user_id: user_id,
        gradeable_id: gradeable_id,
        file_name: file_name,
        file_path: file_path
    };
    //TODO: Replace this with rerender_pdf, only rerender if rerender_pdf is set to true
    if(true){
        window.RENDER_OPTIONS.documentId = file_name;
        //TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
        window.RENDER_OPTIONS.userId = grader_id;
        if (url === "") {
            url = buildCourseUrl(['gradeable', gradeable_id, 'encode_pdf']);
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                user_id: user_id,
                filename: file_name,
                file_path: file_path,
                csrf_token: csrfToken
            },
            success: (data) => {
                PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalUserStoreAdapter(GENERAL_INFORMATION.grader_id));
                let pdfData;
                try {
                    pdfData = JSON.parse(data)['data'];
                    pdfData = atob(pdfData);
                } catch (err){
                    console.log(err);
                    console.log(data);
                    alert("Something went wrong, please try again later.");
                }
                pdfjsLib.getDocument({
                    data: pdfData,
                    cMapUrl: '../../vendor/pdfjs/cmaps/',
                    cMapPacked: true
                }).promise.then((pdf) => {
                    let doc = new jsPDF('p', 'mm');
                    let numPages = pdf.numPages;
                    renderPageForDownload(pdf, doc, 1, numPages + 1, file_name);
                });
            }
        });
    }
    else{
        var anchor=document.createElement('a');
        anchor.setAttribute('href','file_path');
        anchor.setAttribute('download', file_path);
        document.body.appendChild(anchor);
        anchor.click();
        anchor.parentNode.removeChild(anchor);
    }
}
function renderPageForDownload(pdf, doc, num, targetNum, file_name) {
    if(num < targetNum){
        if(num != 1){
            doc.addPage();
        }
        page = pdf.getPage(num).then(function(page) {
            let viewport = page.getViewport({scale:1});
            let canvas = document.createElement('canvas');
            let ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            let renderContext = {
              canvasContext: ctx,
              viewport: viewport
            };
            
            page.render(renderContext).then(function(){
                let annotationsPage = PDFAnnotate.getAnnotations(file_name, num).then(function(annotationsPage){
                    let annotations=annotationsPage.annotations;
                    for(let an = 0; an < annotations.length; an++){
                        annotation = annotations[an];
                        if(annotation.type == "drawing"){
                            ctx.lineWidth = annotation.width;
                            ctx.strokeStyle = annotation.color;
                            for(let line = 1; line < annotation.lines.length; line++){
                                ctx.moveTo(annotation.lines[line-1][0], annotation.lines[line-1][1]);
                                ctx.lineTo(annotation.lines[line][0], annotation.lines[line][1]);
                                ctx.stroke();
                            }
                        }
                        
                        if(annotation.type == "textbox"){
                            ctx.font = annotation.size + "px sans-serif";
                            ctx.fillStyle = annotation.color;
                            let text = annotation.content;
                            if(text != null){
                                ctx.fillText(text, annotation.x, annotation.y);
                            }
                        }
                    }
                    let imgData = canvas.toDataURL('image/png');              
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
    else{
        doc.save(file_name);
    }
}

function render(gradeable_id, user_id, grader_id, file_name, file_path, page_num, url = "") {
    window.GENERAL_INFORMATION = {
        grader_id: grader_id,
        user_id: user_id,
        gradeable_id: gradeable_id,
        file_name: file_name,
        file_path: file_path
    };

    window.RENDER_OPTIONS.documentId = file_name;
    //TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
    window.RENDER_OPTIONS.userId = grader_id;
    if (url === "") {
        url = buildCourseUrl(['gradeable', gradeable_id, 'encode_pdf']);
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: {
            user_id: user_id,
            filename: file_name,
            file_path: file_path,
            csrf_token: csrfToken
        },
        success: (data) => {
            PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalUserStoreAdapter(GENERAL_INFORMATION.grader_id));
            // documentId = file_name;

            let pdfData;
            try {
                pdfData = JSON.parse(data)['data'];
                pdfData = atob(pdfData);
            } catch (err){
                console.log(err);
                console.log(data);
                alert("Something went wrong, please try again later.");
            }
            pdfjsLib.getDocument({
                data: pdfData,
                cMapUrl: '../../vendor/pdfjs/cmaps/',
                cMapPacked: true
            }).promise.then((pdf) => {
                window.RENDER_OPTIONS.pdfDocument = pdf;
                let viewer = document.getElementById('viewer');
                $(viewer).on('touchstart touchmove', function(e){
                    //Let touchscreen work
                    if(currentTool == "pen" || currentTool == "text"){
                        e.preventDefault();
                    }
                });
                $("a[value='zoomcustom']").text(parseInt(window.RENDER_OPTIONS.scale * 100) + "%");
                viewer.innerHTML = '';
                NUM_PAGES = pdf.numPages;
                for (let i=0; i<NUM_PAGES; i++) {
                    let page = PDFAnnotate.UI.createPage(i+1);
                    viewer.appendChild(page);
                    let page_id = i+1;
                    PDFAnnotate.UI.renderPage(page_id, window.RENDER_OPTIONS).then(function(){
                        if (i == page_num) {
                            // scroll to page on load
                            let initialPage = $("#pageContainer" + page_id);
                            if(initialPage.length) {
                                $('#file-content').animate({scrollTop: initialPage[0].offsetTop}, 500);
                            }
                        }
                        document.getElementById('pageContainer'+page_id).addEventListener('pointerdown', function(){
                            let selected = $(".tool-selected");
                            if(selected.length != 0 && $(selected[0]).attr('value') != 'cursor'){
                                $("#save_status").text("Changes not saved");
                                $("#save_status").css("color", "red");
                            }
                        });
                    });
                }
            });
        }
    });
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
function loadPDFToolbar(){
    let init_pen_size = document.getElementById('pen_size_selector').value;
    let init_color = document.getElementById('color_selector').style.backgroundColor;
    localStorage.setItem('pen/size', init_pen_size);
    localStorage.setItem('main_color', init_color);
    PDFAnnotate.UI.setPen(init_pen_size, init_color);
    let init_text_size = document.getElementById('text_size_selector').value;
    localStorage.setItem('text/size', init_text_size);
    PDFAnnotate.UI.setText(init_text_size, init_color);
}
