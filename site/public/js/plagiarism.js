// MODEL + CONTROLLERS /////////////////////////////////////////////////////////
/**
 * On document.ready, JS in PlagiarismResult.twig will call this function
 * @param {string} gradeable_id
 * @param {string} term_course_gradeable
 * @param {string} config_id
 * @param {array} user_1_list
 */
// eslint-disable-next-line no-unused-vars
function setUpPlagView(gradeable_id, term_course_gradeable, config_id, user_1_list) {
    // eslint-disable-next-line no-undef
    initializeResizablePanels('.left-sub-item', '.plag-drag-bar');

    // initialize editors
    // eslint-disable-next-line no-undef
    const editor1 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true,
    });
    // eslint-disable-next-line no-undef
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
        'curr_course_term_course_gradeable': term_course_gradeable,
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
        user1DropdownChanged(state);
    });

    // set event handler for user 1 version dropdown onchange event
    $('#user-1-version-dropdown-list').change(() => {
        user1VersionDropdownChanged(state);
    });

    $('#user-2-dropdown-list').change(() => {
        user2DropdownChanged(state);
    });

    $('#swap-students-button').click(() => {
        const prev_user_1 = state.user_1_selected;
        state.user_1_selected.user_id = state.user_2_selected.user_id;
        state.user_1_selected.version = state.user_2_selected.version;

        $.each(state.user_2_dropdown_list, (i, user) => {
            if (user.user_id === prev_user_1.user_id && user.version === prev_user_1.version && user.source_gradeable === state.curr_course_term_course_gradeable) {
                state.user_2_selected = user;
                user1DropdownChanged(state);
            }
        });
    });

    handleClickedMarks(state);
}


function user1DropdownChanged(state) {
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
}


function user1VersionDropdownChanged(state) {
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
}


function user2DropdownChanged(state) {
    // update the state
    state.user_2_selected = JSON.parse($('#user-2-dropdown-list').val());

    // clear editor 2
    state.editor2.getDoc().setValue('');
    state.editor2.refresh();

    // load new content for the editor
    loadConcatenatedFileForEditor(state, 2);
    loadColorInfo(state);
}


function loadUser1VersionDropdownList(state) {
    // eslint-disable-next-line no-undef
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
    // eslint-disable-next-line no-undef
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
        // eslint-disable-next-line no-undef
        url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'concat'])}?user_id=${state.user_1_selected.user_id}&version=${state.user_1_selected.version}`;
    }
    else {
        // eslint-disable-next-line no-undef
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
    // eslint-disable-next-line no-undef
    const url = `${buildCourseUrl(['plagiarism', 'gradeable', state.gradeable_id, state.config_id, 'colorinfo'])}?user_id_1=${state.user_1_selected.user_id}&version_user_1=${state.user_1_selected.version}&user_id_2=${state.user_2_selected.user_id}&version_user_2=${state.user_2_selected.version}&source_gradeable_user_2=${state.user_2_selected.source_gradeable}`;
    requestAjaxData(url, (data) => {
        state.color_info = data;

        colorRefreshAfterConcatLoad(state);
    });
}


/**
 * Makes a request to the specified URL and passes the list of parameters (es) to the specified callback function (f)
 * @param url
 * @param f
 * @param es
 */
function requestAjaxData(url, f, es) {
    $.ajax({
        url: url,
        success: function(data) {
            data = JSON.parse(data);
            if (data.status !== 'success') {
                alert(data.message);
                return;
            }

            f(data.data, es);
        },
        error: function() {
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
                    color = 'match-style';
                }
                else if (interval.type === 'specific-match') {
                    color = 'specific-match-style';
                }
                else if (interval.type === 'provided') {
                    color = 'provided-code-style';
                }
                else if (interval.type === 'common') {
                    color = 'common-code-style';
                }
                else {
                    color = '';
                }

                const mp_text_marks = [];

                $.each(interval.matching_positions, (i, mp) => {
                    const color = 'specific-match-style';
                    mp_text_marks[i] = state.editor2.markText(
                        { // start position
                            line: mp.start_line - 1,
                            ch: mp.start_char - 1,
                        },
                        { // end position
                            line: mp.end_line - 1,
                            ch: mp.end_char - 1,
                        },
                        {
                            attributes: {
                                'original_color': color,
                                'start_line': mp.start_line - 1,
                                'end_line': mp.start_line - 1,
                            },
                            className: color,
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
                        className: color,
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


        // hide the "others" popup in case it was visible
        $('#popup_to_show_matches_id').css('display', 'none');


        // Only grab the first one if there is overlap...
        const clickedMark = markers[0];


        // reset all previous marks
        const marks_editor1 = state.editor1.getAllMarks();
        state.editor1.operation(() => {
            marks_editor1.forEach(mark => {
                if (markers.length === 0 || mark !== clickedMark) {
                    mark.className = mark.attributes.original_color;
                    mark.attributes.selected = false;
                }
            });
        });
        const marks_editor2 = state.editor2.getAllMarks();
        state.editor2.operation(() => {
            marks_editor2.forEach(mark => {
                mark.className = mark.attributes.original_color;
            });
        });


        // Did not select a marker
        if (markers.length === 0) {
            state.editor1.refresh();
            state.editor2.refresh();
            return;
        }


        // mark the clicked mark on both sides
        if (clickedMark.attributes.type === 'specific-match' && !clickedMark.attributes.selected) {
            clickedMark.attributes.selected = true;
            clickedMark.className = 'selected-style-red';

            // highlight the matching regions on the right side
            state.editor2.operation(() => {
                $.each(clickedMark.attributes.matching_positions, (i, mp) => {
                    mp.className = 'selected-style-red';
                });
                state.editor2.scrollIntoView(clickedMark.attributes.matching_positions[0].attributes.start_line, 0);
                state.editor2.scrollIntoView(clickedMark.attributes.matching_positions[0].attributes.end_line + 1, 0);
            });
        }
        else if (clickedMark.attributes.type === 'match' || (clickedMark.attributes.type === 'specific-match' && clickedMark.attributes.selected)) {
            clickedMark.attributes.selected = true;
            clickedMark.className = 'selected-style-blue';

            $('#popup_to_show_matches_id').css('left', `${e.clientX}px`);
            $('#popup_to_show_matches_id').css('top', `${e.clientY}px`);
            $('#popup_to_show_matches_id').empty();

            $.each(clickedMark.attributes.others, (i, other) => {
                $('#popup_to_show_matches_id').append($.parseHTML(`<li id="others_menu_${i}" class="ui-menu-item"><div tabindex="-1" class="ui-menu-item-wrapper">${other.user_id}:${other.version}</div></li>`));
                $(`#others_menu_${i}`).on('click', () => {
                    // hiding the popup and resetting the text color immediately makes the page feel faster
                    $('#popup_to_show_matches_id').css('display', 'none');
                    clickedMark.className = clickedMark.attributes.original_color;
                    state.editor2.getDoc().setValue('');
                    state.editor2.refresh();
                    state.editor1.refresh();

                    // set the selected user in the user 2 dropdown
                    $.each(state.user_2_dropdown_list, (i, item) => {
                        if (item.user_id === other.user_id && item.version === other.version && item.source_gradeable === other.source_gradeable) {
                            state.user_2_selected = item;
                        }
                    });

                    // all async so order doesn't particularly matter
                    loadConcatenatedFileForEditor(state, 2);
                    loadColorInfo(state);
                    refreshUser2Dropdown(state);
                });
            });
            $('#popup_to_show_matches_id').css('display', 'block');
        }

        // Refresh editors
        state.editor1.refresh();
        state.editor2.refresh();
    };
}


// eslint-disable-next-line no-unused-vars
function showPlagiarismHighKey() {
    $('#Plagiarism-Highlighting-Key').css('display', 'block');
}


// eslint-disable-next-line no-unused-vars
function toggleFullScreenMode() {
    $('main#main').toggleClass('full-screen-mode');
}


function swapStudents(state) {

}
