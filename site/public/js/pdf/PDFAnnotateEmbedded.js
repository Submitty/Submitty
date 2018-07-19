const { UI } = PDFAnnotate;
let documentId = 'example.pdf';
let PAGE_HEIGHT;
let RENDER_OPTIONS = {
    documentId,
    pdfDocument: null,
    // scale: parseFloat(localStorage.getItem(`${documentId}/scale`), 10) || 0.5,
    scale: 1,
    rotate: parseInt(localStorage.getItem(`${documentId}/rotate`), 10) || 0
};

PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalStoreAdapter());
PDFJS.workerSrc = './shared/pdf.worker.js';

/*
This chunk renders the page when scrolling. It also makes sure that no page is rendered more than once.
 */
let NUM_PAGES = 0;
let renderedPages = [];
let okToRender = false;
document.getElementById('submission_browser').addEventListener('scroll', function (e) {
    let visiblePageNum = Math.round(e.target.scrollTop / PAGE_HEIGHT) + 1;
    let visiblePage = document.querySelector(`.page[data-page-number="${visiblePageNum}"][data-loaded="false"]`);

    if (renderedPages.indexOf(visiblePageNum) == -1){
        okToRender = true;
        renderedPages.push(visiblePageNum);
    } else {
        okToRender = false;
    }

    if (visiblePage && okToRender) {
        setTimeout(function () {
            UI.renderPage(visiblePageNum, RENDER_OPTIONS);
        });
    }
});

function render(gradeable_id, user_id, file_name) {
    $.ajax({
        type: 'POST',
        url:'http://192.168.56.101/index.php?semester=f18&course=sample&component=misc&page=base64_encode_pdf',
        // url: buildUrl({'component': 'misc', 'page': 'base64_encode_pdf'}),
        data: {
            gradeable_id: gradeable_id,
            user_id: user_id,
            filename: file_name
        },
        success: function(data){
            RENDER_OPTIONS.documentId = file_name;
            documentId = file_name;
            var pdfData = JSON.parse(data);
            pdfData = atob(pdfData);
            PDFJS.getDocument({data:pdfData}).then((pdf) => {
                RENDER_OPTIONS.pdfDocument = pdf;
                let viewer = document.getElementById('viewer');
                viewer.innerHTML = '';
                NUM_PAGES = pdf.pdfInfo.numPages;
                for (let i=0; i<NUM_PAGES; i++) {
                    let page = UI.createPage(i+1);
                    viewer.appendChild(page);
                }

                UI.renderPage(1, RENDER_OPTIONS).then(([pdfPage, annotations]) => {
                    let viewport = pdfPage.getViewport(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate);
                    PAGE_HEIGHT = viewport.height;
                })
            });
        }
    });
}
