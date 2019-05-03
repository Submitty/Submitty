const { UI } = PDFAnnotate;
let documentId = 'example.pdf';
let PAGE_HEIGHT;
let RENDER_OPTIONS = {
    documentId,
    pdfDocument: null,
    scale: parseFloat(localStorage.getItem(`${documentId}/scale`), 10) || 1.33,
    rotate: parseInt(localStorage.getItem(`${documentId}/rotate`), 10) || 0
};

PDFAnnotate.setStoreAdapter(new PDFAnnotate.LocalStoreAdapter());
PDFJS.workerSrc = './shared/pdf.worker.js';

// Render stuff
let NUM_PAGES = 0;
let renderedPages = [];
let okToRender = false;
document.getElementById('content-wrapper').addEventListener('scroll', function (e) {
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

function render() {
    $.ajax({
        type: 'POST',
        url:'http://192.168.56.101/index.php?semester=f18&course=sample&component=misc&page=base64_encode_pdf',
        // url: buildUrl({'component': 'misc', 'page': 'base64_encode_pdf'}),
        data: {
            gradeable_id: 'open_homework',
            user_id: 'instructor',
            file_name: 'toy_eb.pdf'
        },
        success: function(data){
            RENDER_OPTIONS.documentId = 'toy_eb.pdf';
            documentId = 'toy_eb'.pdf;
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
                });
            });
        }
    });
}
render();

// Hotspot color stuff
(function () {
    let hotspotColor = localStorage.getItem(`${RENDER_OPTIONS.documentId}/hotspot/color`) || 'darkgoldenrod';
    let currentTarget = undefined;

    function handleAnnotationClick(target) {
        let type = target.getAttribute('data-pdf-annotate-type');
        if (['fillcircle', 'arrow'].indexOf(type) === -1) {
            return; // nothing to do
        }
        currentTarget = target;
        hotspotColor = currentTarget.getAttribute('stroke');

        UI.setArrow(10, hotspotColor);
        UI.setCircle(10, hotspotColor);

        let a = document.querySelector('.hotspot-color .color');
        if (a) {
            a.setAttribute('data-color', hotspotColor);
            a.style.background = hotspotColor;
        }
    }

    function handleAnnotationBlur(target) {
        if (currentTarget === target) {
            currentTarget = undefined;
        }
    }

    initColorPicker(document.querySelector('.hotspot-color'), hotspotColor, function (value) {
        if (value === hotspotColor) {
            return; // nothing to do
        }
        localStorage.setItem(`${RENDER_OPTIONS.documentId}/hotspot/color`, value);
        hotspotColor = value;

        UI.setArrow(10, hotspotColor);
        UI.setCircle(10, hotspotColor);

        if (!currentTarget) {
            return; // nothing to do
        }

        let type = currentTarget.getAttribute('data-pdf-annotate-type');
        let annotationId = currentTarget.getAttribute('data-pdf-annotate-id');
        if (['fillcircle', 'arrow'].indexOf(type) === -1) {
            return; // nothing to do
        }

        // update target
        currentTarget.setAttribute('stroke', hotspotColor);
        currentTarget.setAttribute('fill', hotspotColor);

        // update annotation
        PDFAnnotate.getStoreAdapter().getAnnotation(documentId, annotationId).then((annotation) => {
            annotation.color = hotspotColor;
            PDFAnnotate.getStoreAdapter().editAnnotation(documentId, annotationId, annotation);
        });
    });

    UI.addEventListener('annotation:click', handleAnnotationClick);
    UI.addEventListener('annotation:blur', handleAnnotationBlur);
})();

// Text stuff
(function () {
    let textSize;
    let textColor;

    function initText() {
        let size = document.querySelector('.toolbar .text-size');
        [8, 9, 10, 11, 12, 14, 18, 24, 30, 36, 48, 60, 72, 96].forEach((s) => {
            size.appendChild(new Option (s, s));
        });

        setText(
            localStorage.getItem(`${RENDER_OPTIONS.documentId}/text/size`) || 10,
            localStorage.getItem(`${RENDER_OPTIONS.documentId}/text/color`) || '#000000'
        );

        initColorPicker(document.querySelector('.text-color'), textColor, function (value) {
            setText(textSize, value);
        });
    }

    function setText(size, color) {
        let modified = false;

        if (textSize !== size) {
            modified = true;
            textSize = size;
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/text/size`, textSize);
            document.querySelector('.toolbar .text-size').value = textSize;
        }

        if (textColor !== color) {
            modified = true;
            textColor = color;
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/text/color`, textColor);

            let selected = document.querySelector('.toolbar .text-color.color-selected');
            if (selected) {
                selected.classList.remove('color-selected');
                selected.prop('aria-selected', false);
            }

            selected = document.querySelector(`.toolbar .text-color[data-color="${color}"]`);
            if (selected) {
                selected.classList.add('color-selected');
                selected.prop('aria-selected', true);
            }

        }

        if (modified) {
            UI.setText(textSize, textColor);
        }
    }

    function handleTextSizeChange(e) {
        setText(e.target.value, textColor);
    }

    document.querySelector('.toolbar .text-size').addEventListener('change', handleTextSizeChange);

    initText();
})();

// Pen stuff
(function () {
    let penSize;
    let penColor;

    function initPen() {
        let size = document.querySelector('.toolbar .pen-size');
        for (let i=0; i<20; i++) {
            size.appendChild(new Option(i+1, i+1));
        }

        setPen(
            localStorage.getItem(`${RENDER_OPTIONS.documentId}/pen/size`) || 1,
            localStorage.getItem(`${RENDER_OPTIONS.documentId}/pen/color`) || '#000000'
        );

        initColorPicker(document.querySelector('.pen-color'), penColor, function (value) {
            setPen(penSize, value);
        });
    }

    function setPen(size, color) {
        let modified = false;

        if (penSize !== size) {
            modified = true;
            penSize = size;
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/pen/size`, penSize);
            document.querySelector('.toolbar .pen-size').value = penSize;
        }

        if (penColor !== color) {
            modified = true;
            penColor = color;
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/pen/color`, penColor);

            let selected = document.querySelector('.toolbar .pen-color.color-selected');
            if (selected) {
                selected.classList.remove('color-selected');
                selected.removeAttribute('aria-selected');
            }

            selected = document.querySelector(`.toolbar .pen-color[data-color="${color}"]`);
            if (selected) {
                selected.classList.add('color-selected');
                selected.setAttribute('aria-selected', true);
            }
        }

        if (modified) {
            UI.setPen(penSize, penColor);
        }
    }

    function handlePenSizeChange(e) {
        setPen(e.target.value, penColor);
    }

    document.querySelector('.toolbar .pen-size').addEventListener('change', handlePenSizeChange);

    initPen();
})();

// Toolbar buttons
(function () {
    let tooltype = localStorage.getItem(`${RENDER_OPTIONS.documentId}/tooltype`) || 'cursor';
    if (tooltype) {
        setActiveToolbarItem(tooltype, document.querySelector(`.toolbar button[data-tooltype=${tooltype}]`));
    }

    function setActiveToolbarItem(type, button) {
        let active = document.querySelector('.toolbar button.active');
        if (active) {
            active.classList.remove('active');

            switch (tooltype) {
                case 'cursor':
                    UI.disableEdit();
                    break;
                case 'draw':
                    UI.disablePen();
                    break;
                case 'arrow':
                    UI.disableArrow();
                    break;
                case 'text':
                    UI.disableText();
                    break;
                case 'point':
                    UI.disablePoint();
                    break;
                case 'area':
                case 'highlight':
                case 'strikeout':
                    UI.disableRect();
                    break;
                case 'circle':
                case 'emptycircle':
                case 'fillcircle':
                    UI.disableCircle();
                    break;
            }
        }

        if (button) {
            button.classList.add('active');
        }
        if (tooltype !== type) {
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/tooltype`, type);
        }
        tooltype = type;

        switch (type) {
            case 'cursor':
                UI.enableEdit();
                break;
            case 'draw':
                UI.enablePen();
                break;
            case 'arrow':
                UI.enableArrow();
                break;
            case 'text':
                UI.enableText();
                break;
            case 'point':
                UI.enablePoint();
                break;
            case 'area':
            case 'highlight':
            case 'strikeout':
                UI.enableRect(type);
                break;
            case 'circle':
            case 'emptycircle':
            case 'fillcircle':
                UI.enableCircle(type);
                break;
        }
    }

    function handleToolbarClick(e) {
        if (e.target.nodeName === 'BUTTON') {
            setActiveToolbarItem(e.target.getAttribute('data-tooltype'), e.target);
        }
    }

    document.querySelector('.toolbar').addEventListener('click', handleToolbarClick);
})();

// Scale/rotate
(function () {
    function setScaleRotate(scale, rotate) {
        scale = parseFloat(scale, 10);
        rotate = parseInt(rotate, 10);

        if (RENDER_OPTIONS.scale !== scale || RENDER_OPTIONS.rotate !== rotate) {
            RENDER_OPTIONS.scale = scale;
            RENDER_OPTIONS.rotate = rotate;

            localStorage.setItem(`${RENDER_OPTIONS.documentId}/scale`, RENDER_OPTIONS.scale);
            localStorage.setItem(`${RENDER_OPTIONS.documentId}/rotate`, RENDER_OPTIONS.rotate % 360);

            render();
        }
    }

    function handleScaleChange(e) {
        setScaleRotate(e.target.value, RENDER_OPTIONS.rotate);
    }

    function handleRotateCWClick() {
        setScaleRotate(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate + 90);
    }

    function handleRotateCCWClick() {
        setScaleRotate(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate - 90);
    }

    document.querySelector('.toolbar select.scale').value = RENDER_OPTIONS.scale;
    document.querySelector('.toolbar select.scale').addEventListener('change', handleScaleChange);
    document.querySelector('.toolbar .rotate-ccw').addEventListener('click', handleRotateCCWClick);
    document.querySelector('.toolbar .rotate-cw').addEventListener('click', handleRotateCWClick);
})();

// Clear toolbar button
(function () {
    function handleClearClick(e) {
        if (confirm('Are you sure you want to clear annotations?')) {
            for (let i=0; i<NUM_PAGES; i++) {
                document.querySelector(`div#pageContainer${i+1} svg.annotationLayer`).innerHTML = '';
            }

            localStorage.removeItem(`${RENDER_OPTIONS.documentId}/annotations`);
        }
    }
    document.querySelector('a.clear').addEventListener('click', handleClearClick);
})();

//Handles Download
(function() {
    function handleDownloadClick(e){
        alert('hi2');
    }
    document.querySelector('a.download').addEventListener('click', handleDownloadClick);
})();

// Comment stuff
(function (window, document) {
    let commentList = document.querySelector('#comment-wrapper .comment-list-container');
    let commentForm = document.querySelector('#comment-wrapper .comment-list-form');
    let commentText = commentForm.querySelector('input[type="text"]');

    function supportsComments(target) {
        let type = target.getAttribute('data-pdf-annotate-type');
        return ['point', 'highlight', 'area'].indexOf(type) > -1;
    }

    function insertComment(comment) {
        let child = document.createElement('div');
        child.className = 'comment-list-item';
        child.innerHTML = twitter.autoLink(twitter.htmlEscape(comment.content));

        commentList.appendChild(child);
    }

    function handleAnnotationClick(target) {
        if (supportsComments(target)) {
            let documentId = target.parentNode.getAttribute('data-pdf-annotate-document');
            let annotationId = target.getAttribute('data-pdf-annotate-id');

            PDFAnnotate.getStoreAdapter().getComments(documentId, annotationId).then((comments) => {
                commentList.innerHTML = '';
                commentForm.style.display = '';
                commentText.focus();

                commentForm.onsubmit = function () {
                    PDFAnnotate.getStoreAdapter().addComment(documentId, annotationId, commentText.value.trim())
                        .then(insertComment)
                        .then(() => {
                            commentText.value = '';
                            commentText.focus();
                        });

                    return false;
                };

                comments.forEach(insertComment);
            });
        }
    }

    function handleAnnotationBlur(target) {
        if (supportsComments(target)) {
            commentList.innerHTML = '';
            commentForm.style.display = 'none';
            commentForm.onsubmit = null;

            insertComment({content: 'No comments'});
        }
    }

    UI.addEventListener('annotation:click', handleAnnotationClick);
    UI.addEventListener('annotation:blur', handleAnnotationBlur);

    UI.setArrow(10, 'darkgoldenrod');
    UI.setCircle(10, 'darkgoldenrod')

})(window, document);
