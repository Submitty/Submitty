const { UI } = PDFAnnotate;

let currentTool;

let documentId = '';
let PAGE_HEIGHT;
window.RENDER_OPTIONS = {
    documentId,
    userId: "",
    pdfDocument: null,
    scale: parseFloat(localStorage.getItem('scale')) || 1,
    rotate: parseInt(localStorage.getItem('rotate')) || 0
};
window.GENERAL_INFORMATION = {
    grader_id: "",
    user_id: "",
    gradeable_id: "",
    file_name: "",
}

PDFJS.workerSrc = 'js/pdf/pdf.worker.min.js';

let NUM_PAGES = 0;

//For the student popup window, buildURL doesn't work because the context switched. Therefore, we need to pass in the url
//as a parameter.
function render_student(gradeable_id, user_id, file_name, pdf_url){
    render(gradeable_id, user_id, "", file_name, 1, pdf_url)
}

function render(gradeable_id, user_id, grader_id, file_name, page_num, url = "") {
    window.GENERAL_INFORMATION = {
        grader_id: grader_id,
        user_id: user_id,
        gradeable_id: gradeable_id,
        file_name: file_name
    }
    window.RENDER_OPTIONS.documentId = file_name;
    //TODO: Duplicate user_id in both RENDER_OPTIONS and GENERAL_INFORMATION, also grader_id = user_id in this context.
    window.RENDER_OPTIONS.userId = grader_id;
    if(url === ""){
        url = buildUrl({'component': 'misc', 'page': 'base64_encode_pdf'});
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: {
            gradeable_id: gradeable_id,
            user_id: user_id,
            filename: file_name
        },
        success: (data)=>{
            PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalStoreAdapter(GENERAL_INFORMATION.grader_id));
            // documentId = file_name;

            let pdfData;
            try {
                pdfData = JSON.parse(data);
                pdfData = atob(pdfData);
            } catch (err){
                alert("Something went wrong, please try again later.");
            }
            PDFJS.getDocument({data:pdfData}).then((pdf) => {
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
                NUM_PAGES = pdf.pdfInfo.numPages;
                for (let i=0; i<NUM_PAGES; i++) {
                    let page = UI.createPage(i+1);
                    viewer.appendChild(page);
                    let page_id = i+1;
                    UI.renderPage(page_id, window.RENDER_OPTIONS).then(([pdfPage, annotations]) => {
                        let viewport = pdfPage.getViewport(window.RENDER_OPTIONS.scale, window.RENDER_OPTIONS.rotate);
                        PAGE_HEIGHT = viewport.height;
                        if(i == page_num) {
                            $('#file_content').animate({scrollTop: page_num * PAGE_HEIGHT}, 500);
                        }
                    }).then(function(){
                        document.getElementById('pageContainer'+page_id).addEventListener('mousedown', function(){
                            //Makes sure the panel don't move when writing on it.
                            $("#submission_browser").draggable('disable');
                            let selected = $(".tool-selected");
                            if(selected.length != 0 && $(selected[0]).attr('value') != 'cursor'){
                                $("#save_status").text("Changes not saved");
                                $("#save_status").css("color", "red");
                            }
                        });
                        document.getElementById('pageContainer'+page_id).addEventListener('mouseup', function(){
                            $("#submission_browser").draggable('enable');
                        });
                    });
                }
            });
        }
    });
}


//TODO: Stretch goal, find a better solution to load/unload annotation. Maybe use session storage?
$(window).unload(function() {
    for(let i = 0; i < localStorage.length; i++){
        if(localStorage.key(i).includes('annotations')){
            localStorage.removeItem(localStorage.key(i));
        }
    }
});
