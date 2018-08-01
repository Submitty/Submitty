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

function updateCheckpointCell(elem, setFull) {
    elem = $(elem);
    if (!setFull && elem.data("score") === 1.0) {
        elem.data("score", 0.5);
        elem.css("background-color", "#88d0f4");
        elem.css("border-right", "15px solid #f9f9f9");
    }
    else if (!setFull && elem.data("score") === 0.5) {
        elem.data("score", 0);
        elem.css("background-color", "");
        elem.css("border-right", "15px solid #ddd");
    }
    else {
        elem.data("score", 1);
        elem.css("background-color", "#149bdf");
        elem.css("border-right", "15px solid #f9f9f9");
    }
}

function setupCheckboxCells() {
    // jQuery for the elements with the class cell-grade (those in the component columns)
    $("td.cell-grade").click(function() {
        var parent = $(this).parent();
        var elems = [];
        var scores = {};
        updateCheckpointCell(this);
        elems.push(this);
        scores[$(this).data('id')] = $(this).data('score');

        // Update the buttons to reflect that they were clicked
        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_lab'}),
            {
              'csrf_token': csrfToken,
              'user_id': parent.data("user"),
              'g_id': parent.data('gradeable'),
              'scores': scores
            },
            function() {
                elems.forEach(function(elem) {
                    elem = $(elem);
                    elem.animate({"border-right-width": "0px"}, 400);                                   // animate the box
                    elem.attr("data-score", elem.data("score"));                                        // update the score
                });
            },
            function() {
                elems.forEach(function(elem) {
                    console.log(elem);
                    $(elem).css("border-right-width", "15px");
                    $(elem).stop(true, true).animate({"border-right-color": "#DA4F49"}, 400);
                });
            }
        );
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

}

function setupNumericTextCells() {
    $("input[class=option-small-box]").change(function() {
        elem = this;
        if(this.value == 0){
            $(this).css("color", "#bbbbbb");
        }
        else{
            $(this).css("color", "");
        }
        var scores = {};
        var total = 0;
        $(this).parent().parent().children("td.option-small-input, td.option-small-output").each(function() {
            $(this).children(".option-small-box").each(function(){
                if($(this).data('num') === true){
                    total += parseFloat(this.value);
                }
                if($(this).data('total') === true){
                    this.value = total;
                }
                else{
                    scores[$(this).data("id")] = this.value;
                }
            });
        });

        // find number of users (num of input elements whose id starts with "cell-" and ends with 0)
        var num_users = 0;
        $("input[id^=cell-][id$=0]").each(function() {
            // increment only if great-grandparent id ends with a digit (indicates section is not NULL)
            if($(this).parent().parent().parent().attr("id").match(/\d+$/)) {
                num_users++;
            }
        });
        // find stats popup to access later
        var stats_popup = $("#simple-stats-popup");
        var num_graded_elem = stats_popup.find("#num-graded");

        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_numeric'}),
            {
                'csrf_token': csrfToken,
                'user_id': $(this).parent().parent().data("user"),
                'g_id': $(this).parent().parent().data('gradeable'),
                'scores': scores
            },
            function() {
                $(elem).css("background-color", "#ffffff");                                     // change the color
                $(elem).attr("value", elem.value);                                              // Stores the new input value
                $(elem).parent().parent().children("td.option-small-output").each(function() {  
                    $(this).children(".option-small-box").each(function() {
                        $(this).attr("value", this.value);                                      // Finds the element that stores the total and updates it to reflect increase
                    });
                });
            },
            function() {
                $(elem).css("background-color", "#ff7777");
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
                            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'upload_csv_numeric'}),
                            {'csrf_token': csrfToken, 'g_id': gradeable_id, 'users': user_ids, 'component_ids' : component_ids,
                            'num_numeric' : num_numeric, 'num_text' : num_text, 'big_file': reader.result},
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
                                                } else {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("background-color", "#ff7777");
                                                }

                                                if($('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val() == 0) {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("color", "#bbbbbb");
                                                } else {
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
                        alert("CVS upload failed! Format file incorrect.");
                    }
                }
            }
        } else {
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

    // updates the checkbox scores of elems:
    // if is_all, updates all, else, updates only the elem at idx
    // if !is_all and is the cell-all element, updates all the elements
    function updateCheckboxScores(num, elems, is_all, idx=0) {
        if(is_all) {                                // if updating all, update all non .cell-all cells individually
            elems.each(function() {
                updateCheckboxScores(num, elems, false, idx);
                idx++;
            });
        }
        else {                              // if updating one, click until the score matches 
            elem = $(elems[idx]);
            for(var i = 0; i < 2; i++) {
                if(elem.data("score") == num) {
                    break;
                }
                else {
                    elem.click();
                }
            }
        } 
    }

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

    var dont_focus = true;                                          // set to allow toggling of focus on input element
    var num_rows = $("td.cell-all").length;                         // the number of rows in the table
    var search_bar_offset = $("#student-search").offset();          // the offset of the search bar: used to lock the searhc bar on scroll
    var highlight_color = "#337ab7";                                // the color used in the border around the selected element in the table
    var search_selector = action == 'lab'       ?                   // the selector being used varies depending on the action (lab/numeric are different)
                         'td.cell-grade'     :
                         'td.option-small-input';
    var table_row = 0;                                              // the current row
    var child_idx = 0;                                              // the index of the current element in the row
    var child_elems = $("tr[data-row=0]").find(search_selector);    // the clickable elements in the current row

    // outline the first element in the first row if able
    if(child_elems.length) {
        var child = $(child_elems[0]);
        if(action == 'numeric') {
            child = child.children("input");
        }
        child.css("outline", "3px dashed " + highlight_color);
    }

    // movement keybinds
    $(document).on("keydown", function(event) {
        if(!$("#student-search-input").is(":focus")) {
            // allow refocusing on the input field by pressing enter when it is not the focus
            if(event.keyCode == 13) {
                dont_focus = false;
            }
            // movement commands
            else if([37,38,39,40,9].includes(event.keyCode)) { // Arrow keys/tab unselect, bounds check, then move and reselect
                var child = $(child_elems[child_idx]);
                if(action == 'lab') {
                    child.css("outline", "");
                }
                else {
                    child.children("input").css("outline", "");
                }
                if(event.keyCode == 37 || (event.keyCode == 9 && event.shiftKey)) { // Left arrow/shift+tab
                    if(event.keyCode == 9 && event.shiftKey) {
                        event.preventDefault();
                    }
                    if(child_idx > 0 && (action == 'lab' || (event.keyCode == 9 && event.shiftKey) || child.children("input")[0].selectionStart == 0)) {
                        child_idx--;
                    }
                }
                else if(event.keyCode == 39 || event.keyCode == 9) {                // Right arrow/tab
                    if(event.keyCode == 9) {
                        event.preventDefault();
                    }
                    if(child_idx < child_elems.length - 1 && (action == 'lab' || event.keyCode == 9 || child.children("input")[0].selectionEnd == child.children("input")[0].value.length)) {
                        child_idx++;
                    }
                }
                else {
                    event.preventDefault();
                    if(event.keyCode == 38) {               // Up arrow
                        if(table_row > 0) {
                            table_row--;
                        }
                    }
                    else if(table_row < num_rows - 1) {     // Down arrow
                        table_row++;
                    }
                    child_elems = $("tr[data-row=" + table_row + "]").find(search_selector);
                }
                child = $(child_elems[child_idx]);
                if(action == 'lab') {
                    child.css("outline", "3px dashed " + highlight_color);
                }
                else {
                    child.children("input").css("outline", "3px dashed " + highlight_color).focus();
                }

                if((event.keyCode == 38 || event.keyCode == 40) && !child.isInViewport()) {
                    $('html, body').animate( { scrollTop: child.offset().top - $(window).height()/2}, 50);
                }
            }
        }
    });

    // refocus on the input field by pressing enter
    $(document).on("keyup", function(event) {
        if(event.keyCode == 13 && !dont_focus) {
            $("#student-search-input").focus();
        }
    });
    
    // register empty function locked event handlers for movement keybinds so they show up in the hotkeys menu
    registerKeyHandler({name: "Search", code: "Enter", locked: true}, function() {});
    registerKeyHandler({name: "Move Right", code: "ArrowRight", locked: true}, function() {});
    registerKeyHandler({name: "Move Left", code: "ArrowLeft", locked: true}, function() {});
    registerKeyHandler({name: "Move Up", code: "ArrowUp", locked: true}, function() {});
    registerKeyHandler({name: "Move Down", code: "ArrowDown", locked: true}, function() {});

    // register keybinds for grading controls
    if(action == 'lab') {
        registerKeyHandler({name: "Set Cell to 0", code: "KeyZ"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0, child_elems, false, child_idx);
            }
        });
        registerKeyHandler({name: "Set Cell to 0.5", code: "KeyX"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0.5, child_elems, false, child_idx);
            }
        });
        registerKeyHandler({name: "Set Cell to 1", code: "KeyC"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(1, child_elems, false, child_idx);
            }
        });
        registerKeyHandler({name: "Cycle Cell Value", code: "KeyV"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                $(child_elems[child_idx]).click();
            }
        });
        registerKeyHandler({name: "Set Row to 0", code: "KeyA"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0, child_elems, true);
            }
        });
        registerKeyHandler({name: "Set Row to 0.5", code: "KeyS"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0.5, child_elems, true);
            }
        });
        registerKeyHandler({name: "Set Row to 1", code: "KeyD"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(1, child_elems, true);
            }
        });
        registerKeyHandler({name: "Cycle Row Value", code: "KeyF"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                $(child_elems).each(function() {
                    $(this).click();
                });
            }
        });
    }
    // for numeric gradeables, whenever an input field is focused, update location variables
    else {
        $("input[id^=cell-]").on("focus", function(event) {
            $(child_elems[child_idx]).children("input").css("outline", "");
            var tr_elem = $(this).parent().parent();
            table_row = tr_elem.attr("data-row");
            child_elems = tr_elem.find(search_selector);
            child_idx = child_elems.index($(this).parent());
            $(child_elems[child_idx]).children("input").css("outline", "3px dashed " + highlight_color);
        });
    }

    // when pressing enter in the search bar, go to the corresponding element
    $("#student-search-input").on("keyup", function(event) {
        if(event.keyCode == 13) { // Enter
            this.blur();
            dont_focus = true; // dont allow refocusing until later
            var value = $(this).val();
            if(value != "") {
                var prev_child_elem = $(child_elems[child_idx]);
                // get the row number of the table element with the matching id
                var tr_elem = $('table tbody tr[data-user="' + value +'"]');
                // if a match is found, then use it to find the cell
                if(tr_elem.length > 0) {
                    table_row = tr_elem.attr("data-row");
                    child_elems = $("tr[data-row=" + table_row + "]").find(search_selector);
                    if(action == 'lab') {
                        prev_child_elem.css("outline", "");
                        $(child_elems[child_idx]).css("outline", "3px dashed " + highlight_color);
                    }
                    else {
                        prev_child_elem.children("input").css("outline", "");
                        $(child_elems[child_idx]).children("input").css("outline", "3px dashed " + highlight_color).focus();
                    }
                    $('html, body').animate( { scrollTop: $(child_elems).parent().offset().top - $(window).height()/2}, 50);
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

    // used to reposition the search field when the window scrolls
    $(window).on("scroll", function(event) {
        var search_field = $("#student-search");
        if(search_bar_offset.top < $(window).scrollTop()) {
            search_field.css("top", 0);
            search_field.css("left", search_bar_offset.left);
            search_field.css("position", "fixed");
        }
        else {
            search_field.css("position", "relative");
            search_field.css("left", "");
        }
    });

    // check if the search field needs to be repositioned when the page is loaded
    if(search_bar_offset.top < $(window).scrollTop()) {
        var search_field = $("#student-search");
        search_field.css("top", 0);
        search_field.css("left", search_bar_offset.left);
        search_field.css("position", "fixed");
    }

    // check if the search field needs to be repositioned when the page is resized
    $(window).on("resize", function(event) {
        var settings_btn_offset = $("#settings-btn").offset();
        search_bar_offset = {   // NOTE: THE SEARCH BAR IS PLACED RELATIVE TO THE SETTINGS BUTTON
            top : settings_btn_offset.top,
            left : settings_btn_offset.left - $("#student-search").width()
        };
        if(search_bar_offset.top < $(window).scrollTop()) {
            var search_field = $("#student-search");
            search_field.css("top", 0);
            search_field.css("left", search_bar_offset.left);
            search_field.css("position", "fixed");
        }
    });

    // search bar code ends here
}
