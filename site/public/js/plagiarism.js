const YELLOW = "#ffff00";
const ORANGE = "#ffa500";
const RED    = "#ff0000";
const BLUE   = "#89CFF0";
var editor0 = null;
var editor1 = null;
var form = null;
var si = null;

function isColoredMarker(marker, color) {
    return marker.css.toLowerCase().indexOf(color) != -1;
}

function colorEditors(data) {
    window.si = data.si;
    for(var users_color in data.ci) {
    	var editor = users_color == 1 ? editor0 : editor1;
    	editor.operation(() => {
        	for(var pos in data.ci[users_color]) {
            	var element = data.ci[users_color][pos];
            	editor.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_prev_color": element[4], "data_start": element[7], "data_end": element[8]}, css: "background: " + element[4]});
        	}
    	});
    }
}

function updatePanesOnOrangeClick(leftClickedMarker, editor0, editor1) {
    var marks_editor2 = editor1.getAllMarks();
    editor1.operation( () => {
    	marks_editor2.forEach(mark => {
	        var rightMarkerData = mark.find();
	        if(mark.attributes.data_start == leftClickedMarker.attributes.data_start && mark.attributes.data_end == leftClickedMarker.attributes.data_end) {
	            mark.css = "background: #FF0000";
	            mark.attributes = {"data_color_prev": ORANGE, "data_current_color": RED};
	        }
    	});
	});
	leftClickedMarker.css = "background:#FF0000";
	leftClickedMarker.attributes = {"data_prev_color": ORANGE, "data_current_color": RED};
	editor0.refresh();
}

function setUpLeftPane(gradeable_id) {
    editor0.getWrapperElement().onmouseup = function(e) {
        var lineCh = editor0.coordsChar({ left: e.clientX, top: e.clientY });
        var markers = editor0.findMarksAt(lineCh);
        // Did not select a marker 
        if (markers.length === 0) { 
            return; 
        }

        // Only grab the first one if there is overlap...
        var lineData = markers[0].find();
        var clickedMark = markers[0];
        if(isColoredMarker(clickedMark, YELLOW)) {
            var user_id_1 = $('[name="user_id_1"]', form).val();
            var user_1_version = $('[name="version_user_1"]', form).val();
            getMatchesListForClick(gradeable_id, user_id_1, user_1_version, lineData.from);
        } else if(isColoredMarker(clickedMark, ORANGE)) {
            // In this case we want to update the right side as well...
            // Needs work...
            //updatePanesOnOrangeClick(clickedMark, editor0, editor1);
        } else {
            if($('#popup_to_show_matches_id').css('display') == 'block'){
                $('#popup_to_show_matches_id').css('display', 'none');
            }
        }

    }
}



function getMatchesListForClick(gradeable_id, user_id_1, user_1_version, user_1_match_start) {
    var user_matches = window.si[`${user_1_match_start.line}_${user_1_match_start.ch}`];
    var to_append = '';
    $.each(user_matches, function(i, match) {
        var res = match.split('_');
        to_append += '<li class="ui-menu-item"><div tabindex="-1" class="ui-menu-item-wrapper" onclick="clearCodeEditorsAndUpdateSelection(' + `'${gradeable_id}', '${user_id_1}', '${user_1_version}', '${res[0]}', '${res[1]}'); $('#popup_to_show_matches_id').css('display', 'none');"` + '>' + res[0] + '(version:'+res[1]+')</div></li>'; 
    }); 
    to_append = $.parseHTML(to_append); 
    $("#popup_to_show_matches_id").empty().append(to_append);   
    var x = event.pageX;    
    var y = event.pageY;    
    $('#popup_to_show_matches_id').css('display', 'block'); 
    var width = $('#popup_to_show_matches_id').width(); 
    $('#popup_to_show_matches_id').css('top', y+5); 
    $('#popup_to_show_matches_id').css('left', x-width/2.00);
}

function setUpPlagView(gradeable_id) {

	form = $("#users_with_plagiarism");
    editor0 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });
    editor1 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });

    editor0.setSize("100%", "100%");
    editor1.setSize("100%", "100%");

    $('[name="user_id_1"]', form).change(function(){
        setCodeInEditor(gradeable_id,'user_id_1');
    });
    $('[name="version_user_1"]', form).change(function(){
        setCodeInEditor(gradeable_id, 'version_user_1');
    });
    $('[name="user_id_2"]', form).change(function(){
        setCodeInEditor(gradeable_id, 'user_id_2');
    });
    setUpLeftPane(gradeable_id);
}

function requestAjaxData(url, f, es) {
    $.ajax({
        url: url,
        success: function(data) {
            data = JSON.parse(data);
            if(data.error){
                alert(data.error);
                return;
            }
            f(data, es);
        },
        error: function(e) {
            alert("Error occured when requesting via ajax. Please refresh the page and try again.");
        }
    });
}

function createRightUsersList(data, form) {
    var append_options='<option value="">None</option>';
    $.each(data, function(i,users){
        append_options += '<option value="{&#34;user_id&#34;:&#34;'+ users[0]+'&#34;,&#34;version&#34;:'+ users[1] +'}">'+ users[2]+ ' '+users[3]+' &lt;'+users[0]+'&gt; (version:'+users[1]+')</option>';
    });
    $('[name="user_id_2"]', form).find('option').remove().end().append(append_options).val('');
}

function createLeftUserVersionDropdown(version_data, active_version_user_1, max_matching_version, code_version_user_1) {
    var append_options='<option value="">None</option>';
    $.each(version_data, function(i,version_to_append){
        if(version_to_append == active_version_user_1 && version_to_append == max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)(Max Match)</option>';
        }
        if(version_to_append == active_version_user_1 && version_to_append != max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)</option>';
        }
        if(version_to_append != active_version_user_1 && version_to_append == max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Max Match)</option>';
        }

        if(version_to_append != active_version_user_1 && version_to_append != max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +'</option>';
        }
    });
    $('[name="version_user_1"]', form).find('option').remove().end().append(append_options).val(code_version_user_1);

}

function clearCodeEditorsAndUpdateSelection(gradeable_id, user_id_1, version_id_1, user_id_2 = null, version_id_2 = null) {
    const f = function(data, secondEditor) {
        editor0.getDoc().setValue(data.display_code1);
        editor0.refresh();
        createLeftUserVersionDropdown(data.all_versions_user_1, data.active_version_user_1, data.max_matching_version, data.code_version_user_1);
        if (secondEditor) {
            editor1.getDoc().setValue(data.display_code2);
            editor1.refresh();
        } else {
            editor1.getDoc().setValue('');
        }
        colorEditors(data);
    };
    var url = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'concat']) + `?user_id_1=${user_id_1}&version_user_1=${version_id_1}`;
    var es = false;
    if (user_id_2 != null) {
        url += `&user_id_2=${user_id_2}&version_user_2=${version_id_2}`;
        es = true;
    } else {
        var url2 = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'match']) + `?user_id_1=${user_id_1}&version_user_1=${version_id_1}`;
        const f2 = function(data, secondEditor) {
            createRightUsersList(data);
        }
        requestAjaxData(url2, f2, false);
    }
    requestAjaxData(url, f, es);
}

function setCodeInEditor(gradeable_id, changed) {
    var form = $("#users_with_plagiarism");
    var user_id_1 = $('[name="user_id_1"]', form).val();
    var version_user_1 = $('[name="version_user_1"]', form).val();
    var user_id_2_data = $('[name="user_id_2"]', form).val();
    
    // Empty lists and code
    if((changed == "user_id_1" && user_id_1 == "") || (changed == "version_user_1" && version_user_1 == "")){ 
        $('[name="version_user_1"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        $('[name="user_id_2"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        editor0.getDoc().setValue('');
        editor1.getDoc().setValue('');
    } else if (changed == "user_id_2" && user_id_2_data == "") {
        editor1.getDoc().setValue('');
    } else {
        // First check if left side changed... Clean up this...
        if (changed === 'user_id_1' || changed === 'version_user_1') {
            if(version_user_1 == "") {
                version_user_1 = "max_matching";
            }
            clearCodeEditorsAndUpdateSelection(gradeable_id, user_id_1, version_user_1);
        } else {
            // We know that our right side changed
            const user_id_2_parsed = JSON.parse(user_id_2_data);
            var user_id_2 = user_id_2_parsed['user_id'];
            var version_user_2 = user_id_2_parsed['version'];
            clearCodeEditorsAndUpdateSelection(gradeable_id, user_id_1, version_user_1, user_id_2, version_user_2);
        }
    }
}

