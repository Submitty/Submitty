// MODEL + CONTROLLERS /////////////////////////////////////////////////////////
/**
 * On document.ready, JS in PlagiarismResult.twig will call this function
 * @param {string} gradeable_id
 * @param {string} config_id
 * @param {array} user_1_list
 */
function setUpPlagView(gradeable_id, config_id, user_1_list) {
    initializeResizablePanels('.left-sub-item', '.plag-drag-bar');

    // initialize editors
    const editor1 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true,
    });
    const editor2 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true,
    });

    editor1.setSize('100%', '100%');
    editor2.setSize('100%', '100%');

    // this is the global state for the entire program.  All functions will read and modify this object.
    const state = {
        'gradeable_id': gradeable_id,
        'config_id': config_id,
        'user_1_dropdown_list': user_1_list,
        'user_1_version_dropdown_list': {
            'versions': [],
            'max_matching': '',
            'active_version': '',
        },
        'user_2_dropdown_list': [],
        'user_1_selected': {
            'user_id': user_1_list[0].user_id,
            'version': user_1_list[0].version,
        },
        'user_2_selected': {
            'percent': '',
            'user_id': '',
            'display_name' : '',
            'version': '',
            'source_gradeable': '',
        },
        'editor1': editor1,
        'editor2': editor2,
        'color_info': [],
    };

    // force the page to load default data for user 1 with highest % match
    loadUser1VersionDropdownList(state);

    // set event handler for user 1 dropdown onchange event
    $('#user-1-dropdown-list').change(() => {
        // update the state
        state.user_1_selected.user_id = $('#user-1-dropdown-list').val();

        // refresh the user 2 dropdown
        $('#user-2-dropdown-list').empty();
        $('#user-2-dropdown-list').append('<option>Loading...</option>');

        // refresh the user 1 version dropdown
        $('#user-1-version-dropdown-list').empty();
        $('#user-1-version-dropdown-list').append('<option>Loading...</option>');

        // clear editors
        state.editor1.getDoc().setValue('');
        state.editor1.refresh();
        state.editor2.getDoc().setValue('');
        state.editor2.refresh();

        // the call to trigger the chain of updates
        loadUser1VersionDropdownList(state);
    });

    // set event handler for user 1 version dropdown onchange event
    $('#user-1-version-dropdown-list').change(() => {
        // update the state
        state.user_1_selected.version = $('#user-1-version-dropdown-list').val();

        // refresh the user 2 dropdown
        $('#user-2-dropdown-list').empty();
        $('#user-2-dropdown-list').append('<option>Loading...</option>');

        // clear editors
        state.editor1.getDoc().setValue('');
        state.editor1.refresh();
        state.editor2.getDoc().setValue('');
        state.editor2.refresh();

        // the call to trigger the chain of updates
        loadUser2DropdownList(state);
        loadConcatenatedFileForEditor(state, 1);
    });

    $('#user-2-dropdown-list').change(() => {
        // update the state
        state.user_2_selected = JSON.parse($('#user-2-dropdown-list').val());

        // clear editor 2
        state.editor2.getDoc().setValue('');
        state.editor2.refresh();

        // load new content for the editor
        loadConcatenatedFileForEditor(state, 2);
        loadColorInfo(state);
    });

    handleClickedMarks(state);
}


function loadUser1VersionDropdownList(state) {
    const url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'versionlist'])}?user_id_1=${state.user_1_selected.user_id}`;
    requestAjaxData(url, (data) => {
        state.user_1_version_dropdown_list = data;
        state.user_1_selected.version = data.max_matching;

        refreshUser1VersionDropdown(state);

        // reload conents of panel 1 (async)
        loadConcatenatedFileForEditor(state, 1);

        // update user 2 dropdown, will call other functions to update panel 2 (async)
        loadUser2DropdownList(state);
    });
}


function loadUser2DropdownList(state) {
    // acquire ajax data for user 2 dropdown and send to the refresher
    const url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'match'])}?user_id_1=${state.user_1_selected.user_id}&version_user_1=${state.user_1_selected.version}`;
    requestAjaxData(url, (data) => {

        state.user_2_dropdown_list = data;
        state.user_2_selected = data[0];

        refreshUser2Dropdown(state);
        loadConcatenatedFileForEditor(state, 2);
        loadColorInfo(state);
    });
}


/**
 * @param {int} editor
 */
function loadConcatenatedFileForEditor(state, editor) {
    // makes an ajax request to get the concatenated file with respsect
    // to the selected user + version in panel number #editor
    let url = '';
    if (editor === 1) {
        url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'concat'])}?user_id=${state.user_1_selected.user_id}&version=${state.user_1_selected.version}`;
    }
    else {
        url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'concat'])}?user_id=${state.user_2_selected.user_id}&version=${state.user_2_selected.version}&source_gradeable=${state.user_2_selected.source_gradeable}`;
    }
    requestAjaxData(url, (data) => {
        if (editor === 1) {
            state.editor1.getDoc().setValue(data);
            state.editor1.refresh();
        }
        else {
            state.editor2.getDoc().setValue(data);
            state.editor2.refresh();
        }
    });
}


/**
 * Prevents a race condition where the editor could still be waiting on data when the color info results come back.
 * We wait until the contents of both editors are set before attempting to make colored marks
 * @param state
 */
function colorRefreshAfterConcatLoad(state) {
    if (state.editor1.getDoc().getValue() !== '' && state.editor2.getDoc().getValue() !== '') {
        refreshColorInfo(state);
    }
    else {
        setTimeout(() => {
            colorRefreshAfterConcatLoad(state);
        }, 50);
    }
}


function loadColorInfo(state) {
    const url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'colorinfo'])}?user_id_1=${state.user_1_selected.user_id}&version_user_1=${state.user_1_selected.version}&user_id_2=${state.user_2_selected.user_id}&version_user_2=${state.user_2_selected.version}&source_gradeable_user_2=${state.user_2_selected.source_gradeable}`;
    requestAjaxData(url, (data) => {
        state.color_info = data;

        colorRefreshAfterConcatLoad(state);
    });
}


function requestAjaxData(url, f, es) {
    $.ajax({
        url: url,
        success: function(data) {
            $('#_test').append(data);
            data = JSON.parse(data);
            if (data.status !== 'success') {
                alert(data.message);
                return;
            }

            f(data.data, es);
        },
        error: function(e) {
            alert('Error occured when requesting via ajax. Please refresh the page and try again.');
        },
    });
}


// VIEWS ///////////////////////////////////////////////////////////////////////
// functions that get data from the global state and load it into UI elements

function refreshUser1VersionDropdown(state) {
    // grab data for the version dropdown and append them as options to the html element
    $('#user-1-version-dropdown-list').empty();
    let append_options;
    $.each(state.user_1_version_dropdown_list.versions, (i, version_to_append) => {
        if (version_to_append === state.user_1_version_dropdown_list.active_version && version_to_append === state.user_1_version_dropdown_list.max_matching) {
            append_options += `<option value="${version_to_append}">${version_to_append} (Active)(Max Match)</option>`;
        }
        else if (version_to_append === state.user_1_version_dropdown_list.active_version && version_to_append !== state.user_1_version_dropdown_list.max_matching) {
            append_options += `<option value="${version_to_append}">${version_to_append} (Active)</option>`;
        }
        else if (version_to_append !== state.user_1_version_dropdown_list.active_version && version_to_append === state.user_1_version_dropdown_list.max_matching) {
            append_options += `<option value="${version_to_append}">${version_to_append} (Max Match)</option>`;
        }
        else {
            append_options += `<option value="${version_to_append}">${version_to_append}</option>`;
        }
    });
    $('#user-1-version-dropdown-list').append(append_options);
}


function refreshUser2Dropdown(state) {
    // grab users from user_2_dropdown_list and append to the html element
    $('#user-2-dropdown-list').empty();
    let append_options;
    $.each(state.user_2_dropdown_list, (i, users) => {
        append_options += `<option value='${JSON.stringify(users)}'`;
        if (users === state.user_2_selected) {
            append_options += ' selected';
        }
        append_options += `>(${users.percent} Match) ${users.display_name} &lt;${users.user_id}&gt; (version: ${users.version})</option>`;
    });
    $('#user-2-dropdown-list').append(append_options);
}


function refreshColorInfo(state) {

    state.editor1.operation(() => {
        state.editor2.operation(() => {
            $.each(state.color_info, (i, interval) => {
                let color = '';
                if (interval.type === 'match') {
                    color = 'yellow';
                } else if (interval.type === 'specific-match') {
                    color = 'orange';
                } else if (interval.type === 'provided') {
                    color = 'green';
                } else if (interval.type === 'common') {
                    color = 'gray';
                } else {
                    color = 'transparent';
                }

                const mp_text_marks = [];

                $.each(interval['matching_positions'], (i, mp) => {
                    const color = 'orange';
                    mp_text_marks[i] = state.editor2.markText(
                        { // start position
                            line: mp.start_line - 1,
                            ch: mp.start_char - 1,
                        },
                        { // end position
                            line: interval.end_line - 1,
                            ch: interval.end_char - 1,
                        },
                        {
                            attributes: {
                                'original_color': color,
                            },
                            css: `background: ${color}; ${interval.type === 'specific-match' ? 'border: solid black 1px;' : ''}`,
                        },
                    );
                });

                state.editor1.markText(
                    { // start position
                        line: interval.start_line - 1,
                        ch: interval.start_char - 1,
                    },
                    { // end position
                        line: interval.end_line - 1,
                        ch: interval.end_char - 1,
                    },
                    {
                        attributes: {
                            'original_color': color,
                            'selected': false,
                            'type': interval.type,
                            'matching_positions': mp_text_marks,
                            'others': interval.others,
                        },
                        css: `background: ${color}; ${interval.type === 'specific-match' ? 'border: solid black 1px;' : ''}`,
                    },
                );
            });
        });
    });
    state.editor1.refresh();
    state.editor2.refresh();
}


function handleClickedMarks(state) {
    state.editor1.getWrapperElement().onmouseup = function(e) {
        const lineCh = state.editor1.coordsChar({ left: e.clientX, top: e.clientY });
        const markers = state.editor1.findMarksAt(lineCh);

        // Did not select a marker
        if (markers.length === 0) {
            return;
        }


        // Only grab the first one if there is overlap...
        const clickedMark = markers[markers.length - 1];


        // reset all previous marks
        const marks_editor1 = state.editor1.getAllMarks();
        state.editor1.operation(() => {
            marks_editor1.forEach(mark => {
                if (mark !== clickedMark) {
                    mark.css = `background-color: ${mark.attributes.original_color}`;
                    mark.attributes.selected = false;
                }
            });
        });
        const marks_editor2 = state.editor2.getAllMarks();
        state.editor2.operation(() => {
            marks_editor2.forEach(mark => {
                mark.css = `background-color: ${mark.attributes.original_color}`;
            });
        });


        // mark the clicked mark on both sides
        if (clickedMark.attributes.type === 'specific-match' && !clickedMark.attributes.selected) {
            clickedMark.attributes.selected = true;
            clickedMark.css = 'background-color: red;';

            // highlight the matching regions on the right side
            state.editor2.operation(() => {
                $.each(clickedMark.attributes.matching_positions, (i, mp) => {
                    mp.css = 'background-color: red;';
                });
            });
        }
        else if (clickedMark.attributes.type === 'match' || (clickedMark.attributes.type === 'specific-match' && clickedMark.attributes.selected)) {
            clickedMark.attributes.selected = true;
            clickedMark.css = 'background-color: lightblue;';

            $('#popup_to_show_matches_id').css('left', e.clientX + 'px');
            $('#popup_to_show_matches_id').css('top', e.clientY + 'px');
            $('#popup_to_show_matches_id').empty();

            $.each(clickedMark.attributes.others, function(i, other) {
                $('#popup_to_show_matches_id').append($.parseHTML(`<li id="others_menu_${i}" class="ui-menu-item"><div tabindex="-1" class="ui-menu-item-wrapper">${other.user_id}:${other.version}</div></li>`));
                $(`#others_menu_${i}`).on('click', () => {
                    $.each(state.user_2_dropdown_list, (i, item) => {
                        if (item.user_id === other.user_id && item.version === other.version && item.source_gradeable === other.source_gradeable) {
                            state.user_2_selected = item;
                        }
                    });
                    state.editor2.getDoc().setValue('');
                    state.editor2.refresh();
                    // load new content for the editor
                    loadConcatenatedFileForEditor(state, 2);
                    loadColorInfo(state);

                    refreshUser2Dropdown(state);
                    $('#popup_to_show_matches_id').css('display', 'none');
                });
            });
            $('#popup_to_show_matches_id').css('display', 'block');
        }


        // Refresh editors
        state.editor1.refresh();
        state.editor2.refresh();
    };
}


function showPlagiarismHighKey() {
    $('#Plagiarism-Highlighting-Key').css('display', 'block');
}









// const YELLOW = "#ffff00";
// const ORANGE = "#ffa500";
// const RED    = "#ff0000";
// const BLUE   = "#89CFF0";
// let editor0 = null;
// let editor1 = null;
// let form = null;
// let si = null;
// let gradeableId = null;
// let configId = null;
// let blueClickedMark = null;
//
//
// function isColoredMarker(marker, color) {
//     return marker.css.toLowerCase().indexOf(color) !== -1;
// }
//
// function colorEditors(data) {
//     si = data.si;
//     for(let users_color in data.ci) {
//     	let editor = parseInt(users_color) === 1 ? editor0 : editor1;
//     	editor.operation(() => {
//         	for(let pos in data.ci[users_color]) {
//             	let element = data.ci[users_color][pos];
//             	editor.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_prev_color": element[4], "data_start": element[5], "data_end": element[6], "line": element[1]}, css: "background: " + element[4] + "; " + (parseInt(users_color) === 1 ? "border: solid black 1px;" : "")});
//         	}
//     	});
//     }
// }
//
// function updatePanesOnOrangeClick(leftClickedMarker, editor1, editor2) {
//     // Remove existing red region and add new one
//     let marks_editor2 = editor2.getAllMarks();
//
//     //add new red colored marks
//     let firstMarkFound = false;
//     marks_editor2.forEach(mark => {
//         for (let i=0; i < leftClickedMarker.attributes.data_start.length; i++) {
//             if (mark.attributes.data_start === parseInt(leftClickedMarker.attributes.data_start[i]) && mark.attributes.data_end === parseInt(leftClickedMarker.attributes.data_end[i])) {
//                 if (!firstMarkFound) {
//                     editor2.scrollIntoView({line: mark.attributes.line, ch: 0});
//                     firstMarkFound = true;
//                 }
//                 mark.css = "background: " + RED + ";";
//             }
//         }
//     });
//
//     // Color the clicked region in editor1
// 	leftClickedMarker.css = "background: " + RED + "; border: solid black 1px;";
// }
//
// function setUpLeftPane() {
//     editor0.getWrapperElement().onmouseup = function(e) {
//         let lineCh = editor0.coordsChar({ left: e.clientX, top: e.clientY });
//         lineCh["ch"] = lineCh["ch"] + 1;
//         let markers = editor0.findMarksAt(lineCh);
//
//         // Did not select a marker
//         if (markers.length === 0) {
//             return;
//         }
//
//         // Only grab the first one if there is overlap...
//         let lineData = markers[0].find();
//         let clickedMark = markers[0];
//
//         // remove existing marks on editor 1
//         editor1.operation(function() {
//             editor1.getAllMarks().forEach(mark => {
//                 mark.css = "background: " + mark.attributes.data_prev_color + ";";
//             });
//         });
//         // Remove existing marks on editor 0
//         editor0.operation(function() {
//             editor0.getAllMarks().forEach(mark => {
//                 if (mark !== clickedMark) {
//                     mark.css = "background: " + mark.attributes.data_prev_color + "; border: solid black 1px;";
//                 }
//             });
//         });
//
//         // Reset any existing popups
//         if($('#popup_to_show_matches_id').css('display') === 'block'){
//             $('#popup_to_show_matches_id').css('display', 'none');
//             clickedMark.css = "background: " + clickedMark.attributes.data_prev_color;
//             blueClickedMark = null;
//         }
//
//         if(isColoredMarker(clickedMark, YELLOW) || isColoredMarker(clickedMark, RED)) {
//             $('#popup_to_show_matches_id').css('left', e.clientX + "px");
//             $('#popup_to_show_matches_id').css('top', e.clientY + "px");
//
//             let user_id_1 = $('[name="user_id_1"]', form).val();
//             let user_1_version = $('[name="version_user_1"]', form).val();
//             clickedMark.css = "background: " + BLUE;
//             blueClickedMark = clickedMark;
//             getMatchesListForClick(user_id_1, user_1_version, lineData.from);
//         } else if(isColoredMarker(clickedMark, ORANGE)) {
//             updatePanesOnOrangeClick(clickedMark, editor0, editor1);
//         }
//
//         // Refresh editors
//         editor0.refresh();
//         editor1.refresh();
//     }
// }
//
// function getMatchesListForClick(user_id_1, user_1_version, user_1_match_start) {
//     let user_matches = si[`${user_1_match_start.line}_${user_1_match_start.ch}`];
//     let to_append = '';
//     $.each(user_matches, function(i, match) {
//         let res = match.split('__');
//         to_append += `<li class="ui-menu-item"><div tabindex="-1" class="ui-menu-item-wrapper" onclick="clearCodeEditorsAndUpdateSelection('${user_id_1}', '${user_1_version}', '${res[0]}', '${res[1]}'); $('#popup_to_show_matches_id').css('display', 'none');">${res[0]} (version:${res[1]})</div></li>`;
//     });
//     to_append = $.parseHTML(to_append);
//     $("#popup_to_show_matches_id").empty().append(to_append);
//     $('#popup_to_show_matches_id').css('display', 'block');
// }
//
// function getUserData() {
//     let user_id_2_data = $('[name="user_id_2"]', form).val();
//     const user_id_2_parsed = JSON.parse(user_id_2_data);
//     let user_id_2 = user_id_2_parsed['user_id'];
//     let version_user_2 = user_id_2_parsed['version'];
//     let source_gradeable = user_id_2_parsed['source_gradeable'];
//     let user_id_1 = $('[name="user_id_1"]', form).val();
//     let version_user_1 = $('[name="version_user_1"]', form).val();
//     return {'user_id_1': user_id_1, 'version_user_1': version_user_1, 'user_id_2': user_id_2, 'version_user_2': version_user_2, 'source_gradeable': source_gradeable};
// }
//
// function toggle() {
//     let data = getUserData();
//     updateRightUserLists(data['user_id_2'], data['version_user_2'], data['user_id_1']);
//     clearCodeEditorsAndUpdateSelection(data['user_id_2'], data['version_user_2'], data['user_id_1'], data['version_user_1']);
//     $('[name="user_id_1"]', form).val(data['user_id_2']);
//     $('[name="version_user_1"]', form).val(data['version_user_2']);
// }
//
// function toggleFullScreenMode() {
//     $('main#main').toggleClass("full-screen-mode");
// }
//
// $(document).ready(() => {
//     initializeResizablePanels('.left-sub-item', '.plag-drag-bar');
// });
//
// function showPlagiarismHighKey() {
//     $('#Plagiarism-Highlighting-Key').css('display', 'block');
// }
//
// function setUpPlagView(gradeable_id, config_id) {
//
//     gradeableId = gradeable_id;
//     configId = config_id;
// 	form = $("#users_with_plagiarism");
//     editor0 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
//         lineNumbers: true,
//         readOnly: true,
//         cursorHeight: 0.0,
//         lineWrapping: true
//     });
//     editor1 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
//         lineNumbers: true,
//         readOnly: true,
//         cursorHeight: 0.0,
//         lineWrapping: true
//     });
//
//     editor0.setSize("100%", "100%");
//     editor1.setSize("100%", "100%");
//
//     $('[name="user_id_1"]', form).change(function(){
//         setCodeInEditor('user_id_1');
//     });
//     $('[name="version_user_1"]', form).change(function(){
//         setCodeInEditor('version_user_1');
//     });
//     $('[name="user_id_2"]', form).change(function(){
//         setCodeInEditor('user_id_2');
//     });
//     setUpLeftPane();
//     setCodeInEditor('user_id_1'); // Automatically load the user with the highest % match
// }
//
// function requestAjaxData(url, f, es) {
//     $.ajax({
//         url: url,
//         success: function(data) {
//             $("#_test").append(data)
//             data = JSON.parse(data);
//             if (data.status !== "success") {
//                 alert(data.message);
//                 return;
//             }
//
//             f(data.data, es);
//         },
//         error: function(e) {
//             alert("Error occured when requesting via ajax. Please refresh the page and try again.");
//         }
//     });
// }
//
// function createRightUsersList(data, select = null) {
//     let position = 0;
//     let append_options;
//     $.each(data, function(i,users){
//         append_options += `<option value="{'user_id':'${users[0]}', 'version':'${users[1]}', 'source_gradeable':'${users[5]}'}"`;
//         if (select === users[0]) {
//             position = i;
//             append_options += ' selected>';
//         } else {
//             append_options += '>';
//         }
//         append_options += '(' + users[4] + ' Match) ' + users[2] + ' ' + users[3] + ' &lt;' + users[0] + '&gt; (version:' + users[1] + ')</option>';
//     });
//     $('[name="user_id_2"]', form).find('option').remove().end().append(append_options).val('');
//     $('[name="user_id_2"] option', form).eq(position).prop('selected', true);
//     $('[name="user_id_2"]', form).change();
// }
//
// function createLeftUserVersionDropdown(version_data, active_version_user_1, max_matching_version, code_version_user_1) {
//     let append_options;
//     $.each(version_data, function(i,version_to_append) {
//         if(version_to_append === active_version_user_1 && version_to_append === max_matching_version){
//             append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)(Max Match)</option>';
//         }
//         if(version_to_append === active_version_user_1 && version_to_append !== max_matching_version){
//             append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)</option>';
//         }
//         if(version_to_append !== active_version_user_1 && version_to_append === max_matching_version){
//             append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Max Match)</option>';
//         }
//
//         if(version_to_append !== active_version_user_1 && version_to_append !== max_matching_version){
//             append_options += '<option value="'+ version_to_append +'">'+ version_to_append +'</option>';
//         }
//     });
//     $('[name="version_user_1"]', form).find('option').remove().end().append(append_options).val(code_version_user_1);
//
// }
//
// function updateRightUserLists(user_id_1, version_id_1, select = null) {
//     let url2 = buildCourseUrl(['plagiarism', 'gradeable', gradeableId, 'match']) + `?user_id_1=${user_id_1}&version_user_1=${version_id_1}&config_id=${configId}`;
//     const f2 = function(data, select) {
//         createRightUsersList(data, select);
//     }
//     requestAjaxData(url2, f2, select);
// }
//
// function clearCodeEditorsAndUpdateSelection(user_id_1, version_id_1, user_id_2 = null, version_id_2 = null, source_gradeable_user_2 = null) {
//     const f = function(data, secondEditor) {
//         editor0.getDoc().setValue(data.display_code1);
//         editor0.refresh();
//         createLeftUserVersionDropdown(data.all_versions_user_1, data.active_version_user_1, data.max_matching_version, data.code_version_user_1);
//         if (secondEditor) {
//             editor1.getDoc().setValue(data.display_code2);
//             editor1.refresh();
//         } else {
//             editor1.getDoc().setValue('');
//         }
//         colorEditors(data);
//     };
//     let url = buildCourseUrl(['plagiarism', 'gradeable', gradeableId, 'concat']) + `?user_id_1=${user_id_1}&version_user_1=${version_id_1}&config_id=${configId}`;
//     let es = false;
//     if (user_id_2 != null) {
//         editor1.getDoc().setValue('');
//         url += `&user_id_2=${user_id_2}&version_user_2=${version_id_2}&source_gradeable_user_2=${source_gradeable_user_2}`;
//         es = true;
//         $(".user2-select").val(`{"user_id":"${user_id_2}", "version":${version_id_2}, "source_gradeable":"${source_gradeable_user_2}"}`);
//     } else {
//         editor0.getDoc().setValue('');
//         editor1.getDoc().setValue('');
//         updateRightUserLists(user_id_1, version_id_1);
//     }
//     requestAjaxData(url, f, es);
// }
//
// function setCodeInEditor(changed) {
//     let user_id_1 = $('[name="user_id_1"]', form).val();
//     let version_user_1 = $('[name="version_user_1"]', form).val();
//     let user_id_2_data = $('[name="user_id_2"]', form).val();
//
//     // Empty lists and code (this should never happen)
//     if((changed === "user_id_1" && user_id_1 === "") || (changed === "version_user_1" && version_user_1 === "")){
//         $('[name="version_user_1"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
//         $('[name="user_id_2"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
//         editor0.getDoc().setValue('');
//         editor1.getDoc().setValue('');
//     } else if (changed === "user_id_2" && user_id_2_data === "") {
//         editor1.getDoc().setValue('');
//     } else {
//         // First check if left side changed... Clean up this...
//         if (changed === 'user_id_1' || changed === 'version_user_1') {
//             if(version_user_1 === "" || changed === 'user_id_1') { // If user 1 was changed or no user has been selected yet, set the version to max matching
//                 version_user_1 = "max_matching";
//             }
//             clearCodeEditorsAndUpdateSelection(user_id_1, version_user_1);
//         } else {
//             // We know that our right side changed
//             const user_id_2_parsed = JSON.parse(user_id_2_data);
//             let user_id_2 = user_id_2_parsed['user_id'];
//             let version_user_2 = user_id_2_parsed['version'];
//             let source_gradeable_user_2 = user_id_2_parsed['source_gradeable'];
//             clearCodeEditorsAndUpdateSelection(user_id_1, version_user_1, user_id_2, version_user_2, source_gradeable_user_2);
//         }
//     }
// }
