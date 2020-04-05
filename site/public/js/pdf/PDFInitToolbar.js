if (PDFAnnotate.default) {
  PDFAnnotate = PDFAnnotate.default;
}

var loaded = sessionStorage.getItem('toolbar_loaded');
window.onbeforeunload = function() {
    sessionStorage.removeItem('toolbar_loaded');
};
//Toolbar stuff
(function () {
    let active_toolbar = true;
    const debounce = (fn, time, ...args) => {
        if (active_toolbar) {
            fn(args);
            active_toolbar = false;
            setTimeout(function() {
                active_toolbar = true;
            }, time);
        }
    }
    function setActiveToolbarItem(option) {
        let selected = $('.tool-selected');
        let clicked_button = $("a[value="+option+"]");
        if(option != selected.attr('value')){
            //There are two classes for the icons; toolbar-action and toolbar-item.
            //toolbar-action are single use buttons such as download and clear
            //toolbar-item are continuous options such as pen, text, etc.
            if(!clicked_button.hasClass('toolbar-action')){
                $(selected[0]).removeClass('tool-selected');
                clicked_button.addClass('tool-selected');
                switch($(selected[0]).attr('value')){
                    case 'pen':
                        $('#file_content').css('overflow', 'auto');
                        $('#scroll_lock_mode').prop('checked', false);
                        PDFAnnotate.UI.disablePen();
                        break;
                    case 'eraser':
                        PDFAnnotate.UI.disableEraser();
                        break;
                    case 'cursor':
                        PDFAnnotate.UI.disableEdit();
                        break;
                    case 'text':
                        PDFAnnotate.UI.disableText();
                        break;
                }
                $('.selection_panel').hide();
            }
            switch(option){
                case 'pen':
                    currentTool = 'pen';
                    PDFAnnotate.UI.enablePen();
                    break;
                case 'eraser':
                    currentTool = 'eraser';
                    PDFAnnotate.UI.enableEraser();
                    break;
                case 'cursor':
                    PDFAnnotate.UI.enableEdit();
                    break;
                case 'clear':
                    clearCanvas();
                    break;
                case 'save':
                    debounce(saveFile, 500);
                    break;
                case 'zoomin':
                    debounce(zoom, 500, 'in');
                    break;
                case 'zoomout':
                    debounce(zoom, 500, 'out');
                    break;
                case 'zoomcustom':
                    debounce(zoom, 500, 'custom');
                    break;
                case 'rotate':
                    debounce(rotate, 500);
                    break;
                case 'text':
                    currentTool = 'text';
                    PDFAnnotate.UI.enableText();
                    break;
            }
        }
        else {
            //For color and size select
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

    function rotate(){
        window.RENDER_OPTIONS.rotate += 90;
        if (!window.RENDER_OPTIONS.studentPopup) {
          localStorage.setItem('rotate', window.RENDER_OPTIONS.rotate);
        }
        render(window.GENERAL_INFORMATION.gradeable_id, window.GENERAL_INFORMATION.user_id, window.GENERAL_INFORMATION.grader_id, window.GENERAL_INFORMATION.file_name, window.GENERAL_INFORMATION.file_path);
    }

    function zoom(option, custom_val){
        let zoom_flag = true;
        let zoom_level = window.RENDER_OPTIONS.scale;
        if(option == 'in'){
            zoom_level += 0.1;
        }
        else if(option == 'out'){
            zoom_level -= 0.1;
        }
        else {
            if(custom_val != null){
                zoom_level = custom_val/100;
            }
            else {
                zoom_flag = false;
            }
            $('#zoom_selection').toggle();
        }
        if(zoom_level > 10 || zoom_level < 0.25){
            alert("Cannot zoom more");
            return;
        }
        if(zoom_flag){
            $("a[value='zoomcustom']").text(parseInt(RENDER_OPTIONS.scale * 100) + "%");
            window.RENDER_OPTIONS.scale = zoom_level;
            if (!window.RENDER_OPTIONS.studentPopup) {
              localStorage.setItem('scale', window.RENDER_OPTIONS.scale);
            }
            render(window.GENERAL_INFORMATION.gradeable_id, window.GENERAL_INFORMATION.user_id, window.GENERAL_INFORMATION.grader_id, window.GENERAL_INFORMATION.file_name, window.GENERAL_INFORMATION.file_path);
        }
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
        let GENERAL_INFORMATION = window.GENERAL_INFORMATION;
        let annotation_layer = localStorage.getItem(`${window.RENDER_OPTIONS.documentId}/${GENERAL_INFORMATION.grader_id}/annotations`);
        let doc = null;
        let annotationPages = document.getElementsByClassName("annotationLayer");
        for(page in annotationPages){
            page.style.display = "none";
        }
        
        html2canvas(document.querySelector(`div#viewer`), {
            onrendered: function(canvas) {
                let ctx = canvas.getContext('2d');
                for (let i=0; i<NUM_PAGES; i++) {
                    let children = document.querySelector(`div#pageContainer${i+1} svg.annotationLayer`).children;
                    for (let j = 0; j < children.length; j++) {
                        let annotation = children[j];
                        if(annotation.getAttribute("data-pdf-annotate-type") == "drawing"){
                            let path2 = new Path2D(annotation.getAttribute("d"));
                            ctx.strokeStyle = annotation.getAttribute("stroke");
                            //ctx.stroke(path2);
                        }
                        if(annotation.getAttribute("data-pdf-annotate-type") == "textbox"){
                            let textChild = annotation.children[0];
                            //console.log(textChild);
                            ctx.font = textChild.getAttribute("font-size") + "px sans-serif";
                            ctx.fillStyle = textChild.getAttribute("fill");
                            let text = annotation.textContent;
                            if(text != null){
                                ctx.fillText(text, textChild.getAttribute("x"), textChild.getAttribute("y"));
                            }
                        }
                    }
                }
                let imgData = canvas.toDataURL(
                    'image/png');              
                doc = new jsPDF('p', 'mm');
                doc.addImage(imgData, 'PNG', 10, 10);
                //doc.save('sample-file.pdf');
                var fd = new FormData();
                var pdf = btoa(doc.output());
                fd.append('annotation_layer', JSON.stringify(annotation_layer));
                fd.append('GENERAL_INFORMATION', JSON.stringify(GENERAL_INFORMATION));
                fd.append('csrf_token', csrfToken);
                fd.append('pdf', pdf);
                
                /*var a = document.createElement('a');
                // toDataURL defaults to png, so we need to request a jpeg, then convert for file download.
                a.href = canvas.toDataURL("image/png");
                a.download = 'somefilename.png';
                a.click();*/
                let url = buildCourseUrl(['gradeable', GENERAL_INFORMATION['gradeable_id'], 'pdf', 'annotations']);
                console.log(typeof annotation_layer);
                for(page in annotationPages){
                    page.style.display = "none";
                }
                $.ajax({
                    type: 'POST',
                    url: url,
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(data){
                        let response = JSON.parse(data);
                        if(response.status == "success"){
                            $('#save_status').text("Saved");
                            $('#save_status').css('color', 'black');
                        }
                        else {
                            alert(data.message);
                        }
                    },
                    error: function(){
                        alert("Something went wrong, please contact a administrator.");
                    }
                });
            }
        });
    }

    function handleToolbarClick(e){
        setActiveToolbarItem(e.target.getAttribute('value'));
    }
    if(!loaded){
        document.getElementById('pdf_annotation_icons').addEventListener('click', handleToolbarClick);
    }
    sessionStorage.setItem('toolbar_loaded', true);
})();

// Color/size selection
(function () {
    let main_color;
    function initColors(){
        let init_color = localStorage.getItem('main_color') || '#ff0000';
        document.getElementById('color_selector').style.backgroundColor = init_color;
        setColor(init_color);
    }

    function colorMenuToggle(e){
        let shouldShow = !$('#color_selector_menu').is(':visible');
        $('.selection-menu').hide();
        shouldShow && $('#color_selector_menu').toggle();
    }

    function sizeMenuToggle(){
        let shouldShow = !$('#size_selector_menu').is(':visible');
        $('.selection-menu').hide();
        shouldShow && $('#size_selector_menu').toggle();
    }

    function changeColor(e){
        setColor(e.srcElement.getAttribute('value'))
    }

    function setColor(color){
        if(main_color != color){
            main_color = color;
            localStorage.setItem('main_color', color);
            document.getElementById('color_selector').style.backgroundColor = color;
        }
    }
    if(!loaded){
        document.getElementById("color_selector").addEventListener('click', colorMenuToggle);
        document.getElementById("size_selector").addEventListener('click', sizeMenuToggle);
        document.addEventListener('colorchange', changeColor);
    }
    initColors();
})();

// Pen stuff
(function () {
    let penSize;
    let penColor;
    let scrollLock;
    function initPen() {
        let init_size = localStorage.getItem('pen/size') || 3;
        let init_color = localStorage.getItem('main_color') || '#FF0000';
        document.getElementById('pen_size_selector').value = init_size;
        document.getElementById('pen_size_value').value = init_size;
        if($('#scroll_lock_mode').is(':checked')) {
            scrollLock = true;
        }

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
        }

        if (modified && scrollLock) {
            $('#file_content').css('overflow', 'hidden');
        }

        if (modified) {
            PDFAnnotate.UI.setPen(penSize, penColor);
        }
    }

    document.getElementById('pen_size_selector').addEventListener('change', function(e){
        let value = e.target.value ? e.target.value : e.srcElement.value;
        setPen(value, penColor);
    });
    document.addEventListener('colorchange', function(e){
        setPen(penSize, e.srcElement.getAttribute('value'));
    });
    initPen();
})();

// Text stuff
(function () {
    let textSize;
    let textColor;

    function initText() {
        let init_size = localStorage.getItem('text/size') || 12;
        let init_color = localStorage.getItem('main_color') || '#FF0000';
        document.getElementById('text_size_selector').value = init_size;
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
        }

        if (modified) {
            PDFAnnotate.UI.setText(textSize, textColor);
        }
    }
    if(!loaded){
        document.addEventListener('colorchange', function(e){
            setText(textSize, e.srcElement.getAttribute('value'));
        });
        document.getElementById('text_size_selector').addEventListener('change', function(e) {
            let value = e.target.value ? e.target.value : e.srcElement.value;
            setText(value, textColor);
        });
    }
    initText();
})();
