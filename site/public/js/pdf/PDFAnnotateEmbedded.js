const { UI } = PDFAnnotate;
let documentId = '';
let PAGE_HEIGHT;
let RENDER_OPTIONS = {
    documentId,
    //User id in this case is the grader
    userId: null,
    pdfDocument: null,
    // scale: parseFloat(localStorage.getItem(`${documentId}/scale`), 10) || 0.5,
    scale: 1,
    rotate: parseInt(localStorage.getItem(`${documentId}/rotate`), 10) || 0
};
let GENERAL_INFORMATION = {
    user_id: "",
    gradeable_id: "",
    file_name: "",
}

PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalStoreAdapter());
PDFJS.workerSrc = 'js/pdf/pdf.worker.js';

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

function render(gradeable_id, user_id, grader_id, file_name) {
    let url = buildUrl({'component': 'misc', 'page': 'base64_encode_pdf'});
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
            RENDER_OPTIONS.userId = grader_id;
            // documentId = file_name;
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
                    let page_id = i+1;
                    UI.renderPage(page_id, RENDER_OPTIONS).then(([pdfPage, annotations]) => {
                        let viewport = pdfPage.getViewport(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate);
                        PAGE_HEIGHT = viewport.height;
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
//Toolbar stuff
(function (){
    function setActiveToolbarItem(option){
        let selected = $('.tool-selected');
        if(option != selected.attr('value')){
            //There are two classes for the icons; toolbar-action and toolbar-item.
            //toolbar-action are single use buttons such as download and clear
            //toolbar-item are continuous options such as pen, text, etc.
            let clicked_button = $("a[value="+option+"]");
            if(!clicked_button.hasClass('toolbar-action')){
                $(selected[0]).removeClass('tool-selected');
                clicked_button.addClass('tool-selected');
                switch($(selected[0]).attr('value')){
                    case 'pen':
                        UI.disablePen();
                        break;
                    case 'cursor':
                        UI.disableEdit();
                        break;
                    case 'text':
                        UI.disableText();
                        break;
                }
                $('.selection_panel').hide();
            }
            switch(option){
                case 'pen':
                    UI.enablePen();
                    break;
                case 'cursor':
                    UI.enableEdit();
                    break;
                case 'clear':
                    clearCanvas();
                    break;
                case 'save':
                    saveFile();
                    break;
                case 'zoomin':
                    zoom('in');
                    break;
                case 'zoomout':
                    zoom('out');
                    break;
                case 'text':
                    UI.enableText();
                    break;
            }
        } else {
            switch(option){
                case 'pen':
                    $("#pen_selection").toggle();
                    break;
                case 'text':
                    $("#text_selection").toggle();
                    break;
            }
        }
    }

    function zoom(option){
        if(option == 'in'){
            RENDER_OPTIONS.scale += 0.1;
        } else {
            RENDER_OPTIONS.scale -= 0.1;
        }

        UI.renderPage(1, RENDER_OPTIONS);
    }

    function clearCanvas(){
        if (confirm('Are you sure you want to clear annotations?')) {
            for (let i=0; i<NUM_PAGES; i++) {
                document.querySelector(`div#pageContainer${i+1} svg.annotationLayer`).innerHTML = '';
            }

            localStorage.removeItem(`${RENDER_OPTIONS.documentId}/annotations`);
        }
    }

    function saveFile(){
        $('#save_status').text("Saved");
        $('#save_status').css('color', 'black');
        let url = buildUrl({'component': 'grading','page': 'electronic', 'action': 'save_pdf_annotation'});
        let annotation_layer = localStorage.getItem(`${RENDER_OPTIONS.documentId}/${RENDER_OPTIONS.userId}/annotations`);
        // let count = 0;
        // for (let i = 0; i < JSON.parse(annotation_layer).length; i++){
        //     count+= JSON.parse(annotation_layer)[i]['lines'].length;
        // }
        // console.log(count);
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                annotation_layer,
                GENERAL_INFORMATION
            },
            success: function(data){

            }
        });
    }

    function handleToolbarClick(e){
        setActiveToolbarItem(e.target.getAttribute('value'));
    }
    document.getElementById('pdf_annotation_icons').addEventListener('click', handleToolbarClick);
})();

// Pen stuff
(function () {
    let penSize;
    let penColor;

    function initPen() {
        let init_size = localStorage.getItem('pen/size') || 3;
        let init_color = localStorage.getItem('pen/color') || '#ff0000';
        document.getElementById('pen_size_selector').value = init_size;
        document.getElementById('pen_size_value').value = init_size;
        document.getElementById('pen_color_selector').value = init_color;
        setPen(init_size, init_color);
    }

    function setPen(size, color) {
        let modified = false;

        if (penSize !== size) {
            modified = true;
            penSize = size;
            localStorage.setItem('pen/size', penSize);
        }

        if (penColor !== color) {
            modified = true;
            penColor = color;
            localStorage.setItem('pen/color', penColor);
        }

        if (modified) {
            UI.setPen(penSize, penColor);
        }
    }

    document.getElementById('pen_color_selector').addEventListener('change', function(e){
        this.value = e.srcElement.value;
        setPen(penSize, e.srcElement.value);
    });
    document.getElementById('pen_size_selector').addEventListener('change', function(e){
        setPen(e.srcElement.value, penColor);
    });
    initPen();
})();

// Text stuff
(function () {
    let textSize;
    let textColor;

    function initText() {
        let init_size = localStorage.getItem('text/size') || 12;
        let init_color = localStorage.getItem('text/color') || '#000000';
        document.getElementById('text_size_selector').value = init_size;
        document.getElementById('text_color_selector').value = init_color;
        setText(init_size, init_color);
    }

    function setText(size, color) {
        let modified = false;
        if (textSize !== size) {
            modified = true;
            textSize = size;
            localStorage.setItem('text/size', textSize);
        }

        if (textColor !== color) {
            modified = true;
            textColor = color;
            localStorage.setItem('text/color', textColor);
        }

        if (modified) {
            UI.setText(textSize, textColor);
        }
    }
    document.getElementById('text_color_selector').addEventListener('change', function(e){
        setText(textSize, e.srcElement.value);
    });
    document.getElementById('text_size_selector').addEventListener('change', function(e){
        setText(e.srcElement.value, textColor);
    });
    initText();
})();

//TODO: Stretch goal, find a better solution to load/unload annotation. Maybe use session storage?
$(window).unload(function() {
    for(let i = 0; i < localStorage.length; i++){
        if(localStorage.key(i).includes('annotations')){
            localStorage.removeItem(localStorage.key(i));
        }
    }
});

