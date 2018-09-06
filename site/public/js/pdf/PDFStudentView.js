const { UI } = PDFAnnotate;

let currentTool;

let documentId = '';
let PAGE_HEIGHT;
let RENDER_OPTIONS = {
    documentId,
    pdfDocument: null,
    scale: parseFloat(localStorage.getItem('scale')) || 1,
    rotate: parseInt(localStorage.getItem('rotate')) || 0
};
let GENERAL_INFORMATION = {
    grader_id: "",
    user_id: "",
    gradeable_id: "",
    file_name: "",
}

PDFJS.workerSrc = 'js/pdf/pdf.worker.min.js';

/*
 * This chunk renders the page when scrolling. It also makes sure that no page is rendered more than once.
 * NOTE: Currently this is disabled because it causes too many bugs. Will re-enable if performance becomes a
 * big issue.
 */
let NUM_PAGES = 0;
// let renderedPages = [];
// let okToRender = false;
// document.getElementById('file_content').addEventListener('scroll', function (e) {
//     let visiblePageNum = Math.round(e.target.scrollTop / PAGE_HEIGHT) + 1;
//     let visiblePage = document.querySelector(`.page[data-page-number="${visiblePageNum}"][data-loaded="false"]`);
//
//     if (renderedPages.indexOf(visiblePageNum) == -1){
//         okToRender = true;
//         renderedPages.push(visiblePageNum);
//     } else {
//         okToRender = false;
//     }
//
//     if (visiblePage && okToRender) {
//         setTimeout(function () {
//             UI.renderPage(visiblePageNum, RENDER_OPTIONS);
//         });
//     }
// });

function render(gradeable_id, user_id, file_name, url) {
    $.ajax({
        type: 'POST',
        url: url,
        data: {
            gradeable_id: gradeable_id,
            user_id: user_id,
            filename: file_name
        },
        success: function(data){
            GENERAL_INFORMATION.user_id = user_id;
            GENERAL_INFORMATION.gradeable_id = gradeable_id;
            GENERAL_INFORMATION.file_name = file_name;
            RENDER_OPTIONS.documentId = file_name;
            //It's empty if student is viewing
            // PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalStoreAdapter(GENERAL_INFORMATION.grader_id));
            // documentId = file_name;

            let pdfData;
            try {
                pdfData = JSON.parse(data);
                pdfData = atob(pdfData);
                PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalStoreAdapter(GENERAL_INFORMATION.grader_id));
            } catch (err){
                alert("Please select 'Grade this version' in Student Information panel. If it is already selected, " +
                    "then the PDF is either corrupt or broken");
            }
            PDFJS.getDocument({data:pdfData}).then((pdf) => {
                RENDER_OPTIONS.pdfDocument = pdf;
                let viewer = document.getElementById('viewer');
                $(viewer).on('touchstart touchmove', function(e){
                    //Let touchscreen work
                    if(currentTool == "pen" || currentTool == "text"){
                        e.preventDefault();
                    }
                });
                viewer.innerHTML = '';
                NUM_PAGES = pdf.pdfInfo.numPages;
                for (let i=0; i<NUM_PAGES; i++) {
                    let page = UI.createPage(i+1);
                    viewer.appendChild(page);
                    let page_id = i+1;
                    UI.renderPage(page_id, RENDER_OPTIONS).then(([pdfPage, annotations]) => {
                        let viewport = pdfPage.getViewport(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate);
                        PAGE_HEIGHT = viewport.height;
                    }).catch((err) => {
                        alert("Something went wrong, please contact your instructor.");
                    });
                }
            });
        }
    });
}

//TODO: Stretch goal, find a better solution to load/unload annotation. Maybe use session storage?
// $(window).unload(function() {
//     for(let i = 0; i < localStorage.length; i++){
//         if(localStorage.key(i).includes('annotations')){
//             localStorage.removeItem(localStorage.key(i));
//         }
//     }
// });
