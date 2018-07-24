const { UI } = PDFAnnotate;
let documentId = '';
let PAGE_HEIGHT;
let RENDER_OPTIONS = {
    documentId,
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

function render(gradeable_id, user_id, file_name) {
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
                    let pages = document.getElementsByClassName('page');
                    for(let i = 0; i < pages.length; i++){
                        pages[i].addEventListener('mousedown', function(){
                            //Makes sure the panel don't move when writing on it.
                            $("#submission_browser").draggable('disable');
                        });
                        pages[i].addEventListener('mouseup', function(){
                            $("#submission_browser").draggable('enable');
                        });
                    }

                })
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
                    $("#pdf_annotation_icons").bind("drag", function(e, ui){
                        let panel = $("#pen_selection");
                        let new_top = $("#pdf_annotation_icons").css('top');
                        new_top = parseInt(new_top.slice(0, -2)) + 30;
                        new_top = new_top + "px";
                        let new_left = $("#pdf_annotation_icons").css('left');
                        panel.css({top: new_top, left: new_left});
                    });
                    break;
                case 'text':

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
        let url = buildUrl({'component': 'grading','page': 'electronic', 'action': 'save_pdf_annotation'});
        let annotation_layer = localStorage.getItem(`${RENDER_OPTIONS.documentId}/annotations`);
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                annotation_layer,
                GENERAL_INFORMATION
            },
            success: function(data){
                alert("Annotation successfully saved!");
            }
        });
    }

    function handleToolbarClick(e){
        setActiveToolbarItem(e.target.getAttribute('value'));
    }
    document.getElementById('pdf_annotation_bar').addEventListener('click', handleToolbarClick);
})();

// Pen stuff
(function () {
    let penSize;
    let penColor;

    function initPen() {
        //TODO: Add size selector
        // let size = document.querySelector('.toolbar .pen-size');
        // for (let i=0; i<20; i++) {
        //     size.appendChild(new Option(i+1, i+1));
        // }

        setPen(
            localStorage.getItem(`${RENDER_OPTIONS.documentId}/pen/size`) || 1,
            localStorage.getItem(`${RENDER_OPTIONS.documentId}/pen/color`) || '#000000'
        );
        //TODO: Add color selector
        // initColorPicker(document.querySelector('.pen-color'), penColor, function (value) {
        //     setPen(penSize, value);
        // });
    }

    function setPen(size, color) {
        let modified = false;

        if (penSize !== size) {
            modified = true;
            penSize = size;
            // localStorage.setItem(`${RENDER_OPTIONS.documentId}/pen/size`, penSize);
            // document.querySelector('.toolbar .pen-size').value = penSize;
        }

        if (penColor !== color) {
            modified = true;
            penColor = color;
            // localStorage.setItem(`${RENDER_OPTIONS.documentId}/pen/color`, penColor);

            // let selected = document.querySelector('.toolbar .pen-color.color-selected');
            // if (selected) {
            //     selected.classList.remove('color-selected');
            //     selected.removeAttribute('aria-selected');
            // }
            //
            // selected = document.querySelector(`.toolbar .pen-color[data-color="${color}"]`);
            // if (selected) {
            //     selected.classList.add('color-selected');
            //     selected.setAttribute('aria-selected', true);
            // }
        }

        if (modified) {
            UI.setPen(penSize, penColor);
        }
    }

    function handlePenSizeChange(e) {
        setPen(e.target.value, penColor);
    }

    // document.querySelector('.toolbar .pen-size').addEventListener('change', handlePenSizeChange);

    initPen();
})();

// Text stuff
(function () {
    let textSize;
    let textColor;

    function initText() {
        // let size = document.querySelector('.toolbar .text-size');
        // [8, 9, 10, 11, 12, 14, 18, 24, 30, 36, 48, 60, 72, 96].forEach((s) => {
        //     size.appendChild(new Option (s, s));
        // });

        setText(10, '#000000');

        // initColorPicker(document.querySelector('.text-color'), textColor, function (value) {
        //     setText(textSize, value);
        // });
    }

    function setText(size, color) {
        let modified = false;

        if (textSize !== size) {
            modified = true;
            textSize = size;
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/text/size`, textSize);
            // document.querySelector('.toolbar .text-size').value = textSize;
        }

        if (textColor !== color) {
            modified = true;
            textColor = color;
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/text/color`, textColor);

            // let selected = document.querySelector('.toolbar .text-color.color-selected');
            // if (selected) {
            //     selected.classList.remove('color-selected');
            //     selected.removeAttribute('aria-selected');
            // }

            // selected = document.querySelector(`.toolbar .text-color[data-color="${color}"]`);
            // if (selected) {
            //     selected.classList.add('color-selected');
            //     selected.setAttribute('aria-selected', true);
            // }

        }

        // if (modified) {
            UI.setText(textSize, textColor);
        // }
    }

    function handleTextSizeChange(e) {
        setText(e.target.value, textColor);
    }

    // document.querySelector('.toolbar .text-size').addEventListener('change', handleTextSizeChange);

    initText();
})();

//TODO: Stretch goal, find a better solution to load/unload annotation. Maybe use session storage?
$(window).unload(function() {
    localStorage.removeItem(`${RENDER_OPTIONS.documentId}/annotations`);
});

