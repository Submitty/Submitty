function calcSimpleGraderStats(action) {
    // start variable declarations
    var average = 0;                // overall average
    var stddev = 0;                 // overall stddev
    var component_averages = [];    // average of each component
    var component_stddevs = [];     // stddev of each component
    var section_counts = {};        // counts of the number of users in each section    (used to calc average per section)
    var section_sums = {};          // sum of scores per section                        (used with ^ to calc average per section)
    var section_sums_sqrs = {};     // sum of squares of scores per section             (used with ^ and ^^ to calc stddev per section)
    var num_graded = 0;             // count how many students have a nonzero grade
    var c = 0;                      // count the current component number
    var num_users = 0;              // count the number of users
    var has_graded = [];            // keeps track of whether or not each user already has a nonzero grade
    var elems;                      // the elements of the current component
    var elem_type;                  // the type of element that has the scores
    var data_attr;                  // the data attribute in which the score is stored
    // end variable declarations

    // start initial setup: use action to assign values to elem_type and data_attr
    if(action == "lab") {
        elem_type = "td";
        data_attr = "data-score";
    }
    else if(action == "numeric") {
        elem_type = "input";
        data_attr = "value";
    }
    else {
        console.log("Invalid grading type:");
        console.log(action);
        return;
    }
    // get all of the elements with the scores for the first component
    elems = $(elem_type + "[id^=cell-][id$=0]");
    // end initial setup

    // start main loop: iterate by component and calculate stats
    while(elems.length > 0) {
        if(action == "lab" || elems.data('num') == true) { // do all components for lab and ignore text components for numeric
            var sum = 0;                            // sum of the scores
            var sum_sqrs = 0;                       // sum of the squares of the scores
            var user_num = 0;                       // the index for has_graded so that it can be tracked whether or not there is a grade
            var section;                            // the section of the current user (registration or rotating)
            var reg_section;                        // the registration section of the current user
            elems.each(function() {
                if(action == "lab") {
                    reg_section = $(this).parent().find("td:nth-child(2)").text();              // second child of parent has registration section as text
                    section = $(this).parent().parent().attr("id").split("-")[1];               // grandparent id has section
                }
                else if(action == "numeric") {
                    reg_section = $(this).parent().parent().find("td:nth-child(2)").text();     // second child of grandparent has registration section as text
                    section = $(this).parent().parent().parent().attr("id").split("-")[1];      // great-grandparent id has section
                }

                if(reg_section != "") {                 // if section is not null
                    if(!(section in section_counts)) {
                        section_counts[section] = 0;
                        section_sums[section] = 0;
                        section_sums_sqrs[section] = 0;
                    }
                    if(c == 0) {                    // on the first iteration of the while loop...
                        num_users++;                // ...sum up the number of users...
                        section_counts[section]++;  // ...sum up the number of users per section...
                        has_graded.push(false);     // ...and populate the has_graded array with false

                        // for the first component, calculate total stats by section.
                        var score_elems;            // the score elements for this user
                        var score = 0;              // the total score of this user
                        if(action == "lab") {
                            score_elems = $(this).parent().find("td.cell-grade");
                        }
                        else if(action == "numeric") {
                            score_elems = $(this).parent().parent().find("input[data-num=true]");
                        }

                        score_elems.each(function() {
                            score += parseFloat($(this).attr(data_attr));
                        });

                        // add to the sums and sums_sqrs
                        section_sums[section] += score;
                        section_sums_sqrs[section] += score**2;
                    }
                    var score = parseFloat($(this).attr(data_attr));
                    if(!has_graded[user_num]) {     // if they had no nonzero score previously...
                        has_graded[user_num] = score != 0;
                        if(has_graded[user_num]) {  // ...but they have one now
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
        elems = $(elem_type + "[id^=cell-][id$=" + (++c).toString() + "]");
    }
    // end main loop

    // start finalizing: find total stats place all stats into their proper elements
    var stats_popup = $("#simple-stats-popup"); // the popup with all the stats in it.
    for(c = 0; c < component_averages.length; c++) {
        average += component_averages[c];                                                               // sum up component averages to get the total average
        stddev += component_stddevs[c]**2;                                                              // sum up squares of component stddevs (sqrt after all summed) to get the total stddev
        stats_popup.find("#avg-component-" + c.toString()).text(component_averages[c].toFixed(2));      // set the display text of the proper average element
        stats_popup.find("#stddev-component-" + c.toString()).text(component_stddevs[c].toFixed(2));    // set the display text of the proper stddev element
    }

    stddev = Math.sqrt(stddev);                                 // take sqrt of sum of squared stddevs to get total stddev
    stats_popup.find("#avg-total").text(average.toFixed(2));    // set the display text of the proper average element
    stats_popup.find("#stddev-total").text(stddev.toFixed(2));  // set the display text of the proper stddev element


    var section_average;
    var section_stddev;
    for(var section in section_counts) {
        section_average = section_sums[section] / section_counts[section];
        section_stddev = Math.sqrt(Math.max(0, (section_sums_sqrs[section] - section_sums[section]**2 / section_counts[section]) / section_counts[section]));
        stats_popup.find("#avg-section-" + section).text(section_average.toFixed(2));         // set the display text of the proper average element
        stats_popup.find("#stddev-section-" + section).text(section_stddev.toFixed(2));       // set the display text of the proper stddev element
    }
    var num_graded_elem = stats_popup.find("#num-graded");
    $(num_graded_elem).text(num_graded.toString() + "/" + num_users.toString());
    // end finalizing
}

function showSimpleGraderStats(action) {
    if($("#simple-stats-popup").css("display") == "none") {
        calcSimpleGraderStats(action);
        $('.popup').css('display', 'none');
        $("#simple-stats-popup").css("display", "block");
        captureTabInModal("simple-stats-popup");
        $(document).on("click", function(e) {                                           // event handler: when clicking on the document...
            if($(e.target).attr("id") != "simple-stats-btn"                             // ...if neither the stats button..
               && $(e.target).closest('div').attr('id') != "simple-stats-popup") {      // ...nor the stats popup are being clicked...
                $("#simple-stats-popup").css("display", "none");                        // ...hide the stats popup...
                $(document).off("click");                                               // ...and remove this event handler
            }
        });
    }
    else {
        $("#simple-stats-popup").css("display", "none");
        $(document).off("click");
    }
}

function updateCheckpointCells(elems, scores, no_cookie) {
    // see if we're setting all of a row to one score
    var singleScore = null;
    if (scores && typeof scores != 'object') {
        singleScore = scores;
    }

    // keep track of changes
    var new_scores = {};
    var old_scores = {};

    elems = $(elems);
    elems.each(function(idx, el) {
        var elem = $(el);
        var set_new = false;

        old_scores[elem.data('id')] = elem.data('score');

        // if one score passed, set all elems to it
        if (singleScore) {
            elem.data("score", singleScore);
            set_new = true;
        }
        // otherwise match up object IDs with a scores object
        else if (scores && elem.data("id") in scores) {
            elem.data("score", scores[elem.data("id")]);
            set_new = true;
        }
        // if no score set, toggle through options
        else if (!scores) {
            if (elem.data("score") === 1.0) elem.data("score", 0.5);
            else if (elem.data("score") === 0.5) elem.data("score", 0);
            else elem.data("score", 1);
            set_new = true;
        }

        if (set_new) {
            new_scores[elem.data("id")] = elem.data("score");

            // update css to reflect score
            if (elem.data("score") === 1.0) elem.css("background-color", "#149bdf");
            else if (elem.data("score") === 0.5) elem.css("background-color", "#88d0f4");
            else elem.css("background-color", "");

            // create border we can animate to reflect ajax status
            elem.css("border-right", "60px solid #ddd");
        }
    });

    var parent = $(elems[0]).parent();
    var user_id = parent.data("user");
    var g_id = parent.data('gradeable');

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
          'scores': new_scores
        },
        function(returned_data) {
            // Validate that the Simple Grader backend correctly saved components before updating
            var expected_vals = JSON.stringify(Object.entries(new_scores).map(String));
            var returned_vals = JSON.stringify(Object.entries(returned_data['data']).map(String));
            if (expected_vals === returned_vals) {
                elems.each(function(idx, elem) {
                    elem = $(elem);
                    elem.animate({"border-right-width": "0px"}, 400); // animate the box
                    elem.attr("data-score", elem.data("score"));      // update the score
                });
                window.socketClient.send({'type': "update_checkpoint", 'elem': elems.attr("id"), 'score':elems.data('score')});
            } else {
                console.log("Save error: returned data:", returned_vals, "does not match expected new data:", expected_vals);
                elems.each(function(idx, elem) {
                    elem = $(elem);
                    elem.stop(true, true);
                    elem.css("border-right", "60px solid #DA4F49");
                });
            }
        },
        function() {
            elems.each(function(idx, elem) {
                elem = $(elem);
                elem.stop(true, true);
                elem.css("border-right", "60px solid #DA4F49");
            });
        }
    );
}

function getCheckpointHistory(g_id) {
    var name = g_id + "_history=";
    var cookies = decodeURIComponent(document.cookie).split(';');
    for(var i = 0; i < cookies.length; i++) {
        var c = cookies[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return JSON.parse(c.substring(name.length, c.length));
        }
    }
    // if history is empty set pointer to 0
    return [0];
}

function setCheckpointHistory(g_id, history) {
    var expiration_date = new Date(Date.now());
    expiration_date.setDate(expiration_date.getDate() + 1);
    document.cookie = g_id + "_history=" + JSON.stringify(history) + "; expires=" + expiration_date.toUTCString();
}

function generateCheckpointCookie(user_id, g_id, old_scores, new_scores) {
    // format: [pointer, [studentID1, old_scores], [studentID1, new_scores], [studentID2, old_scores ... where studentid1 is the oldest edited
    // pointer should be the index of the current state in history. new_scores should be true for all leading up to that state
    // the pointer is bound by 1, history.length-1
    var history = getCheckpointHistory(g_id);

    // erase future snapshots on write
    if (history[0] < history.length-1) {
        history = history.slice(0, history[0]);
    }

    // write new student entry
    history.push([user_id, old_scores]);
    history.push([user_id, new_scores]);

    // update undo/redo buttons
    if (history.length > 1) {
        $("#checkpoint-undo").prop("disabled", false);
    }
    $("#checkpoint-redo").prop("disabled", true);

    // keep max history of 5 entries (1 buffer for pointer, 5x2 for old/new)
    if (history.length > 11) {
        history.splice(1, 2);
    }
    else {
        history[0] = history.length-1; // increment to latest new_scores
    }

    setCheckpointHistory(g_id, history);
}

// helper function for undo/redo which rolls the history to a specific point
function checkpointRollTo(g_id, diff) {
    // grab history from cookie
    var history = getCheckpointHistory(g_id);

    var update_queue = [];
    var direction = Math.sign(diff);
    var pointer = history[0];

    // clamp to bounds
    if (pointer + diff < 1) diff = 1 - pointer;
    if (pointer + diff >= history.length) diff = history.length - 1 - pointer;

    // if redoing and pointer is on an old_score, move pointer to next new_score
    if (direction>0 && pointer%2) {
        pointer += 1;
        diff -= direction;
        update_queue.push(history[pointer]);
    }
    // if undoing and pointer is on an new_score, move pointer to next old_score
    else if (direction<0 && !(pointer%2)) {
        pointer -= 1;
        diff -= direction;
        update_queue.push(history[pointer]);
    }

    // incrementally move snapshot and set states to update, incrementing by old_scores if direction < 0, new_scores if dir > 0
    while (diff != 0) {
        pointer += (2*direction);
        update_queue.push(history[pointer]);
        diff -= direction;
    }

    // update buttons
    $("#checkpoint-undo").prop("disabled", false);
    $("#checkpoint-redo").prop("disabled", false);
    if (pointer <= 1) {
        $("#checkpoint-undo").prop("disabled", true);
    }
    if (pointer >= history.length-1) {
        $("#checkpoint-redo").prop("disabled", true);
    }

    //write new cookie
    history[0] = pointer;
    setCheckpointHistory(g_id, history);

    // update cells for each snapshot
    update_queue.forEach(function(snaphot) {
        // get elems from studentID
        var elems = $("tr[data-user='" + snaphot[0] + "'] .cell-grade");
        updateCheckpointCells(elems, snaphot[1], true);
    });
}

function setupCheckboxCells() {
    // jQuery for the elements with the class cell-grade (those in the component columns)
    $("td.cell-grade").click(function() {
        updateCheckpointCells(this);
    });

    // show all the hidden grades when this checkbox is clicked
    $("#show-graders").on("change", function() {
        if($(this).is(":checked")) {
            $(".simple-grade-grader").css("display", "block");
        }
        else {
            $(".simple-grade-grader").css("display", "none");
        }
    });

    // show all the hidden dates when that checkbox is clicked
    $("#show-dates").on("change", function() {
        if($(this).is(":checked")) {
            $(".simple-grade-date").css("display", "block");
        }
        else {
            $(".simple-grade-date").css("display", "none");
        }
    });

    // initialize undo/redo
    var g_id = $("tr#row-0").data('gradeable');
    var history = getCheckpointHistory(g_id);

    if (history.length > 1) {
        $("#checkpoint-undo").prop("disabled", false);
        if (history[0] < history.length - 1) {
            $("#checkpoint-redo").prop("disabled", false);
        }
    }
}

function setupNumericTextCells() {
    $(".cell-grade").change(function() {
        elem = $(this);
        if (this.value == "") {
            return;
        }
        if(this.value == 0) {
            elem.css("color", "#bbbbbb");
        }
        else{
            elem.css("color", "");
        }

        var row_num = elem.attr("id").split("-")[1];
        var row_el = $("tr#row-" + row_num);

        var scores = {};
        var old_scores = {};
        var total = 0;

        row_el.find(".cell-grade").each(function() {
            elem = $(this);
            if (elem.data("num")) {
                total += parseFloat(elem.val());
            }

            // ensure value is string (might not be on initial load from twig)
            old_scores[elem.data("id")] = elem.data("origval") + "";
            scores[elem.data("id")] = elem.val();

            // save old value so we can verify data is not stale
            elem.data('origval', elem.val());
            elem.attr('data-origval', elem.val());
        });

      let id = this.id;
      let value = this.value;

      submitAJAX(
            buildCourseUrl(['gradeable', row_el.data('gradeable'), 'grading']),
            {
                'csrf_token': csrfToken,
                'user_id': row_el.data("user"),
                'old_scores': old_scores,
                'scores': scores
            },
            function() {
              // Finds the element that stores the total and updates it to reflect increase
              if (row_el.find(".cell-total").text() != total)
                row_el.find(".cell-total").text(total).hide().fadeIn("slow");
              window.socketClient.send({'type': "update_numeric", 'elem': id, 'value': value, 'total': total});
            },
            function() {
                elem.css("background-color", "#ff7777");
            }
        );
    });

    $("input[class=csvButtonUpload]").change(function() {
        var confirmation = window.confirm("WARNING! \nPreviously entered data may be overwritten! " +
        "This action is irreversible! Are you sure you want to continue?\n\n Do not include a header row in your CSV. Format CSV using one column for " +
        "student id and one column for each field. Columns and field types must match.");
        if (confirmation) {
            var f = $('#csvUpload').get(0).files[0];
            if(f) {
                var reader = new FileReader();
                reader.readAsText(f);
                reader.onload = function(evt) {
                    var breakOut = false; //breakOut is used to break out of the function and alert the user the format is wrong
                    var lines = (reader.result).trim().split(/\r\n|\n/);
                    var tempArray = lines[0].split(',');
                    var csvLength = tempArray.length; //gets the length of the array, all the tempArray should be the same length
                    for (var k = 0; k < lines.length && !breakOut; k++) {
                        tempArray = lines[k].split(',');
                        breakOut = (tempArray.length === csvLength) ? false : true; //if tempArray is not the same length, break out
                    }
                    var textChecker = 0;
                    var num_numeric = 0;
                    var num_text = 0;
                    var user_ids = [];
                    var component_ids = [];
                    var get_once = true;
                    var gradeable_id = "";
                    if (!breakOut){
                        $('.cell-all').each(function() {
                            user_ids.push($(this).parent().data("user"));
                            if(get_once) {
                                num_numeric = $(this).parent().parent().data("numnumeric");
                                num_text = $(this).parent().parent().data("numtext");
                                component_ids = $(this).parent().parent().data("compids");
                                gradeable_id = $(this).parent().data("gradeable");
                                get_once = false;
                                if (csvLength !== 4 + num_numeric + num_text) {
                                    breakOut = true;
                                    return false;
                                }
                                var k = 3; //checks if the file has the right number of numerics
                                tempArray = lines[0].split(',');
                                if(num_numeric > 0) {
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
                    if (!breakOut){
                        submitAJAX(
                            buildCourseUrl(['gradeable', gradeable_id, 'grading', 'csv']),
                            {'csrf_token': csrfToken, 'users': user_ids,
                            'num_numeric' : num_numeric, 'big_file': reader.result},
                            function(returned_data) {
                                $('.cell-all').each(function() {
                                    for (var x = 0; x < returned_data['data'].length; x++) {
                                        if ($(this).parent().data("user") === returned_data['data'][x]['username']) {
                                            var starting_index1 = 0;
                                            var starting_index2 = 3;
                                            var value_str = "value_";
                                            var status_str = "status_";
                                            var value_temp_str = "value_";
                                            var status_temp_str = "status_";
                                            var total = 0;
                                            var y = starting_index1;
                                            var z = starting_index2; //3 is the starting index of the grades in the csv
                                            //puts all the data in the form
                                            for (z = starting_index2; z < num_numeric + starting_index2; z++, y++) {
                                                value_temp_str = value_str + y;
                                                status_temp_str = status_str + y;
                                                $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val(returned_data['data'][x][value_temp_str]);
                                                if (returned_data['data'][x][status_temp_str] === "OK") {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("background-color", "#ffffff");
                                                }
                                                else {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("background-color", "#ff7777");
                                                }

                                                if($('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val() == 0) {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("color", "#bbbbbb");
                                                }
                                                else {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("color", "");
                                                }

                                                total += Number($('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val());
                                            }
                                            $('#total-'+$(this).parent().data("row")).val(total);
                                            z++;
                                            var counter = 0;
                                            while (counter < num_text) {
                                                value_temp_str = value_str + y;
                                                status_temp_str = status_str + y;
                                                $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2-1)).val(returned_data['data'][x][value_temp_str]);
                                                z++;
                                                y++;
                                                counter++;
                                            }

                                            x = returned_data['data'].length;
                                        }
                                    }
                                });
                            },
                            function() {
                                alert("submission error");
                            }
                        );
                    }

                    if (breakOut) {
                        alert("CSV upload failed! Format file incorrect.");
                    }
                }
            }
        }
        else {
            var f = $('#csvUpload');
            f.replaceWith(f = f.clone(true));
        }
    });
}

function setupSimpleGrading(action) {
    if(action === "lab") {
        setupCheckboxCells();
    }
    else if(action === "numeric") {
        setupNumericTextCells();
    }
    // search bar code starts here (see site/app/templates/grading/StudentSearch.twig for #student-search)

    // highlights the first jquery-ui autocomplete result if there is only one
    function highlightOnSingleMatch(is_remove) {
        var matches = $("#student-search > ul > li");
        // if there is only one match, use jquery-ui css to highlight it so the user knows it is selected
        if(matches.length == 1) {
            $(matches[0]).children("div").addClass("ui-state-active");
        }
        else if(is_remove) {
            $(matches[0]).children("div").removeClass("ui-state-active");
        }
    }

    var dont_hotkey_focus = true; // set to allow toggling of focus on input element so we can use enter as hotkey

    // prevent hotkey focus while already focused, so we dont override search functionality
    $("#student-search-input").focus(function(event) {
        dont_hotkey_focus = true;
    });

    // refocus on the input field by pressing enter
    $(document).on("keyup", function(event) {
        if(event.keyCode == 13 && !dont_hotkey_focus) {
            $("#student-search-input").focus();
        }
    });

    // moves the selection to an adjacent cell
    function movement(direction){
        var prev_cell = $(".cell-grade:focus");
        if(prev_cell.length) {
            // ids have the format cell-ROW#-COL#
            var new_selector_array = prev_cell.attr("id").split("-");
            new_selector_array[1] = parseInt(new_selector_array[1]);
            new_selector_array[2] = parseInt(new_selector_array[2]);

            // update row and col to get new val
            if (direction == "up") new_selector_array[1] -= 1;
            else if (direction == "down") new_selector_array[1] += 1;
            else if (direction == "left") new_selector_array[2] -= 1;
            else if (direction == "right") new_selector_array[2] += 1;

            // get new cell
            var new_cell = $("#" + new_selector_array.join("-"));
            if (new_cell.length) {
                prev_cell.blur();
                new_cell.focus();
                new_cell.select(); // used to select text in input cells

                if((direction == "up" || direction == "down") && !new_cell.isInViewport()) {
                    $('html, body').animate( { scrollTop: new_cell.offset().top - $(window).height()/2}, 50);
                }
            }
        }
    }

    // default key movement
    $(document).on("keydown", function(event) {
        // if input cell selected, use this to check if cursor is in the right place
        var input_cell = $("input.cell-grade:focus");

        // if there is no selection OR there is a selection to the far left with 0 length
        if(event.keyCode == 37 && (!input_cell.length || (
                input_cell[0].selectionStart == 0 &&
                input_cell[0].selectionEnd - input_cell[0].selectionStart == 0))) {
            event.preventDefault();
            movement("left");
        }
        else if(event.keyCode == 38) {
            event.preventDefault();
            movement("up");
        }
        // if there is no selection OR there is a selection to the far right with 0 length
        else if(event.keyCode == 39 && (!input_cell.length || (
                input_cell[0].selectionEnd == input_cell[0].value.length &&
                input_cell[0].selectionEnd - input_cell[0].selectionStart == 0))) {
            event.preventDefault();
            movement("right");
        }
        else if(event.keyCode == 40) {
            event.preventDefault();
            movement("down");
        }
    });

    // register empty function locked event handlers for "enter" so they show up in the hotkeys menu
    registerKeyHandler({name: "Search", code: "Enter", locked: true}, function() {});
    // make arrow keys in lab section changeable now
    if(action == 'lab') {
        registerKeyHandler({name: "Move Right", code: "ArrowRight", locked: false}, function(event) {
            event.preventDefault();
            movement("right");
        });
        registerKeyHandler({name: "Move Left", code: "ArrowLeft", locked: false}, function(event) {
            event.preventDefault();
            movement("left");
        });
        registerKeyHandler({name: "Move Up", code: "ArrowUp", locked: false}, function(event) {
            event.preventDefault();
            movement("up");
        });
        registerKeyHandler({name: "Move Down", code: "ArrowDown", locked: false}, function(event) {
            event.preventDefault();
            movement("down");
        });
    }
    //the arrow keys in test section remain unchangeable as setting up other keys will disturb the input
    else{
        registerKeyHandler({name: "Move Right", code: "ArrowRight", locked: true}, function() {});
        registerKeyHandler({name: "Move Left", code: "ArrowLeft", locked: true}, function() {});
        registerKeyHandler({name: "Move Up", code: "ArrowUp", locked: true}, function() {});
        registerKeyHandler({name: "Move Down", code: "ArrowDown", locked: true}, function(event) {});
    }

    // check if a cell is focused, then update value
    function keySetCurrentCell(event, options) {
        var cell = $(".cell-grade:focus");
        if (cell.length) {
            updateCheckpointCells(cell, options.score);
        }
    }

    // check if a cell is focused, then update the entire row
    function keySetCurrentRow(event, options) {
        var cell = $(".cell-grade:focus");
        if (cell.length) {
            updateCheckpointCells(cell.parent().find(".cell-grade"), options.score);
        }
    }

    // register keybinds for grading controls
    if(action == 'lab') {
        registerKeyHandler({ name: "Set Cell to 0", code: "KeyZ", options: {score: 0} }, keySetCurrentCell);
        registerKeyHandler({ name: "Set Cell to 0.5", code: "KeyX", options: {score: 0.5} }, keySetCurrentCell);
        registerKeyHandler({ name: "Set Cell to 1", code: "KeyC", options: {score: 1} }, keySetCurrentCell);
        registerKeyHandler({ name: "Cycle Cell Value", code: "KeyV", options: {score: null} }, keySetCurrentCell);
        registerKeyHandler({ name: "Set Row to 0", code: "KeyA", options: {score: 0} }, keySetCurrentRow);
        registerKeyHandler({ name: "Set Row to 0.5", code: "KeyS", options: {score: 0.5} }, keySetCurrentRow);
        registerKeyHandler({ name: "Set Row to 1", code: "KeyD", options: {score: 1} }, keySetCurrentRow);
        registerKeyHandler({ name: "Cycle Row Value", code: "KeyF", options: {score: null} }, keySetCurrentRow);
    }

    // when pressing enter in the search bar, go to the corresponding element
    $("#student-search-input").on("keyup", function(event) {
        if(event.keyCode == 13) { // Enter
            this.blur();
            var value = $(this).val();
            if(value != "") {
                var prev_cell = $(".cell-grade:focus");
                // get the row number of the table element with the matching id
                var tr_elem = $('table tbody tr[data-user="' + value +'"]');
                // if a match is found, then use it to find the cell
                if(tr_elem.length > 0) {
                    var new_cell = $("#cell-" + tr_elem.attr("data-row") + "-0");
                    prev_cell.blur();
                    new_cell.focus();
                    $('html, body').animate( { scrollTop: new_cell.offset().top - $(window).height()/2}, 50);
                }
                else {
                    // if no match is found and there is at least 1 matching autocomplete label, find its matching value
                    var first_match = $("#student-search > ul > li");
                    if(first_match.length == 1) {
                        var first_match_label = first_match.text();
                        var first_match_value = "";
                        for(var i = 0; i < student_full.length; i++) {      // NOTE: student_full comes from StudentSearch.twig script
                            if(student_full[i]["label"] == first_match_label) {
                                first_match_value = student_full[i]["value"];
                                break;
                            }
                        }
                        this.focus();
                        $(this).val(first_match_value); // reset the value...
                        $(this).trigger(event);    // ...and retrigger the event
                    }
                    else {
                        alert("ERROR:\n\nInvalid user.");
                        this.focus();                       // refocus on the input field
                    }
                }
            }
        }
    });

    $("#student-search-input").on("keydown", function() {
        highlightOnSingleMatch(false);
    });
    $("#student-search").on("DOMSubtreeModified", function() {
        highlightOnSingleMatch(true);
    });

    // clear the input field when it is focused
    $("#student-search-input").on("focus", function(event) {
        $(this).val("");
    });

    // the offset of the search bar: used to lock the search bar on scroll
    var sticky_offset = $("#checkpoint-sticky").offset();

    // used to reposition the search field when the window scrolls
    $(window).on("scroll", function(event) {
        var sticky = $("#checkpoint-sticky");
        if(sticky_offset.top < $(window).scrollTop()) {
            sticky.addClass("sticky-top");
        }
        else {
            sticky.removeClass("sticky-top");
        }
    });

    // check if the search field needs to be repositioned when the page is loaded
    if(sticky_offset.top < $(window).scrollTop()) {
        var sticky = $("#checkpoint-sticky");
        sticky.addClass("sticky-top");
    }

    // check if the search field needs to be repositioned when the page is resized
    $(window).on("resize", function(event) {
        var settings_btn_offset = $("#settings-btn").offset();
        sticky_offset = {
            top : settings_btn_offset.top,
        };
        if(sticky_offset.top < $(window).scrollTop()) {
            var sticky = $("#checkpoint-sticky");
            sticky.addClass("sticky-top");
        }
    });
    // search bar code ends here
  initSocketClient();
}

function initSocketClient() {
  window.socketClient = new WebSocketClient();
  window.socketClient.onmessage = (msg) => {
    let elem = $('#' + msg.elem);
    switch (msg.type) {
      case "update_checkpoint":
        elem.data('score', msg.score);
        elem.attr("data-score", msg.score);
        switch (msg.score) {
          case 1.0:
            elem.css("background-color", "#149bdf");
            break;
          case 0.5:
            elem.css("background-color", "#88d0f4")
            break;
          default:
            elem.css("background-color", "")
        }
        elem.css("border-right", "60px solid #ddd");
        elem.animate({"border-right-width": "0px"}, 400);
        break;
      case "update_numeric":
        elem.data('origval', msg.value);
        elem.attr('data-origval', msg.value);
        elem.val(msg.value);
        elem.css("background-color", "white");
        if(msg.value == 0) {
          elem.css("color", "#bbbbbb");
        }
        else{
          elem.css("color", "");
        }
        if (elem.parent().siblings('.option-small-output').children('.cell-total').text() != msg.total)
          elem.parent().siblings('.option-small-output').children('.cell-total').text(msg.total).hide().fadeIn("slow");
        break;
      default:
        console.log('Undefined message received');
    }
  };
  let gradeable_id = window.location.pathname.split("gradeable/")[1].split('/')[0];
  window.socketClient.open(gradeable_id);
}
