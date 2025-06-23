// MODEL + CONTROLLERS /////////////////////////////////////////////////////////
/**
 * On document.ready, JS in PlagiarismResult.twig will call this function
 * @param {string} gradeable_id
 * @param {string} term_course_gradeable
 * @param {string} config_id
 * @param {array} user_1_list
 */
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
        autoRefresh: true,
    });
    // eslint-disable-next-line no-undef
    const editor2 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true,
        autoRefresh: true,
    });

    editor1.setSize('100%', '100%');
    editor2.setSize('100%', '100%');

    const default_highlighting_colors = {
        'common-code': '#d3d3d3',
        'provided-code': '#ccebc5',
        'match': '#ffffb3',
        'specific-match': '#fdb462',
        'selected-red': '#fb8072',
        'selected-blue': '#b3cde3',
    };

    // this is the global state for the entire program.  All functions will read and modify this object.
    const state = {
        gradeable_id: gradeable_id,
        config_id: config_id,
        this_term_course_gradeable: term_course_gradeable,
        user_1_dropdown_list: user_1_list,
        user_1_version_dropdown_list: {
            versions: [],
            max_matching: '',
            active_version: '',
        },
        user_2_dropdown_list: [],
        user_1_selected: {
            user_id: user_1_list[0].user_id,
            version: user_1_list[0].version,
        },
        user_2_selected: {
            percent: '',
            user_id: '',
            display_name: '',
            version: '',
            source_gradeable: '',
        },
        editor1: editor1,
        editor2: editor2,
        color_info: [],
        previous_selection: {
            start_line: -1,
            end_line: -1,
            start_char: -1,
            end_char: -1,
        },
        anon_mode_enabled: localStorage.getItem('plagiarism-anon-mode-enabled') === 'true',
        highlighting_colors: JSON.parse(JSON.stringify(default_highlighting_colors)), // a crude way to copy the object
    };

    // Highlighting setup
    const highlighting_init = (localstorage_name, id) => {
        if (localStorage.getItem(localstorage_name) !== null) {
            $(`#${id}`).val(localStorage.getItem(localstorage_name));
            state.highlighting_colors[id] = localStorage.getItem(localstorage_name);
        }
        else {
            $(`#${id}`).val(default_highlighting_colors[id]);
        }

        $(`#${id}`).on('input', () => {
            localStorage.setItem(localstorage_name, $(`#${id}`).val());
            state.highlighting_colors[id] = $(`#${id}`).val();
            refreshColorInfo(state);
        });
    };

    [
        ['plagiarism-common-code-color', 'common-code'],
        ['plagiarism-provided-code-color', 'provided-code'],
        ['plagiarism-match-color', 'match'],
        ['plagiarism-match-color', 'specific-match'],
        ['plagiarism-selected-red-color', 'selected-red'],
        ['plagiarism-selected-blue-color', 'selected-blue'],
    ].forEach((x) => {
        highlighting_init(x[0], x[1]);
    });

    $('#reset-colors').click(() => {
        localStorage.removeItem('plagiarism-common-code-color');
        localStorage.removeItem('plagiarism-provided-code-color');
        localStorage.removeItem('plagiarism-match-color');
        localStorage.removeItem('plagiarism-specific-match-color');
        localStorage.removeItem('plagiarism-selected-red-color');
        localStorage.removeItem('plagiarism-selected-blue-color');

        $('#common-code').val(default_highlighting_colors['common-code']);
        $('#provided-code').val(default_highlighting_colors['provided-code']);
        $('#match').val(default_highlighting_colors['match']);
        $('#specific-match').val(default_highlighting_colors['specific-match']);
        $('#selected-red').val(default_highlighting_colors['selected-red']);
        $('#selected-blue').val(default_highlighting_colors['selected-blue']);

        state.highlighting_colors = JSON.parse(JSON.stringify(default_highlighting_colors)); // a crude way to copy the object
        refreshColorInfo(state);
    });
    // End highlighting setup

    if (state.anon_mode_enabled) {
        $('#toggle-anon-mode-btn').text('Exit Anonymous Mode');
    }
    else {
        $('#toggle-anon-mode-btn').text('Enter Anonymous Mode');
    }

    // put data in the user 1 dropdown
    recreateUser1Dropdown(state);

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
        swapStudents(state);
    });

    $('#show-plag-high-key-btn').click(() => {
        showPlagiarismHighKey();
    });

    $('#toggle-fullscreen-btn').click(() => {
        toggleFullScreenMode();
    });

    $('#toggle-anon-mode-btn').click(() => {
        toggleAnonymousMode(state);
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
    showLoadingIndicatorRight();
    showLoadingIndicatorLeft();
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
    showLoadingIndicatorRight();
    showLoadingIndicatorLeft();
    loadUser2DropdownList(state);
    loadConcatenatedFileForEditor(state, 1);
}

function user2DropdownChanged(state) {
    // update the state
    state.user_2_selected = JSON.parse($('#user-2-dropdown-list').val());

    // clear editor 2
    state.editor2.getDoc().setValue('');
    state.editor2.refresh();

    if (state.user_2_selected.source_gradeable !== state.this_term_course_gradeable) {
        $('#swap-students-button').addClass('disabled');
    }
    else {
        $('#swap-students-button').removeClass('disabled');
    }

    // load new content for the editor
    showLoadingIndicatorRight();
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

        // reload contents of panel 1 (async)
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

        // No matches for this user+version pair
        if (data.length === 0) {
            state.user_2_selected = [];
        }

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
    else { // editor 2
        if (state.user_2_selected.length === 0) {
            state.editor2.getDoc().setValue('No matches for this submission');
            state.editor2.refresh();
            return;
        }
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
    if (state.user_2_selected.length === 0) {
        state.color_info = [];
        colorRefreshAfterConcatLoad(state);
        return;
    }

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
        success: function (data) {
            try {
                data = JSON.parse(data);
            }
            catch (e) {
                console.log(url);
                console.log(data);
                throw e;
            }
            if (data.status !== 'success') {
                alert(data.message);
                return;
            }

            f(data.data, es);
        },
        error: function () {
            alert('Error occurred when requesting via ajax. Please refresh the page and try again.');
        },
    });
}

// VIEWS ///////////////////////////////////////////////////////////////////////
// functions that get data from the global state and load it into UI elements

function recreateUser1Dropdown(state) {
    $('#user-1-dropdown-list').empty();
    $.each(state.user_1_dropdown_list, (i, element) => {
        if (state.anon_mode_enabled) {
            const hashedDisplayName = element.display_name !== '' ? hashString(element.display_name) : '';
            const hashedUserID = hashString(element.user_id);
            $('#user-1-dropdown-list').append(`<option value="${element.user_id}">(${element.percent}, ${element.match_count} hashes) ${hashedDisplayName} &lt;${hashedUserID}&gt;</option>`);
        }
        else {
            $('#user-1-dropdown-list').append(`<option value="${element.user_id}">(${element.percent}, ${element.match_count} hashes) ${element.display_name} &lt;${element.user_id}&gt;</option>`);
        }
    });
}

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

        if (state.anon_mode_enabled) {
            const hashedDisplayName = users.display_name !== '' ? hashString(users.display_name) : '';
            const hashedUserID = hashString(users.user_id);
            append_options += `>(${users.percent} hashes) ${hashedDisplayName} &lt;${hashedUserID}&gt; (version ${users.version}) `;
        }
        else {
            append_options += `>(${users.percent} hashes) ${users.display_name} &lt;${users.user_id}&gt; (version ${users.version}) `;
        }

        if (users.source_gradeable !== state.this_term_course_gradeable) {
            let humanified_source_gradeable = users.source_gradeable;
            humanified_source_gradeable = humanified_source_gradeable.replaceAll('__', '/');
            append_options += `(${humanified_source_gradeable})`;
        }
        append_options += '</option>';
    });
    $('#user-2-dropdown-list').append(append_options);

    if (state.user_2_selected.source_gradeable !== state.this_term_course_gradeable) {
        $('#swap-students-button').addClass('disabled');
    }
    else {
        $('#swap-students-button').removeClass('disabled');
    }
}

function refreshColorInfo(state) {
    let previousSelectedMark = null;
    state.editor1.operation(() => {
        state.editor2.operation(() => {
            // codemirror doesn't provide an easy way to remove text marks so we just wipe the contents of the document and reset
            const val = state.editor1.getDoc().getValue();
            const scrollPos = state.editor1.getScrollInfo();
            state.editor1.getDoc().setValue('');
            state.editor1.getDoc().setValue(val);
            state.editor1.scrollTo(0, scrollPos.top);

            $.each(state.color_info, (i, interval) => {
                let color = '';
                if (interval.type === 'match') {
                    color = 'match';
                }
                else if (interval.type === 'specific-match') {
                    color = 'specific-match';
                }
                else if (interval.type === 'provided') {
                    color = 'provided-code';
                }
                else if (interval.type === 'common') {
                    color = 'common-code';
                }
                else {
                    color = '';
                }

                const mp_text_marks = [];

                const wasPreviousSelection = interval.start_line - 1 === state.previous_selection.start_line && interval.end_line - 1 === state.previous_selection.end_line && interval.start_char - 1 === state.previous_selection.start_char && interval.end_char - 1 === state.previous_selection.end_char;

                $.each(interval.matching_positions, (i, mp) => {
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
                                original_color: 'specific-match',
                                start_line: mp.start_line - 1,
                                end_line: mp.end_line - 1,
                                start_char: mp.start_char - 1,
                                end_char: mp.end_char - 1,
                            },
                            className: 'specific-match-style',
                            css: `background-color: ${state.highlighting_colors['specific-match']};`,
                        },
                    );
                });

                const mark = state.editor1.markText(
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
                            original_color: color,
                            selected: false,
                            type: interval.type,
                            matching_positions: mp_text_marks,
                            others: interval.others,
                            start_line: interval.start_line - 1,
                            end_line: interval.end_line - 1,
                            start_char: interval.start_char - 1,
                            end_char: interval.end_char - 1,
                        },
                        className: wasPreviousSelection ? 'selected-style-red' : `${color}-style`,
                        css: `background-color: ${wasPreviousSelection ? state.highlighting_colors['selected-red'] : state.highlighting_colors[color]};`,
                    },
                );
                if (wasPreviousSelection) {
                    previousSelectedMark = mark;
                }
            });
        });
    });
    state.editor1.refresh();
    state.editor2.refresh();

    if (previousSelectedMark !== null) {
        handleClickedMark_editor1(state, previousSelectedMark);
    }
    hideLoadingIndicatorLeft();
    hideLoadingIndicatorRight();
}

function handleClickedMark_editor1(state, clickedMark, e = null) {
    // mark the clicked mark on both sides
    if (clickedMark.attributes.type === 'specific-match' && !clickedMark.attributes.selected) {
        clickedMark.attributes.selected = true;
        clickedMark.className = 'selected-style-red';
        clickedMark.css = `background-color: ${state.highlighting_colors['selected-red']};`;

        // highlight the matching regions on the right side
        state.editor2.operation(() => {
            $.each(clickedMark.attributes.matching_positions, (i, mp) => {
                mp.className = 'selected-style-red';
                mp.css = `background-color: ${state.highlighting_colors['selected-red']};`;
            });
            state.editor2.scrollIntoView({ line: clickedMark.attributes.matching_positions[0].attributes.end_line, ch: 0 }, 400);
        });

        // highlight the other matching regions on the left side
        state.editor1.getAllMarks().forEach((mark) => {
            $.each(mark.attributes.matching_positions, (i, mp) => {
                if (mp.attributes.start_line === clickedMark.attributes.matching_positions[0].attributes.start_line
                    && mp.attributes.end_line === clickedMark.attributes.matching_positions[0].attributes.end_line
                    && mp.attributes.start_char === clickedMark.attributes.matching_positions[0].attributes.start_char
                    && mp.attributes.end_char === clickedMark.attributes.matching_positions[0].attributes.end_char
                ) {
                    mark.attributes.selected = true;
                    mark.className = 'selected-style-red';
                    mark.css = `background-color: ${state.highlighting_colors['selected-red']};`;
                }
            });
        });
    }
    else if (clickedMark.attributes.type === 'match' || (clickedMark.attributes.type === 'specific-match' && clickedMark.attributes.selected)) {
        clickedMark.attributes.selected = true;
        clickedMark.className = 'selected-style-blue';
        clickedMark.css = `background-color: ${state.highlighting_colors['selected-blue']};`;

        $('#popup-to-show-matches-id').css('left', `${e.clientX + 2}px`);
        $('#popup-to-show-matches-id').css('top', `${e.clientY}px`);
        $('#popup-to-show-matches-id').empty();

        $.each(clickedMark.attributes.others, (i, other) => {
            let humanified_source_gradeable = other.source_gradeable;
            humanified_source_gradeable = humanified_source_gradeable.replaceAll('__', '/');

            const sg = other.source_gradeable === state.this_term_course_gradeable ? '' : ` (${humanified_source_gradeable})`;

            const other_user_id = state.anon_mode_enabled ? hashString(other.user_id) : other.user_id;
            $('#popup-to-show-matches-id').append(`
                    <li id="others_menu_${i}" class="ui-menu-item">
                        <div tabindex="-1" class="ui-menu-item-wrapper">
                            ${other_user_id}: ${other.version}${sg}
                        </div>
                    </li>
                `);
            $(`#others_menu_${i}`).on('click', () => {
                // hiding the popup and resetting the text color immediately makes the page feel faster
                $('#popup-to-show-matches-id').css('display', 'none');
                showLoadingIndicatorRight();
                clickedMark.className = `${clickedMark.attributes.original_color}-style`;
                clickedMark.css = `background-color: ${state.highlighting_colors[clickedMark.attributes.original_color]};`;
                state.editor2.getDoc().setValue('');
                state.editor2.refresh();
                state.editor1.refresh();

                // set the selected user in the user 2 dropdown
                $.each(state.user_2_dropdown_list, (i, item) => {
                    if (item.user_id === other.user_id && item.version === other.version && item.source_gradeable === other.source_gradeable) {
                        state.user_2_selected = item;
                        state.previous_selection.start_line = clickedMark.attributes.start_line;
                        state.previous_selection.end_line = clickedMark.attributes.end_line;
                        state.previous_selection.start_char = clickedMark.attributes.start_char;
                        state.previous_selection.end_char = clickedMark.attributes.end_char;
                    }
                });

                // all async so order doesn't particularly matter
                loadConcatenatedFileForEditor(state, 2);
                loadColorInfo(state);
                refreshUser2Dropdown(state);
            });
        });
        $('#popup-to-show-matches-id').css('display', 'block');
    }

    // Refresh editors
    state.editor1.refresh();
    state.editor2.refresh();
}

function handleClickedMark_editor2(state, clickedMark) {
    clickedMark.className = 'selected-style-red';
    clickedMark.css = `background-color: ${state.highlighting_colors['selected-red']};`;
    state.editor1.operation(() => {
        let first_mark = true;
        state.editor1.getAllMarks().forEach((mark) => {
            let mark_does_contain_mp = false;
            $.each(mark.attributes.matching_positions, (i, mp) => {
                if (mp.attributes.start_line === clickedMark.attributes.start_line
                    && mp.attributes.end_line === clickedMark.attributes.end_line
                    && mp.attributes.start_char === clickedMark.attributes.start_char
                    && mp.attributes.end_char === clickedMark.attributes.end_char
                ) {
                    mark_does_contain_mp = true;
                }
            });
            if (mark_does_contain_mp) {
                mark.attributes.selected = true;
                mark.className = 'selected-style-red';
                mark.css = `background-color: ${state.highlighting_colors['selected-red']};`;
                $.each(mark.attributes.matching_positions, (i, mp) => {
                    mp.className = 'selected-style-red';
                    mp.css = `background-color: ${state.highlighting_colors['selected-red']};`;
                    if (first_mark) {
                        state.editor1.scrollIntoView({ line: mark.attributes.end_line, ch: 0 }, 400);
                        first_mark = false;
                    }
                });
            }
        });
    });

    // Refresh editors
    state.editor1.refresh();
    state.editor2.refresh();
}

function handleClickedMarks(state) {
    state.editor1.getWrapperElement().onmouseup = function (e) {
        const lineCh = state.editor1.coordsChar({ left: e.clientX, top: e.clientY });
        let markers = state.editor1.findMarksAt(lineCh);

        // hide the "others" popup in case it was visible
        $('#popup-to-show-matches-id').css('display', 'none');

        // Only grab the first one if there is overlap...
        const clickedMark = markers[0];

        // reset all previous marks
        const marks_editor1 = state.editor1.getAllMarks();
        state.editor1.operation(() => {
            marks_editor1.forEach((mark) => {
                // If this mark is already blue, it's like we didn't click any mark
                if (mark.className === 'selected-style-blue') {
                    markers = [];
                }

                if (markers.length === 0 || mark !== clickedMark) {
                    mark.className = `${mark.attributes.original_color}-style`;
                    mark.css = `background-color: ${state.highlighting_colors[mark.attributes.original_color]};`;
                    mark.attributes.selected = false;
                }
            });
        });
        const marks_editor2 = state.editor2.getAllMarks();
        state.editor2.operation(() => {
            marks_editor2.forEach((mark) => {
                mark.className = `${mark.attributes.original_color}-style`;
                mark.css = `background-color: ${state.highlighting_colors[mark.attributes.original_color]};`;
            });
        });

        // Did not select a marker
        if (markers.length === 0) {
            state.editor1.refresh();
            state.editor2.refresh();
            return;
        }

        handleClickedMark_editor1(state, clickedMark, e);
    };

    state.editor2.getWrapperElement().onmouseup = function (e) {
        const lineCh = state.editor2.coordsChar({ left: e.clientX, top: e.clientY });
        const markers = state.editor2.findMarksAt(lineCh);

        // hide the "others" popup in case it was visible
        $('#popup-to-show-matches-id').css('display', 'none');

        // Only grab the first one if there is overlap...
        const clickedMark = markers[0];

        // reset all previous marks
        const marks_editor1 = state.editor1.getAllMarks();
        state.editor1.operation(() => {
            marks_editor1.forEach((mark) => {
                if (markers.length === 0 || mark !== clickedMark) {
                    mark.className = mark.attributes.original_color;
                    mark.css = `background-color: ${state.highlighting_colors[mark.attributes.original_color]};`;
                    mark.attributes.selected = false;
                }
            });
        });
        const marks_editor2 = state.editor2.getAllMarks();
        state.editor2.operation(() => {
            marks_editor2.forEach((mark) => {
                mark.className = mark.attributes.original_color;
                mark.css = `background-color: ${state.highlighting_colors[mark.attributes.original_color]};`;
            });
        });

        // Did not select a marker
        if (markers.length === 0) {
            state.editor1.refresh();
            state.editor2.refresh();
            return;
        }

        handleClickedMark_editor2(state, clickedMark);
    };
}

function showLoadingIndicatorLeft() {
    $('.left-sub-item-middle').addClass('blurry');
    $('.left-loader').css('display', 'block');
}

function hideLoadingIndicatorLeft() {
    $('.left-sub-item-middle').removeClass('blurry');
    $('.left-loader').css('display', 'none');
}

function showLoadingIndicatorRight() {
    $('.right-sub-item-middle').addClass('blurry');
    $('.right-loader').css('display', 'block');
}

function hideLoadingIndicatorRight() {
    $('.right-sub-item-middle').removeClass('blurry');
    $('.right-loader').css('display', 'none');
}

function showPlagiarismHighKey() {
    $('#Plagiarism-Highlighting-Key').css('display', 'block');
}

function toggleFullScreenMode() {
    $('main#main').toggleClass('full-screen-mode');
}

function toggleAnonymousMode(state) {
    if (state.anon_mode_enabled) {
        $('#toggle-anon-mode-btn').text('Enter Anonymous Mode');
        state.anon_mode_enabled = false;
        localStorage.removeItem('plagiarism-anon-mode-enabled');
    }
    else {
        $('#toggle-anon-mode-btn').text('Exit Anonymous Mode');
        state.anon_mode_enabled = true;
        localStorage.setItem('plagiarism-anon-mode-enabled', 'true');
    }

    // update the user 1 dropdown, which triggers the other dropdowns to update as well
    recreateUser1Dropdown(state);
    user1DropdownChanged(state);
}

// currently selects user 2 in the user 1 dropdown and then reloads everything without selecting the proper user 2
// further discussion is necessary regarding whether this behavior is a good or bad thing and whether this should
// be a "swap students" button or a "move user 2 to the left side" button
function swapStudents(state) {
    $('#user-1-version-dropdown-list').val(state.user_2_selected.version);
    $('#user-1-dropdown-list').val(state.user_2_selected.user_id);
    user1DropdownChanged(state);
}

// takes in a string and outputs an 8-character hash of it
function hashString(input) {
    let result = 0;
    for (let i = 0; i < input.length; i++) {
        result = ((result << 5) - result) + input.charCodeAt(i);
        result = result & result;
    }
    result = Math.abs(result);
    return result.toString(16);
}
