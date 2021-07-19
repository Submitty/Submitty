
if (PDFAnnotate.default) {
  PDFAnnotate = PDFAnnotate.default;
}

var loaded = sessionStorage.getItem('toolbar_loaded');
window.onbeforeunload = function() {
    sessionStorage.removeItem('toolbar_loaded');
};
//Toolbar stuff
function renderPDFToolbar() {
    document.getElementById('pdf_annotation_icons').addEventListener('click', handleToolbarClick);
    $('#zoom-custom').val(Number(localStorage.getItem('scale'))*100 || '100');
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
                    saveFile();
                    break;
                case 'toggle-annotations':
                    const hide_other = $('#toggle-annotations-btn').hasClass('hide-other-annotations');
                    $('#toggle-annotations-btn').toggleClass('hide-other-annotations');
                    if(hide_other) {
                        $('#toggle-annotations-btn').html('Show All Annotations <i class="fas fa-eye"></i>');
                    }
                    else {
                        $('#toggle-annotations-btn').html('Hide Other Annotations <i class="fas fa-eye-slash"></i>');
                    }
                    toggleOtherAnnotations(hide_other);
                    break;
                case 'zoomin':
                    zoom(Math.round((Number(window.RENDER_OPTIONS.scale) + 0.1) * 100));
                    break;
                case 'zoomout':
                    zoom(Math.round((Number(window.RENDER_OPTIONS.scale) - 0.1) * 100));
                    break;
                case 'rotate-right':
                    rotate(90);
                    break;
                case 'rotate-left':
                    rotate(-90);
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

    function rotate(amount) {
        window.RENDER_OPTIONS.rotate += amount;
        if (!window.RENDER_OPTIONS.studentPopup) {
          localStorage.setItem('rotate', window.RENDER_OPTIONS.rotate);
        }
        render(window.GENERAL_INFORMATION.gradeable_id, window.GENERAL_INFORMATION.user_id, window.GENERAL_INFORMATION.grader_id, window.GENERAL_INFORMATION.file_name, window.GENERAL_INFORMATION.file_path);
    }

    function clearCanvas(){
        if (confirm('Are you sure you want to clear all of your annotations and refresh the page?\n\nWARNING: This action CANNOT be undone.')) {
            localStorage.setItem(`${window.RENDER_OPTIONS.documentId}/${GENERAL_INFORMATION.grader_id}/annotations`, '[]');
            saveFile();
            window.location.reload();
        }
    }
    
    function saveFile(){
        let GENERAL_NFORMATION = window.GENERAL_INFORMATION;
        let url = buildCourseUrl(['gradeable', GENERAL_NFORMATION['gradeable_id'], 'pdf', 'annotations']);
        let annotation_layer = localStorage.getItem(`${window.RENDER_OPTIONS.documentId}/${GENERAL_INFORMATION.grader_id}/annotations`) || {};
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
        setActiveToolbarItem(e.target.getAttribute('value'));
    }

    $(document).on('click', function() {
        $('.selection-menu').hide();
    });

    $('.selection-menu').on('click', function(e) {
        e.stopPropagation();
    })

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
        shouldShow && $('#color_selector_menu').show();
        e.stopPropagation();
    }

    function sizeMenuToggle(e){
        let shouldShow = !$('#size_selector_menu').is(':visible');
        $('.selection-menu').hide();
        shouldShow && $('#size_selector_menu').show();
        e.stopPropagation();
    }

    function changeColor(e){
        setColor(e.target.value);
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
            setPen(e.target.value, penColor);
        });
        document.addEventListener('colorchange', function(e){
            setPen(penSize, e.target.value);
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

function zoom(zoom_level){
    if(isNaN(zoom_level)) {
        zoom_level = 100;
    }

    $('#zoom-custom').val(zoom_level);

    zoom_level = Math.min(Math.max(10, zoom_level), 500);
    zoom_level /= 100;

    window.RENDER_OPTIONS.scale = zoom_level;
    if (!window.RENDER_OPTIONS.studentPopup) {
        localStorage.setItem('scale', window.RENDER_OPTIONS.scale);
    }
    render(window.GENERAL_INFORMATION.gradeable_id, window.GENERAL_INFORMATION.user_id, window.GENERAL_INFORMATION.grader_id, window.GENERAL_INFORMATION.file_name, window.GENERAL_INFORMATION.file_path);
}