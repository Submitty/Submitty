const YELLOW = "#ffff00";
const ORANGE = "#ffa500";
const RED    = "#ff0000";
const BLUE   = "#89CFF0";
let editor0 = null;
let editor1 = null;
let form = null;
let si = null;
let gradeableId = null;
let blueClickedMark = null;


function isColoredMarker(marker, color) {
    return marker.css.toLowerCase().indexOf(color) !== -1;
}

function colorEditors(data) {
    window.si = data.si;
    for(let users_color in data.ci) {
    	let editor = parseInt(users_color) === 1 ? editor0 : editor1;
    	editor.operation(() => {
        	for(let pos in data.ci[users_color]) {
            	let element = data.ci[users_color][pos];
            	editor.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_prev_color": element[4], "data_start": element[5], "data_end": element[6]}, css: "background: " + element[4] + ";" + (parseInt(users_color) === 1 ? "border: solid black 1px;" : "")});
        	}
    	});
    }
}

function updatePanesOnOrangeClick(leftClickedMarker, editor1, editor2) {
    // Remove existing red region and add new one
    let marks_editor2 = editor2.getAllMarks();
    editor2.operation( () => {
        // remove existing marks
        marks_editor2.forEach(mark => {
            if (mark.attributes.data_current_color === RED) {
                mark.css = "background: " + ORANGE + ";";
                mark.attributes.data_current_color = ORANGE;
            }
        });

        //add new red colored marks
    	marks_editor2.forEach(mark => {
    	    for (let i=0; i < leftClickedMarker.attributes.data_start.length; i++) {
                if (mark.attributes.data_start === parseInt(leftClickedMarker.attributes.data_start[i]) && mark.attributes.data_end === parseInt(leftClickedMarker.attributes.data_end[i])) {
                    mark.css = "background: " + RED + ";";
                    mark.attributes.data_current_color = RED;
                }
            }
    	});
	});

    // Remove existing red regions on editor1
    editor2.operation( () => {
        editor1.getAllMarks().forEach(mark => {
            if (mark.attributes.data_current_color === RED) {
                mark.css = "background: " + ORANGE + "; border: solid black 1px;";
                mark.attributes.data_current_color = ORANGE;
            }
        });
    });

    // Color the clicked region in editor1
	leftClickedMarker.css = "background: " + RED + "; border: solid black 1px;";
	leftClickedMarker.attributes.data_current_color = RED;

	// Refresh editors
	editor1.refresh();
    editor2.refresh();
}

function setUpLeftPane() {
    editor0.getWrapperElement().onmouseup = function(e) {
        let lineCh = editor0.coordsChar({ left: e.clientX, top: e.clientY });
        lineCh["ch"] = lineCh["ch"] + 1;
        let markers = editor0.findMarksAt(lineCh);

        // Did not select a marker
        if (markers.length === 0) {
            return;
        }

        // Only grab the first one if there is overlap...
        let lineData = markers[0].find();
        let clickedMark = markers[0];

        // Reset any existing popups
        if($('#popup_to_show_matches_id').css('display') === 'block'){
            $('#popup_to_show_matches_id').css('display', 'none');
            blueClickedMark.css = "background: " + clickedMark.attributes.data_prev_color;
            blueClickedMark = null;
            editor0.refresh();
        }

        if(isColoredMarker(clickedMark, YELLOW) || isColoredMarker(clickedMark, RED)) {
            $('#popup_to_show_matches_id').css('left', e.clientX + "px");
            $('#popup_to_show_matches_id').css('top', e.clientY + "px");

            let user_id_1 = $('[name="user_id_1"]', form).val();
            let user_1_version = $('[name="version_user_1"]', form).val();
            clickedMark.css = "background: " + BLUE;
            blueClickedMark = clickedMark;
            getMatchesListForClick(user_id_1, user_1_version, lineData.from);
            editor0.refresh();
        } else if(isColoredMarker(clickedMark, ORANGE)) {
            updatePanesOnOrangeClick(clickedMark, editor0, editor1);
        }

    }
}

function getUserData() {
    let user_id_2_data = $('[name="user_id_2"]', form).val();
    const user_id_2_parsed = JSON.parse(user_id_2_data);
    let user_id_2 = user_id_2_parsed['user_id'];
    let version_user_2 = user_id_2_parsed['version'];
    let user_id_1 = $('[name="user_id_1"]', form).val();
    let version_user_1 = $('[name="version_user_1"]', form).val();
    return {'user_id_1': user_id_1, 'version_user_1': version_user_1, 'user_id_2': user_id_2, 'version_user_2': version_user_2};
}

function toggle() {
    let data = getUserData();
    updateRightUserLists(data['user_id_2'], data['version_user_2'], data['user_id_1']);
    clearCodeEditorsAndUpdateSelection(data['user_id_2'], data['version_user_2'], data['user_id_1'], data['version_user_1']);
    $('[name="user_id_1"]', form).val(data['user_id_2']);
    $('[name="version_user_1"]', form).val(data['version_user_2']);
}

function toggleFullScreenMode() {
    $('main#main').toggleClass("full-screen-mode");
}

$(document).ready(() => {
    initializeResizablePanels('.left-sub-item', '.plag-drag-bar');
});

function getMatchesListForClick(user_id_1, user_1_version, user_1_match_start) {
    let user_matches = window.si[`${user_1_match_start.line}_${user_1_match_start.ch}`];
    let to_append = '';
    $.each(user_matches, function(i, match) {
        console.log(user_matches);
        let res = match.split('_');
        to_append += '<li class="ui-menu-item"><div tabindex="-1" class="ui-menu-item-wrapper" onclick="clearCodeEditorsAndUpdateSelection(' + `'${user_id_1}', '${user_1_version}', '${res[0]}', '${res[1]}'); $('#popup_to_show_matches_id').css('display', 'none');"` + '>' + res[0] + ' (version:'+res[1]+')</div></li>';
    });
    to_append = $.parseHTML(to_append);
    $("#popup_to_show_matches_id").empty().append(to_append);
    $('#popup_to_show_matches_id').css('display', 'block');
    // TODO: Discuss location for matches popup
}

function showPlagiarismHighKey() {
    $('#Plagiarism-Highlighting-Key').css('display', 'block');
}

function setUpPlagView(gradeable_id) {

    gradeableId = gradeable_id;
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
        setCodeInEditor('user_id_1');
    });
    $('[name="version_user_1"]', form).change(function(){
        setCodeInEditor('version_user_1');
    });
    $('[name="user_id_2"]', form).change(function(){
        setCodeInEditor('user_id_2');
    });
    setUpLeftPane();
    setCodeInEditor('user_id_1'); // Automatically load the user with the highest % match
}

function requestAjaxData(url, f, es) {
    $.ajax({
        url: url,
        success: function(data) {
            $("#_test").append(data);
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

function createRightUsersList(data, select = null) {
    let position = 0;
    let append_options;
    data = JSON.parse(data);
    $.each(data, function(i,users){
        append_options += '<option value="{&#34;user_id&#34;:&#34;'+ users[0]+'&#34;,&#34;version&#34;:'+ users[1] +'}"';
        if (select === users[0]) {
            position = i;
            append_options += ' selected>';
        } else {
            append_options += '>';
        }
        append_options += '(' + users[4] + ' Match) ' + users[2] + ' '+ users[3] + ' &lt;' + users[0] + '&gt; (version:' + users[1] + ')</option>';
    });
    $('[name="user_id_2"]', form).find('option').remove().end().append(append_options).val('');
    $('[name="user_id_2"] option', form).eq(position).prop('selected', true);
    $('[name="user_id_2"]', form).change();
}

function createLeftUserVersionDropdown(version_data, active_version_user_1, max_matching_version, code_version_user_1) {
    let append_options;
    $.each(version_data, function(i,version_to_append) {
        if(version_to_append === active_version_user_1 && version_to_append === max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)(Max Match)</option>';
        }
        if(version_to_append === active_version_user_1 && version_to_append !== max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)</option>';
        }
        if(version_to_append !== active_version_user_1 && version_to_append === max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Max Match)</option>';
        }

        if(version_to_append !== active_version_user_1 && version_to_append !== max_matching_version){
            append_options += '<option value="'+ version_to_append +'">'+ version_to_append +'</option>';
        }
    });
    $('[name="version_user_1"]', form).find('option').remove().end().append(append_options).val(code_version_user_1);

}

function updateRightUserLists(user_id_1, version_id_1, select = null) {
    let url2 = buildCourseUrl(['plagiarism', 'gradeable', gradeableId, 'match']) + `?user_id_1=${user_id_1}&version_user_1=${version_id_1}`;
    const f2 = function(data, select) {
        createRightUsersList(data, select);
    }
    requestAjaxData(url2, f2, select);
}

function clearCodeEditorsAndUpdateSelection(user_id_1, version_id_1, user_id_2 = null, version_id_2 = null) {
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
    let url = buildCourseUrl(['plagiarism', 'gradeable', gradeableId, 'concat']) + `?user_id_1=${user_id_1}&version_user_1=${version_id_1}`;
    let es = false;
    if (user_id_2 != null) {
        url += `&user_id_2=${user_id_2}&version_user_2=${version_id_2}`;
        es = true;
    } else {
        updateRightUserLists(user_id_1, version_id_1);
    }
    requestAjaxData(url, f, es);
}

function setCodeInEditor(changed) {
    let user_id_1 = $('[name="user_id_1"]', form).val();
    let version_user_1 = $('[name="version_user_1"]', form).val();
    let user_id_2_data = $('[name="user_id_2"]', form).val();

    // Empty lists and code (this should never happen)
    if((changed === "user_id_1" && user_id_1 === "") || (changed === "version_user_1" && version_user_1 === "")){
        $('[name="version_user_1"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        $('[name="user_id_2"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        editor0.getDoc().setValue('');
        editor1.getDoc().setValue('');
    } else if (changed === "user_id_2" && user_id_2_data === "") {
        editor1.getDoc().setValue('');
    } else {
        // First check if left side changed... Clean up this...
        if (changed === 'user_id_1' || changed === 'version_user_1') {
            if(version_user_1 === "" || changed === 'user_id_1') { // If user 1 was changed or no user has been selected yet, set the version to max matching
                version_user_1 = "max_matching";
            }
            clearCodeEditorsAndUpdateSelection(user_id_1, version_user_1);
        } else {
            // We know that our right side changed
            const user_id_2_parsed = JSON.parse(user_id_2_data);
            let user_id_2 = user_id_2_parsed['user_id'];
            let version_user_2 = user_id_2_parsed['version'];
            clearCodeEditorsAndUpdateSelection(user_id_1, version_user_1, user_id_2, version_user_2);
        }
    }
}
