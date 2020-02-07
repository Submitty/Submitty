function isYellowMarker(marker) {
    return marker.css.toLowerCase().indexOf("#ffff00") != -1;
}

function isOrangeMarker(marker) {
    return marker.css.toLowerCase().indexOf("#ffa500") != -1;
}

function updateCssForMark(mark, prevColor, toColor) {
    mark.css = "background:" + toColor;
    clickedMark.className = "red_plag";
    clickedMark.attributes = {"data_prev_color": prevColor};                    
}

function colorReset(allMarks, clickedMark, updateClickedCallback) {
    allMarks.forEach(m => {
        if(m.className === "red_plag") {
            m.css = "background: " + m.attributes["data_prev_color"];
            m.className = "";
        }
    });
    updateClickedCallback(clickedMark,);
}

function updatePanesOnOrangeClick(leftClickedMarker, editor0, editor1) {
    var marks_editor2 = editor1.getAllMarks();
    var setLeft = false;
    marks_editor2.forEach(mark => {
        var rightMarkerData = mark.find();

        if(mark.attributes.data_start == leftClickedMark.attributes.data_start && mark.attributes.data_end == leftClickedMark.attributes.data_end) {
        
            if (!setLeft) {
                clickedMark.css = "background:#FF0000";
                clickedMark.className = "red_plag";
                clickedMark.attributes = {"data_prev_color": "#ffa500"};
                editor0.refresh();
                setLeft = true;
            } 

            mark.css = "background: #FF0000";
            mark.className = 'red_plag';
            mark.attributes = {"data_color_prev": "#ffa500"};
            editor1.refresh();
        }
    });
}

function setUpLeftPane(editor0, editor1) {
    editor0.getWrapperElement().onmousedown = function(e) {
        var lineCh = editor0.coordsChar({ left: e.clientX, top: e.clientY });
        var markers = editor0.findMarksAt(lineCh);

        // Did not select a marker 
        if (markers.length === 0) { 
            return; 
        }

        // Only grab the first one if there is overlap...
        var lineData = markers[0].find();
        var clickedMark = markers[0];

        if(isYellowMarker(clickedMark)) {
            var allMarks = editor0.getAllMarks();
            colorReset(allMarks, clickedMark, updateClickedMarkToRed);
            editor0.refresh();

            //getMatchesForClickedMatch("{$gradeable_id}", event, lineData.from, lineData.to, "code_box_1", "orange", null, "", "");
        } else if(isOrangeMarker(clickedMark)) {
            // In this case we want to update the right side as well...
            updatePanesOnOrangeClick(clickedMark, editor0, editor1);
        }

    }
}

function setUpPlagView() {
    var editor0 = $('.CodeMirror')[0].CodeMirror;
    var editor1 = $('.CodeMirror')[1].CodeMirror;


}

var form = $("#users_with_plagiarism");
    var code_user_1 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });
    var code_user_2 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });

    code_user_2.setSize("100%", "100%");
    code_user_1.setSize("100%", "100%");
    $('[name="user_id_1"]', form).change(function(){
        setUserSubmittedCode('{$gradeable_id}','user_id_1');
    });
    $('[name="version_user_1"]', form).change(function(){
        setUserSubmittedCode('{$gradeable_id}', 'version_user_1');
    });
    $('[name="user_id_2"]', form).change(function(){
        setUserSubmittedCode('{$gradeable_id}', 'user_id_2');
    });
    $(document).click(function() {
        if($('#popup_to_show_matches_id').css('display') == 'block'){
            $('#popup_to_show_matches_id').css('display', 'none');
        }
    });