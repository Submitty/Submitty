
if (PDFAnnotate.default) {
  PDFAnnotate = PDFAnnotate.default;
}

var loaded = sessionStorage.getItem('toolbar_loaded');
window.onbeforeunload = function() {
    sessionStorage.removeItem('toolbar_loaded');
};
//Toolbar stuff
function renderPDFToolbar() {
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
    document.getElementById('pdf_annotation_icons').addEventListener('click', handleToolbarClick);
    sessionStorage.setItem('toolbar_loaded', true);
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
                        $('#file-content').css('overflow', 'auto');
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
                    currentTool = 'cursor';
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
                // case 'clear':
                //     const clear = confirm("Are you sure you want to clear all annotations on this page?\n\nWARNING: This action CANNOT be undone.");
                //     if (clear) {
                //         for (let i = 0; i < localStorage.length; i++) {
                //             if (localStorage.key(i).includes('annotations')) {
                //                 localStorage.setItem(localStorage.key(i), '{}');
                //             }
                //         }
                //     }
                //     debounce(saveFile, 500);
                //     break;
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
        let GENERAL_NFORMATION = window.GENERAL_INFORMATION;
        let url = buildCourseUrl(['gradeable', GENERAL_NFORMATION['gradeable_id'], 'pdf', 'annotations']);
        let annotation_layer = localStorage.getItem(`${window.RENDER_OPTIONS.documentId}/${GENERAL_INFORMATION.grader_id}/annotations`) || {};
        console.log('saving annotations...');
        // console.log('annotation_layer', annotation_layer);
        // console.log(JSON.parse(annotation_layer));
        // console.log([...(JSON.parse(annotation_layer).map(o => Object.assign({}, o)))]);
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                annotation_layer,
                GENERAL_INFORMATION,
                'csrf_token': csrfToken
            },
            success: function(data){
                let response = JSON.parse(data);
                if(response.status === "success"){
                    $('#save_status').text("Saved");
                    $('#save_status').css('color', 'inherit');
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


    function handleToolbarClick(e){
        console.log('toolbar click...', e)
        setActiveToolbarItem(e.target.getAttribute('value'));
    }

// Color/size selection
    function initColors(){
        document.getElementById("color_selector").addEventListener('click', colorMenuToggle);
        document.getElementById("size_selector").addEventListener('click', sizeMenuToggle);
        document.addEventListener('colorchange', changeColor);
        let init_color = localStorage.getItem('main_color') || "#000000";
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
        localStorage.setItem('main_color', color);
        document.getElementById('color_selector').style.backgroundColor = color;
    }
// Pen stuff
    let penSize = 3;
    let penColor = '#FF0000';
    let scrollLock= false;
    function initPen() {
        let init_size = localStorage.getItem('pen/size') || 5.0;
        let init_color = localStorage.getItem('main_color') || "#000000";
        document.getElementById('pen_size_selector').value = init_size;
        document.getElementById('pen_size_value').value = init_size;
        if($('#scroll_lock_mode').is(':checked')) {
            scrollLock = true;
        }

        setPen(init_size, init_color);
        
        document.getElementById('pen_size_selector').addEventListener('change', function(e){
            setPen(e.target.value || e.srcElement.value, penColor);
        });
        document.addEventListener('colorchange', function(e){
            setPen(penSize, e.srcElement.getAttribute('value'));
        });
    }

    function setPen(pen_size, pen_color) {
        penSize = pen_size || 5;
        penColor = pen_color || '#000000';
        
        if (scrollLock) {
            $('#file-content').css('overflow', 'hidden');
        }
        localStorage.setItem('pen/size', pen_size);
        localStorage.setItem('main_color', pen_color);
        PDFAnnotate.UI.setPen(pen_size, pen_color);
    }

// Text stuff
    let textSize = 12;
    let textColor = '#FF0000';
    function initText() {
        let init_size = localStorage.getItem('text/size') || 12;
        let init_color = localStorage.getItem('main_color') || "#000000";
        document.getElementById('text_size_selector').value = init_size;
        setText(init_size, init_color);
        document.addEventListener('colorchange', function(e){
            setText(textSize, e.srcElement.getAttribute('value'));
        });
        document.getElementById('text_size_selector').addEventListener('change', function(e) {
            setText(e.target.value || e.srcElement.value, textColor);
        });
    }

    function setText(text_size, text_color) {
        text_size = text_size || 12;
        text_color = text_color || '#000000';
        textSize = text_size;
        textColor = text_color;
        localStorage.setItem('text/size', text_size);
        localStorage.setItem('main_color', text_color);
        PDFAnnotate.UI.setText(text_size, text_color);
    }
    initColors();
    initPen();
    initText();
}
