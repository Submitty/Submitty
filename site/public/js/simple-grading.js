/* global WebSocketClient, registerKeyHandler, student_full, csrfToken, buildCourseUrl, submitAJAX, captureTabInModal, luxon */
/* exported setupSimpleGrading, checkpointRollTo, showSimpleGraderStats */

function calcSimpleGraderStats(action) {
    // start variable declarations
    let average = 0;                // overall average
    let stddev = 0;                 // overall stddev
    const component_averages = [];    // average of each component
    const component_stddevs = [];     // stddev of each component
    const section_counts = {};        // counts of the number of users in each section    (used to calc average per section)
    const section_sums = {};          // sum of scores per section                        (used with ^ to calc average per section)
    const section_sums_sqrs = {};     // sum of squares of scores per section             (used with ^ and ^^ to calc stddev per section)
    let num_graded = 0;             // count how many students have a nonzero grade
    let c = 0;                      // count the current component number
    let num_users = 0;              // count the number of users
    const has_graded = [];            // keeps track of whether or not each user already has a nonzero grade
    let elems;                      // the elements of the current component
    let elem_type;                  // the type of element that has the scores
    let data_attr;                  // the data attribute in which the score is stored
    // end variable declarations

    // start initial setup: use action to assign values to elem_type and data_attr
    if (action === 'lab') {
        elem_type = 'td';
        data_attr = 'data-score';
    }
    else if (action === 'numeric') {
        elem_type = 'input';
        data_attr = 'value';
    }
    else {
        console.log('Invalid grading type:');
        console.log(action);
        return;
    }
    // get all of the elements with the scores for the first component
    elems = $(`${elem_type}[id^=cell-][id$=0]`);
    // end initial setup

    // start main loop: iterate by component and calculate stats
    while (elems.length > 0) {
        // eslint-disable-next-line eqeqeq
        if (action === 'lab' || elems.data('num') == true) { // do all components for lab and ignore text components for numeric
            let sum = 0;                            // sum of the scores
            let sum_sqrs = 0;                       // sum of the squares of the scores
            let user_num = 0;                       // the index for has_graded so that it can be tracked whether or not there is a grade
            let section;                            // the section of the current user (registration or rotating)
            let reg_section;                        // the registration section of the current user
            elems.each(function() {
                if (action === 'lab') {
                    reg_section = $(this).parent().find('td:nth-child(2)').text();              // second child of parent has registration section as text
                    section = $(this).parent().parent().attr('id').split('-')[1];               // grandparent id has section
                }
                else if (action === 'numeric') {
                    reg_section = $(this).parent().parent().find('td:nth-child(2)').text();     // second child of grandparent has registration section as text
                    section = $(this).parent().parent().parent().attr('id').split('-')[1];      // great-grandparent id has section
                }

                if (reg_section !== '') {                 // if section is not null
                    if (!(section in section_counts)) {
                        section_counts[section] = 0;
                        section_sums[section] = 0;
                        section_sums_sqrs[section] = 0;
                    }
                    if (c === 0) {                    // on the first iteration of the while loop...
                        num_users++;                // ...sum up the number of users...
                        section_counts[section]++;  // ...sum up the number of users per section...
                        has_graded.push(false);     // ...and populate the has_graded array with false

                        // for the first component, calculate total stats by section.
                        let score_elems;            // the score elements for this user
                        let score = 0;              // the total score of this user
                        if (action === 'lab') {
                            score_elems = $(this).parent().find('td.cell-grade');
                        }
                        else if (action === 'numeric') {
                            score_elems = $(this).parent().parent().find('input[data-num=true]');
                        }

                        score_elems.each(function() {
                            score += parseFloat($(this).attr(data_attr));
                        });

                        // add to the sums and sums_sqrs
                        section_sums[section] += score;
                        section_sums_sqrs[section] += score**2;
                    }
                    const score = parseFloat($(this).attr(data_attr));
                    if (!has_graded[user_num]) {     // if they had no nonzero score previously...
                        has_graded[user_num] = score !== 0;
                        if (has_graded[user_num]) {  // ...but they have one now
                            num_graded++;
                        }
                    }
                    // add to the sum and sum_sqrs
                    sum += score;
                    sum_sqrs += score**2;
                }
                user_num++;
            });

            // calculate average and stddev from sums and sum_sqrs
            component_averages.push(sum/num_users);
            component_stddevs.push(Math.sqrt(Math.max(0, (sum_sqrs - sum**2 / num_users) / num_users)));
        }

        // get the elements for the next component
        elems = $(`${elem_type}[id^=cell-][id$=${(++c).toString()}]`);
    }
    // end main loop

    // start finalizing: find total stats place all stats into their proper elements
    const stats_popup = $('#simple-stats-popup'); // the popup with all the stats in it.
    for (c = 0; c < component_averages.length; c++) {
        average += component_averages[c];                                                               // sum up component averages to get the total average
        stddev += component_stddevs[c]**2;                                                              // sum up squares of component stddevs (sqrt after all summed) to get the total stddev
        stats_popup.find(`#avg-component-${c.toString()}`).text(component_averages[c].toFixed(2));      // set the display text of the proper average element
        stats_popup.find(`#stddev-component-${c.toString()}`).text(component_stddevs[c].toFixed(2));    // set the display text of the proper stddev element
    }

    stddev = Math.sqrt(stddev);                                 // take sqrt of sum of squared stddevs to get total stddev
    stats_popup.find('#avg-total').text(average.toFixed(2));    // set the display text of the proper average element
    stats_popup.find('#stddev-total').text(stddev.toFixed(2));  // set the display text of the proper stddev element


    let section_average;
    let section_stddev;
    for (const section in section_counts) {
        section_average = section_sums[section] / section_counts[section];
        section_stddev = Math.sqrt(Math.max(0, (section_sums_sqrs[section] - section_sums[section]**2 / section_counts[section]) / section_counts[section]));
        stats_popup.find(`#avg-section-${section}`).text(section_average.toFixed(2));         // set the display text of the proper average element
        stats_popup.find(`#stddev-section-${section}`).text(section_stddev.toFixed(2));       // set the display text of the proper stddev element
    }
    const num_graded_elem = stats_popup.find('#num-graded');
    $(num_graded_elem).text(`${num_graded.toString()}/${num_users.toString()}`);
    // end finalizing
}

function showSimpleGraderStats(action) {
    if ($('#simple-stats-popup').css('display') === 'none') {
        calcSimpleGraderStats(action);
        $('.popup').css('display', 'none');
        $('#simple-stats-popup').css('display', 'block');
        captureTabInModal('simple-stats-popup');
        $(document).on('click', (e) => {                                           // event handler: when clicking on the document...
            if ($(e.target).attr('id') !== 'simple-stats-btn'                             // ...if neither the stats button..
               && $(e.target).closest('div').attr('id') !== 'simple-stats-popup') {      // ...nor the stats popup are being clicked...
                $('#simple-stats-popup').css('display', 'none');                        // ...hide the stats popup...
                $(document).off('click');                                               // ...and remove this event handler
            }
        });
    }
    else {
        $('#simple-stats-popup').css('display', 'none');
        $(document).off('click');
    }
}

function updateCheckpointCells(elems, scores, no_cookie) {
    // see if we're setting all of a row to one score
    let singleScore = null;
    if (scores && typeof scores !== 'object') {
        singleScore = scores;
    }

    // keep track of changes
    const new_scores = {};
    const old_scores = {};

    elems = $(elems);
    elems.each((idx, el) => {
        const elem = $(el);
        let set_new = false;

        old_scores[elem.data('id')] = elem.data('score');

        // if one score passed, set all elems to it
        if (singleScore) {
            elem.data('score', singleScore);
            set_new = true;
        }
        // otherwise match up object IDs with a scores object
        else if (scores && elem.data('id') in scores) {
            elem.data('score', scores[elem.data('id')]);
            set_new = true;
        }
        // if no score set, toggle through options
        else if (!scores) {
            if (elem.data('score') === 1.0) {
                elem.data('score', 0.5);
            }
            else if (elem.data('score') === 0.5) {
                elem.data('score', 0);
            }
            else {
                elem.data('score', 1);
            }
            set_new = true;
        }

        if (set_new) {
            new_scores[elem.data('id')] = elem.data('score');

            // update css to reflect score
            if (elem.data('score') === 1.0) {
                elem.addClass('simple-full-credit');
            }
            else if (elem.data('score') === 0.5) {
                elem.removeClass('simple-full-credit');
                elem.css('background-color', '');
                elem.addClass('simple-half-credit');
            }
            else {
                elem.removeClass('simple-half-credit');
                elem.css('background-color', '');
            }

            //set new grader data
            elem.data('grader', $('#data-table').data('current-grader'));

            // create border we can animate to reflect ajax status
            elem.css('border-right', `60px solid ${getComputedStyle(elem.parent()[0]).getPropertyValue('background-color')}`);
        }
    });

    const parent = $(elems[0]).parent();
    const user_id = parent.data('user');
    const g_id = parent.data('gradeable');

    // update cookie for undo/redo
    if (!no_cookie) {
        generateCheckpointCookie(user_id, g_id, old_scores, new_scores);
    }

    // Update the buttons to reflect that they were clicked
    submitAJAX(
        buildCourseUrl(['gradeable', g_id, 'grading']),
        {
            'csrf_token': csrfToken,
            'user_id': user_id,
            'old_scores': old_scores,
            'scores': new_scores,
        },
        (returned_data) => {
            // Validate that the Simple Grader backend correctly saved components before updating
            const expected_vals = JSON.stringify(Object.entries(new_scores).map(String));
            const returned_vals = JSON.stringify(Object.entries(returned_data['data']).map(String));
            if (expected_vals === returned_vals) {
                elems.each((idx, elem) => {
                    elem = $(elem);
                    elem.animate({'border-right-width': '0px'}, 400); // animate the box
                    elem.attr('data-score', elem.data('score'));      // update the score
                    elem.attr('data-grader', elem.data('grader'));    // update new grader
                    elem.find('.simple-grade-grader').text(elem.data('grader'));
                });
                window.socketClient.send({'type': 'update_checkpoint', 'elem': elems.attr('id').split('-')[3], 'user': parent.data('anon'), 'score': elems.data('score'), 'grader': elems.data('grader')});
            }
            else {
                console.log('Save error: returned data:', returned_vals, 'does not match expected new data:', expected_vals);
                elems.each((idx, elem) => {
                    elem = $(elem);
                    elem.stop(true, true);
                    elem.css('border-right', '60px solid #DA4F49');
                });
            }
        },
        () => {
            elems.each((idx, elem) => {
                elem = $(elem);
                elem.stop(true, true);
                elem.css('border-right', '60px solid #DA4F49');
            });
        },
    );
}

function getCheckpointHistory(g_id) {
    const history = Cookies.get(`${g_id}_history`);
    try {
        return JSON.parse(history) || [0];
    }
    catch (e) {
        return [0];
    }
}

function setCheckpointHistory(g_id, history) {
    const DateTime = luxon.DateTime;
    const now = DateTime.now();
    const expiration_date = now.plus({ days: 1 });
    Cookies.set(`${g_id}_history`, JSON.stringify(history), { expires: expiration_date.toJSDate() });
}

function generateCheckpointCookie(user_id, g_id, old_scores, new_scores) {
    // format: [pointer, [studentID1, old_scores], [studentID1, new_scores], [studentID2, old_scores ... where studentid1 is the oldest edited
    // pointer should be the index of the current state in history. new_scores should be true for all leading up to that state
    // the pointer is bound by 1, history.length-1
    let history = getCheckpointHistory(g_id);

    // erase future snapshots on write
    if (history[0] < history.length-1) {
        history = history.slice(0, history[0]);
    }

    // write new student entry
    history.push([user_id, old_scores]);
    history.push([user_id, new_scores]);

    // keep max history of 5 entries (1 buffer for pointer, 5x2 for old/new)
    if (history.length > 11) {
        history.splice(1, 2);
    }
    else {
        history[0] = history.length-1; // increment to latest new_scores
    }

    setCheckpointHistory(g_id, history);
}

function setupCheckboxCells() {
    // jQuery for the elements with the class cell-grade (those in the component columns)
    $('td.cell-grade').click(function() {
        updateCheckpointCells(this);
    });

    // Initialize based on cookies
    const showGradersCheckbox = $('#show-graders');
    const showDatesGradedCheckbox = $('#show-dates');

    if (Cookies.get('show_grader') === 'true') {
        $('.simple-grade-grader').css('display', 'block');
        showGradersCheckbox.prop('checked', true);
    }

    if (Cookies.get('show_dates') === 'true') {
        $('.simple-grade-date').css('display', 'block');
        showDatesGradedCheckbox.prop('checked', true);
    }

    // show all the hidden grades when showGradersCheckbox is clicked
    showGradersCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            $('.simple-grade-grader').css('display', 'block');
        }
        else {
            $('.simple-grade-grader').css('display', 'none');
        }
        Cookies.set('show_grader', showGradersCheckbox.is(':checked'));
    });

    // show all the hidden dates when showDatesGradedCheckbox is clicked
    showDatesGradedCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            $('.simple-grade-date').css('display', 'block');
        }
        else {
            $('.simple-grade-date').css('display', 'none');
        }
        Cookies.set('show_dates', showDatesGradedCheckbox.is(':checked'));
    });
}

function setupNumericTextCells() {
    $('.cell-grade').change(function() {
        let elem = $(this);
        const split_id = elem.attr('id').split('-');
        const row_el = $(`tr#row-${split_id[1]}-${split_id[2]}`);

        const scores = {};
        const old_scores = {};
        let total = 0;

        let value = this.value;
        const numbers = /^[0-9]*\.?[0-9]*$/;

        if (this.tagName.toLowerCase() === 'input') {
            // Empty input is ok for comment but not numeric cells
            if (!this.value) {
                this.value = 0;
            }
            else if (!this.value.match(numbers)) {
                alert('Score should be a positive number');
                this.value = 0;
            }
            // Input greater than the max_clamp for the component is not allowed
            else {
                if (elem.data('maxclamp') && elem.data('maxclamp') < this.value) {
                    alert(`Score should be less than or equal to the max clamp value: ${elem.data('maxclamp')}`);
                    this.value = 0;
                }
            }
        }

        //eslint-disable-next-line eqeqeq
        if (this.value == 0) {
            elem.css('color', '--standard-light-medium-gray');
        }
        else {
            elem.css('color', '');
        }

        row_el.find('.cell-grade').each(function() {
            elem = $(this);
            if (elem.data('num')) {
                total += parseFloat(elem.val());
            }

            // ensure value is string (might not be on initial load from twig)
            old_scores[elem.data('id')] = `${elem.data('origval')}`;
            scores[elem.data('id')] = elem.val();

            // save old value so we can verify data is not stale
            elem.data('origval', elem.val());
            elem.attr('data-origval', elem.val());
        });

        value = this.value;

        submitAJAX(
            buildCourseUrl(['gradeable', row_el.data('gradeable'), 'grading']),
            {
                'csrf_token': csrfToken,
                'user_id': row_el.data('user'),
                'old_scores': old_scores,
                'scores': scores,
            },

            () => {
                // Finds the element that stores the total and updates it to reflect increase
                //eslint-disable-next-line eqeqeq
                if (row_el.find('.cell-total').text() != total) {
                    row_el.find('.cell-total').text(total).hide().fadeIn('slow');
                }

                window.socketClient.send({'type': 'update_numeric', 'elem': split_id[3], 'user': row_el.data('anon'), 'value': value, 'total': total});
            },
            () => {
                elem.css('background-color', '--standard-light-pink');
            },
        );
    });

    $('input[class=csvButtonUpload]').change(() => {
        const confirmation = window.confirm('WARNING! \nPreviously entered data may be overwritten! ' +
        'This action is irreversible! Are you sure you want to continue?\n\n Do not include a header row in your CSV. Format CSV using one column for ' +
        'student id and one column for each field. Columns and field types must match.');
        if (confirmation) {
            const f = $('#csvUpload').get(0).files[0];
            if (f) {
                const reader = new FileReader();
                reader.readAsText(f);
                reader.onload = function() {
                    let breakOut = false; //breakOut is used to break out of the function and alert the user the format is wrong
                    const lines = (reader.result).trim().split(/\r\n|\n|\r/);
                    let tempArray = lines[0].split(',');
                    const csvLength = tempArray.length; //gets the length of the array, all the tempArray should be the same length
                    for (let k = 0; k < lines.length && !breakOut; k++) {
                        tempArray = lines[k].split(',');
                        breakOut = tempArray.length !== csvLength; //if tempArray is not the same length, break out
                    }
                    let textChecker = 0;
                    let num_numeric = 0;
                    let num_text = 0;
                    const user_ids = [];
                    let get_once = true;
                    let gradeable_id = '';
                    if (!breakOut) {
                        $('.cell-all').each(function() {
                            user_ids.push($(this).parent().data('user'));
                            if (get_once) {
                                num_numeric = $(this).parent().parent().data('numnumeric');
                                num_text = $(this).parent().parent().data('numtext');
                                gradeable_id = $(this).parent().data('gradeable');
                                get_once = false;
                                if (csvLength !== 4 + num_numeric + num_text) {
                                    breakOut = true;
                                    return false;
                                }
                                let k = 3; //checks if the file has the right number of numerics
                                tempArray = lines[0].split(',');
                                if (num_numeric > 0) {
                                    for (k = 3; k < num_numeric + 4; k++) {
                                        if (isNaN(Number(tempArray[k]))) {
                                            breakOut = true;
                                            return false;
                                        }
                                    }
                                }

                                //checks if the file has the right number of texts
                                while (k < csvLength) {
                                    textChecker++;
                                    k++;
                                }
                                if (textChecker !== num_text) {
                                    breakOut = true;
                                    return false;
                                }
                            }
                        });
                    }
                    if (!breakOut) {
                        submitAJAX(
                            buildCourseUrl(['gradeable', gradeable_id, 'grading', 'csv']),
                            {'csrf_token': csrfToken, 'users': user_ids,
                                'num_numeric' : num_numeric, 'big_file': reader.result},
                            (returned_data) => {
                                $('.cell-all').each(function() {
                                    for (let x = 0; x < returned_data['data'].length; x++) {
                                        if ($(this).parent().data('user') === returned_data['data'][x]['username']) {
                                            const starting_index1 = 0;
                                            const starting_index2 = 3;
                                            const value_str = 'value_';
                                            const status_str = 'status_';
                                            let value_temp_str = 'value_';
                                            let status_temp_str = 'status_';
                                            let total = 0;
                                            let y = starting_index1;
                                            let z = starting_index2; //3 is the starting index of the grades in the csv
                                            //puts all the data in the form
                                            for (z = starting_index2; z < num_numeric + starting_index2; z++, y++) {
                                                value_temp_str = value_str + y;
                                                status_temp_str = status_str + y;
                                                const elem = $(`#cell-${$(this).parent().parent().data('section')}-${$(this).parent().data('row')}-${z-starting_index2}`);
                                                elem.val(returned_data['data'][x][value_temp_str]);
                                                if (returned_data['data'][x][status_temp_str] === 'OK') {
                                                    elem.css('background-color', '--main-body-white');
                                                }
                                                else {
                                                    elem.css('background-color', '--standard-light-pink');
                                                }

                                                // eslint-disable-next-line eqeqeq
                                                if (elem.val() == 0) {
                                                    elem.css('color', '--standard-light-medium-gray');
                                                }
                                                else {
                                                    elem.css('color', '');
                                                }

                                                total += Number(elem.val());
                                            }
                                            // $('#total-'+$(this).parent().data("row")).val(total);
                                            const split_row = $(this).parent().attr('id').split('-');
                                            $(`#total-${split_row[1]}-${split_row[2]}`).val(total);
                                            z++;
                                            let counter = 0;
                                            while (counter < num_text) {
                                                value_temp_str = value_str + y;
                                                status_temp_str = status_str + y;
                                                $(`#cell-${$(this).parent().data('row')}-${z-starting_index2-1}`).val(returned_data['data'][x][value_temp_str]);
                                                z++;
                                                y++;
                                                counter++;
                                            }

                                            x = returned_data['data'].length;
                                        }
                                    }
                                });
                            },
                            () => {
                                alert('submission error');
                            },
                        );
                    }

                    if (breakOut) {
                        alert('CSV upload failed! Format file incorrect.');
                    }
                };
            }
        }
        else {
            let f = $('#csvUpload');
            f.replaceWith(f = f.clone(true));
        }
    });
}

function setupSimpleGrading(action) {
    if (action === 'lab') {
        setupCheckboxCells();
    }
    else if (action === 'numeric') {
        setupNumericTextCells();
    }

    // search bar code starts here (see site/app/templates/grading/StudentSearch.twig for #student-search)

    // highlights the first jquery-ui autocomplete result if there is only one
    function highlightOnSingleMatch(is_remove) {
        const matches = $('#student-search > ul > li');
        // if there is only one match, use jquery-ui css to highlight it so the user knows it is selected
        if (matches.length === 1) {
            $(matches[0]).children('div').addClass('ui-state-active');
        }
        else if (is_remove) {
            $(matches[0]).children('div').removeClass('ui-state-active');
        }
    }

    let dont_hotkey_focus = true; // set to allow toggling of focus on input element so we can use enter as hotkey

    // prevent hotkey focus while already focused, so we dont override search functionality
    $('#student-search-input').focus(() => {
        dont_hotkey_focus = true;
    });

    // refocus on the input field by pressing enter
    $(document).on('keyup', (event) => {
        if (event.code === 'Enter' && !dont_hotkey_focus) {
            $('#student-search-input').focus();
        }
    });

    // moves the selection to an adjacent cell
    function movement(direction) {
        const prev_cell = $('.cell-grade:focus');
        if (prev_cell.length) {
            // ids have the format cell-#SECTION-ROW#-COL#
            const new_selector_array = prev_cell.attr('id').split('-');

            if (new_selector_array[1].length) {
                new_selector_array[1] = parseInt(new_selector_array[1]);
            }
            new_selector_array[2] = parseInt(new_selector_array[2]);
            new_selector_array[3] = parseInt(new_selector_array[3]);

            // update row and col to get new val
            if (direction === 'up') {
                new_selector_array[2] -= 1;
            }
            else if (direction === 'down') {
                new_selector_array[2] += 1;
            }
            else if (direction === 'left') {
                new_selector_array[3] -= 1;
            }
            else if (direction === 'right') {
                new_selector_array[3] += 1;
            }

            if (new_selector_array[2] < 0 && direction === 'up') {
                new_selector_array[2] += 1;
                // // Selection needs to move to above the null section
                if (new_selector_array[1] === '') {
                    new_selector_array[1] = 1;
                    // eslint-disable-next-line no-constant-condition
                    while (true) {
                        const temp_cell = $(`#${new_selector_array.join('-')}`);
                        if (!temp_cell.length) {
                            break;
                        }
                        new_selector_array[1] += 1;
                    }
                }

                // // Selection needs to move to above section, if one exists
                // // Find the previous section visible to the grader
                while (new_selector_array[1] >= 0) {
                    new_selector_array[1] -= 1;
                    const temp_cell = $(`#${new_selector_array.join('-')}`);
                    if (temp_cell.length) {
                        break;
                    }
                }
                // Find the last cell in this section
                let new_cell = $(`#${new_selector_array.join('-')}`);
                while (new_cell.length) {
                    new_selector_array[2] += 1;
                    new_cell = $(`#${new_selector_array.join('-')}`);
                }
                new_selector_array[2] -= 1;

                new_cell = $(`#${new_selector_array.join('-')}`);
                if (new_cell.length) {
                    prev_cell.blur();
                    new_cell.focus();
                    new_cell.select(); // used to select text in input cells

                    if ((direction === 'up' || direction === 'down') && !new_cell.isInViewport()) {
                        $('html, body').animate( { scrollTop: new_cell.offset().top - $(window).height()/2}, 50);
                    }
                }
            }
            else {
                // Try once with the new cell generated above, otherwise try moving down to the next section
                let tries;
                for (tries = 0; tries < 3; tries++) {
                    // get new cell
                    const new_cell = $(`#${new_selector_array.join('-')}`);
                    if (new_cell.length) {
                        prev_cell.blur();
                        new_cell.focus();
                        new_cell.select(); // used to select text in input cells

                        if ((direction === 'up' || direction === 'down') && !new_cell.isInViewport()) {
                            $('html, body').animate( { scrollTop: new_cell.offset().top - $(window).height()/2}, 50);
                        }
                        break;
                    }

                    if (direction === 'down') {
                        // Check if cell needs to move beyond null section
                        if (new_selector_array[1] === '') {
                            break;
                        }

                        // Check if cell needs to move to next section
                        new_selector_array[1] += 1;
                        new_selector_array[2] = 0;

                        // Check if cell needs to move to null section
                        if (tries === 1 && new_selector_array[1] !== '') {
                            new_selector_array[1] = '';
                        }
                    }
                    else {
                        break;
                    }
                }
            }
        }
    }

    // default key movement
    $(document).on('keydown', (event) => {
        // if input cell selected, use this to check if cursor is in the right place
        const input_cell = $('input.cell-grade:focus, textarea.cell-grade:focus');
        if (!input_cell.length) {
            return;
        }

        const typingMode = input_cell[0].dataset.typing === true || input_cell[0].dataset.typing === 'true';

        if (event.code === 'Escape') {
            event.preventDefault();
            // If in typing mode, exit typing mode
            if (typingMode) {
                input_cell[0].dataset.typing = false;
            }
            // If not in typing mode, blur the input
            else {
                input_cell[0].blur();
            }
            return;
        }

        // Exit if in typing mode
        if (typingMode) {
            return;
        }

        // if there is no selection OR there is a selection to the far left with 0 length
        if (event.code === 'ArrowLeft') {
            event.preventDefault();
            movement('left');
        }
        else if (event.code === 'ArrowUp') {
            event.preventDefault();
            movement('up');
        }
        // if there is no selection OR there is a selection to the far right with 0 length
        else if (event.code === 'ArrowRight') {
            event.preventDefault();
            movement('right');
        }
        else if (event.code === 'ArrowDown') {
            event.preventDefault();
            movement('down');
        }
    });

    // register empty function locked event handlers for "enter" so they show up in the hotkeys menu
    registerKeyHandler({name: 'Search', code: 'Enter', locked: true}, () => {});
    registerKeyHandler({name: 'Move Right', code: 'ArrowRight', locked: true}, () => {});
    registerKeyHandler({name: 'Move Left', code: 'ArrowLeft', locked: true}, () => {});
    registerKeyHandler({name: 'Move Up', code: 'ArrowUp', locked: true}, () => {});
    registerKeyHandler({name: 'Move Down', code: 'ArrowDown', locked: true}, () => {});

    // check if a cell is focused, then update value
    function keySetCurrentCell(event, options) {
        const cell = $('.cell-grade:focus');
        if (cell.length) {
            updateCheckpointCells(cell, options.score);
        }
    }

    // check if a cell is focused, then update the entire row
    function keySetCurrentRow(event, options) {
        const cell = $('.cell-grade:focus');
        if (cell.length) {
            updateCheckpointCells(cell.parent().find('.cell-grade'), options.score);
        }
    }

    // register keybinds for grading controls
    if (action === 'lab') {
        registerKeyHandler({ name: 'Set Cell to 0', code: 'KeyZ', options: {score: 0} }, keySetCurrentCell);
        registerKeyHandler({ name: 'Set Cell to 0.5', code: 'KeyX', options: {score: 0.5} }, keySetCurrentCell);
        registerKeyHandler({ name: 'Set Cell to 1', code: 'KeyC', options: {score: 1} }, keySetCurrentCell);
        registerKeyHandler({ name: 'Cycle Cell Value', code: 'KeyV', options: {score: null} }, keySetCurrentCell);
        registerKeyHandler({ name: 'Set Row to 0', code: 'KeyA', options: {score: 0} }, keySetCurrentRow);
        registerKeyHandler({ name: 'Set Row to 0.5', code: 'KeyS', options: {score: 0.5} }, keySetCurrentRow);
        registerKeyHandler({ name: 'Set Row to 1', code: 'KeyD', options: {score: 1} }, keySetCurrentRow);
        registerKeyHandler({ name: 'Cycle Row Value', code: 'KeyF', options: {score: null} }, keySetCurrentRow);
    }

    // make sure to show focused cell when covered by student info
    $('.scrollable-table td[class^="option-"] > .cell-grade').on('focus', function() {
        const lastInfoBox = $(this).parent().parent().children('td:not([class^="option-"])').last();
        const boxRect = lastInfoBox[0].getBoundingClientRect();
        const inputRect = $(this).parent()[0].getBoundingClientRect();

        const diff = boxRect.right - inputRect.left;
        if (diff < 0) {
            return;
        }

        $('.scrollable-table')[0].scrollLeft -= diff;
    });

    // when pressing enter in the search bar, go to the corresponding element
    $('#student-search-input').on('keyup', function(event) {
        if (event.code === 'Enter') { // Enter
            this.blur();
            const value = $(this).val();
            if (value !== '') {
                const prev_cell = $('.cell-grade:focus');
                // get the row number of the table element with the matching id
                const tr_elem = $(`table tbody tr[data-user="${value}"]`);
                // if a match is found, then use it to find the cell
                if (tr_elem.length > 0) {
                    const split_id = tr_elem.attr('id').split('-');
                    const new_cell = $(`#cell-${split_id[1]}-${split_id[2]}-0`);
                    prev_cell.blur();
                    new_cell.focus();
                    $('html, body').animate( { scrollTop: new_cell.offset().top - $(window).height()/2}, 50);
                }
                else {
                    // if no match is found and there is at least 1 matching autocomplete label, find its matching value
                    const first_match = $('#student-search > ul > li');
                    if (first_match.length === 1) {
                        const first_match_label = first_match.text();
                        let first_match_value = '';
                        for (let i = 0; i < student_full.length; i++) {      // NOTE: student_full comes from StudentSearch.twig script
                            if (student_full[i]['label'] === first_match_label) {
                                first_match_value = student_full[i]['value'];
                                break;
                            }
                        }
                        this.focus();
                        $(this).val(first_match_value); // reset the value...
                        $(this).trigger(event);    // ...and retrigger the event
                    }
                    else {
                        alert('ERROR:\n\nInvalid user.');
                        this.focus();                       // refocus on the input field
                    }
                }
            }
        }
    });

    $('#student-search-input').on('keydown', () => {
        highlightOnSingleMatch(false);
    });
    $('#student-search').on('DOMSubtreeModified', () => {
        highlightOnSingleMatch(true);
    });

    // clear the input field when it is focused
    $('#student-search-input').on('focus', function() {
        $(this).val('');
    });

    initSocketClient();

}

function initSocketClient() {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        switch (msg.type) {
            case 'update_checkpoint':
                checkpointSocketHandler(msg.elem, msg.user, msg.score, msg.grader);
                break;
            case 'update_numeric':
                numericSocketHandler(msg.elem, msg.user, msg.value, msg.total);
                break;
            default:
                console.log('Undefined message received');
        }
    };
    const gradeable_id = window.location.pathname.split('gradeable/')[1].split('/')[0];
    window.socketClient.open(gradeable_id);
}

function checkpointSocketHandler(elem_id, anon_id, score, grader) {
    // search for the user within the table
    const tr_elem = $(`table tbody tr[data-anon="${anon_id}"]`);
    // if a match is found, then use it to find animate the correct cell
    if (tr_elem.length > 0) {
        const split_id = tr_elem.attr('id').split('-');
        const elem = $(`#cell-${split_id[1]}-${split_id[2]}-${elem_id}`);
        elem.data('score', score);
        elem.attr('data-score', score);
        elem.data('grader', grader);
        elem.attr('data-grader', grader);
        elem.find('.simple-grade-grader').text(grader);
        switch (score) {
            case 1.0:
                elem.addClass('simple-full-credit');
                break;
            case 0.5:
                elem.removeClass('simple-full-credit');
                elem.css('background-color', '');
                elem.addClass('simple-half-credit');
                break;
            default:
                elem.removeClass('simple-half-credit');
                elem.css('background-color', '');
                break;
        }
        elem.css('border-right', `60px solid ${getComputedStyle(elem.parent()[0]).getPropertyValue('background-color')}`);
        elem.animate({'border-right-width': '0px'}, 400);
    }
}

function numericSocketHandler(elem_id, anon_id, value, total) {
    const tr_elem = $(`table tbody tr[data-anon="${anon_id}"]`);
    if (tr_elem.length > 0) {
        const split_id = tr_elem.attr('id').split('-');
        const elem = $(`#cell-${split_id[1]}-${split_id[2]}-${elem_id}`);
        elem.data('origval', value);
        elem.attr('data-origval', value);
        elem.val(value);
        elem.css('background-color', '--always-default-white');
        // eslint-disable-next-line eqeqeq
        if (value === 0) {
            elem.css('color', '--standard-light-medium-gray');
        }
        else {
            elem.css('color', '');
        }
        // eslint-disable-next-line eqeqeq
        if (elem.parent().siblings('.option-small-output').children('.cell-total').text() !== total) {
            elem.parent().siblings('.option-small-output').children('.cell-total').text(total).hide().fadeIn('slow');
        }
    }
}
