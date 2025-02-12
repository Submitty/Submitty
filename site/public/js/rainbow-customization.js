/* exported addToTable, deleteRow manageWarningsGradeables ResetPerGradeablePercents */
/* global buildCourseUrl csrfToken displayErrorMessage displaySuccessMessage */

const benchmarks_with_input_fields = ['lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d'];
const allowed_grades = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
const allowed_grades_excluding_f = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D'];
const tables = ['plagiarism', 'manualGrade', 'performanceWarnings'];

// eslint-disable-next-line no-unused-vars
function ExtractBuckets() {
    const x = [];
    const bucket_list = $('#buckets_used_list').find('li');
    bucket_list.each((idx, li) => {
        x.push($(li).text());
    });

    $('#generate_json').val(JSON.stringify(x));
    $('#custom_form').submit();
}

// Forces the number of expected gradeables to be greater than or equal to the current number of gradeables
function ClampGradeablesInBucket(el, num_gradeables) {
    if (isNaN(el.value) || el.value < num_gradeables) {
        el.value = num_gradeables;
        displayErrorMessage('The expected number of gradeables must be greater than or equal to the current number of gradeables.');
        saveChanges();
    }
}

// Forces element's value to be non-negative
function ClampPoints(el) {
    if (el.value === '') {
        el.value = el.placeholder;
        el.classList.remove('override');
    }
    el.value = Math.max(0.0, el.value);
}

// Forces element's value to be non-negative and between 0.0 - 100.0
// Distinct from ClampPercent(), this is for Per Gradeable Percents
function ClampPercents(el) {
    if (el.value === '') {
        el.value = el.placeholder;
    }
    el.value = Math.min(Math.max(el.value, 0.0), 100.0);
}

function DetectMaxOverride(el) {
    if (el.value !== el.placeholder) {
        el.classList.add('override');
    }
    else {
        el.classList.remove('override');
    }
}

function ExtractBucketName(s, offset) {
    const tmp = s.split('-');
    let bucket = '';
    let i;
    for (i = offset; i < tmp.length; i++) {
        if (i > offset) {
            bucket += '-';
        }
        bucket += tmp[i];
    }
    return bucket;
}

// Forces element's value to be in range [0.0,100.0]
function ClampPercent(el) {
    el.value = Math.min(Math.max(el.value, 0.0), 100.0);
    UpdateUsedPercentage();
    $(`#config-percent-${ExtractBucketName(el.id, 1)}`).text(`${el.value}%`);
}

// Forces sum of Per Gradeable Percents in a bucket to be below 100.0
function ClampPerGradeablePercents(el, bucket) {
    const percentsInputsInBucket = $(`div[id^="gradeable-percents-div-${bucket}"]`);
    let sum = 0.0;

    percentsInputsInBucket.each((index, percentInput) => {
        const textbox = $(percentInput).children().first();
        sum += parseFloat(textbox.val());
    });

    const warningIcon = $(`#per-gradeable-percents-warning-${bucket}`);
    if (sum > 100.0) {
        const excess = sum - 100.0;
        warningIcon.show();
        $(warningIcon.children()[0]).text(`WARNING: Per Gradeable Percents exceeds 100 by ${excess}. Do not be alarmed if this is due to Extra Credit`);
    }
    else {
        warningIcon.hide();
    }
}

// Resets Per Gradeable Percents in a given bucket to an even split
function ResetPerGradeablePercents(bucket) {
    const percentsInputsInBucket = $(`div[id^="gradeable-percents-div-${bucket}"]`);

    percentsInputsInBucket.each((index, percentInput) => {
        const textbox = $(percentInput).children().first();
        textbox.val('').blur(); // If the textbox is empty, it resets to an even split onblur
    });
}

// Updates the sum of percentage points accounted for by the buckets being used
function UpdateUsedPercentage() {
    let val = 0.0;
    $("input[id^='percent-']").filter(function () {
        return $(this).parent().css('display') !== 'none';
    }).each(function () {
        val += parseFloat($(this).val());
    });
    const percentage_span = $('#used_percentage');
    percentage_span.text(`${val.toString()}%`);
    if (val > 100.0) {
        percentage_span.css({ 'color': 'red', 'font-weight': 'bold' });
    }
    else {
        percentage_span.css({ 'color': 'var(--text-black)', 'font-weight': '' });
    }
}

// Updates which buckets have full configuration shown (inc. each gradeable), and the ordering
// eslint-disable-next-line no-unused-vars
function UpdateVisibilityBuckets() {
    // For each bucket that isn't being used, hide it
    $('#buckets_available_list').find('input').each(function () {
        // Extract the bucket name
        const bucket = ExtractBucketName($(this).attr('id'), 1);
        $(`#config-${bucket}`).css('display', 'none');
    });

    // For each bucket that IS being used, show it
    const used_buckets = $('#buckets_used_list').find('input');
    if (used_buckets.length === 0) {
        return;
    }
    let prev_bucket = ExtractBucketName(used_buckets.first().attr('id'), 1);
    $(`#config-${prev_bucket}`).prependTo('#config-wrapper').css('display', 'block');

    used_buckets.each(function () {
        // Extract the bucket name
        const bucket = ExtractBucketName($(this).attr('id'), 1);
        if (bucket !== prev_bucket) {
            $(`#config-${bucket}`).css('display', 'block');
            $(`#config-${prev_bucket}`).after($(`#config-${bucket}`));
            prev_bucket = bucket;
        }
    });
}

function getDisplay() {
    // Collect display
    const display = [];

    $.each($("input[name='display']:checked"), function () {
        display.push($(this).val());
    });

    return display;
}

function getSection() {
    // Collect sections and labels
    const sections = {};

    $.each($("input[class='sections_and_labels']"), function () {
        // Get data
        const section = this.getAttribute('data-section').toString();
        const label = this.value;

        // Add to sections
        sections[section] = label;
    });

    return sections;
}

function getDisplayBenchmark() {
    // Collect display benchmarks
    const display_benchmarks = [];

    $.each($("input[name='display_benchmarks']:checked"), function () {
        display_benchmarks.push($(this).val());
    });

    return display_benchmarks;
}

/**
 * From the set of Display Benchmarks determine which ones are
 * selected that are part of the subset
 * ['lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d']
 *
 * @returns {[]}
 */
function getSelectedCurveBenchmarks() {
    const all_selected_benchmarks = getDisplayBenchmark();
    const result_set = [];

    all_selected_benchmarks.forEach((elem) => {
        if (benchmarks_with_input_fields.includes(elem)) {
            result_set.push(elem);
        }
    });

    return result_set;
}

function getGradeableBuckets() {
    // Collect gradeable buckets
    const gradeables = [];
    $('.bucket_detail_div').each(function () {
        // Only use buckets which have display block
        // This works even if outer container is collapsed
        if ($(this).css('display') === 'block') {
            const bucket = {};

            // Extract bucket-type
            let type = $(`#${this.id} h3`);
            type = type[0].innerHTML.toLowerCase();
            bucket.type = type;

            // Extract count
            const count = $(`#config-count-${type}`).val();
            bucket.count = parseInt(count);

            // // Extract remove_lowest
            const remove_lowest = $(`#config-remove_lowest-${type}`).val();
            bucket['remove_lowest'] = parseInt(remove_lowest);

            // Extract percent
            let percent = $(`#percent-${type}`).val();
            percent = percent / 100;
            bucket.percent = percent;

            // Extract each independent gradeable in the bucket
            const ids = [];
            const selector = `#gradeables-list-${type}`;
            $(selector).children('.gradeable-li').each(function () {
                const gradeable = {};

                const children = $(this).children();
                // children[0] represents <div id="gradeable-pts-div-*">
                // children[1] represents <div id="gradeable-percents-div-*">
                // replace divs with inputs
                children[0] = children[0].children[0];
                children[1] = children[1].children[0];

                // Get max points
                gradeable.max = parseFloat(children[0].value);

                // Get gradeable final grade percent, but only if Per Gradeable Percents was selected
                if ($(children[1]).is(':visible')) {
                    gradeable.percent = parseFloat(children[1].value) / 100.0;
                }

                // Get gradeable release date
                gradeable.release_date = children[0].dataset.gradeReleaseDate;

                // Get gradeable id
                gradeable.id = $(children).find('.gradeable-id')[0].innerHTML;

                // Get per-gradeable curve data
                const curve_points_selected = getSelectedCurveBenchmarks();

                $(children).find('.gradeable-li-curve input').each(function () {
                    const benchmark = this.getAttribute('data-benchmark').toString();

                    if (curve_points_selected.includes(benchmark) && this.value) {
                        if (!Object.prototype.hasOwnProperty.call(gradeable, 'curve')) {
                            gradeable.curve = [];
                        }

                        gradeable.curve.push(parseFloat(this.value));
                    }
                });

                // Validate the set of per-gradeable curve values
                if (Object.prototype.hasOwnProperty.call(gradeable, 'curve')) {
                    // Has correct number of values
                    if (gradeable.curve.length !== curve_points_selected.length) {
                        throw `To adjust the curve for gradeable ${gradeable.id} you must enter a value in each box`;
                    }

                    let previous = gradeable.max;
                    gradeable.curve.forEach((elem) => {
                        elem = parseFloat(elem);

                        // All values are floats
                        if (isNaN(elem)) {
                            throw `All curve inputs for gradeable ${gradeable.id} must be floating point values`;
                        }

                        // Each value is greater than 0
                        if (elem < 0) {
                            throw `All curve inputs for gradeable ${gradeable.id} must be greater than or equal to 0`;
                        }

                        // Each value is less than the previous
                        if (elem > previous) {
                            throw `All curve inputs for gradeable ${gradeable.id} must be less than or equal to the maximum points for the gradeable and also less than or equal to the previous input`;
                        }

                        previous = elem;
                    });
                }

                ids.push(gradeable);
            });

            // Add gradeable buckets to gradeables array
            bucket.ids = ids;

            // Add to the gradeables array
            gradeables.push(bucket);
        }
    });

    return gradeables;
}

/**
 * Gets data to add to the JSON for the plagiarism and manual grade tables
 * @param {string} table
 *      'plagiarism'
 *      'manualGrade'
 *      'performanceWarnings'
 */
function getTableData(table) {
    if (!tables.includes(table)) {
        return;
    }

    const data = [];

    const tableMap = {
        plagiarism: 'plagiarism-table-body',
        manualGrade: 'manual-grading-table-body',
        performanceWarnings: 'performance-warnings-table-body',
    };
    const tableBody = document.getElementById(tableMap[table]);
    const rows = tableBody.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const firstInput = row.cells[0].textContent;
        const secondInput = row.cells[1].textContent;
        const thirdInput = row.cells[2].textContent;

        if (table === 'plagiarism') {
            data.push({
                user: firstInput,
                gradeable: secondInput,
                penalty: parseFloat(thirdInput),
            });
        }
        else if (table === 'manualGrade') {
            data.push({
                user: firstInput,
                grade: secondInput,
                note: thirdInput,
            });
        }
        else if (table === 'performanceWarnings') {
            const secondInputArray = secondInput.split(', ');
            data.push({
                msg: firstInput,
                ids: secondInputArray,
                value: parseFloat(thirdInput),
            });
        }
    }

    return data;
}

/**
 * Adds input data to plagiarism and manual grade tables
 * @param {string} table
 *     'plagiarism'
 *     'manualGrade'
 *     'performanceWarnings'
 */
function addToTable(table) {
    if (!tables.includes(table)) {
        return;
    }

    const tableMap = {
        plagiarism: ['plagiarism-table-body', 'plagiarism-user-id', 'g_id', 'marks'],
        manualGrade: ['manual-grading-table-body', 'manual-grading-user-id', 'manual-grading-grade', 'manual-grading-note'],
        performanceWarnings: ['performance-warnings-table-body', 'performance-warnings-message', 'performance-warnings-gradeables', 'performance-warnings-score'],
    };

    const firstInput = document.getElementById(tableMap[table][1]).value.trim();
    let secondInput;
    if (table === 'performanceWarnings') { // Performance Warnings gets an object[] for the second input
        const secondInputArray = [];
        $('#performance-warnings-gradeables').select2('data').forEach((element) => {
            secondInputArray.push(element.id);
        });
        secondInput = secondInputArray.join(', ');
    }
    else {
        secondInput = document.getElementById(tableMap[table][2]).value.trim();
    }
    const thirdInput = document.getElementById(tableMap[table][3]).value.trim();

    // Check whether input is allowed
    // eslint-disable-next-line no-undef
    const studentFullDataValues = studentFullData.map((item) => item.value);
    const tableBody = document.getElementById(tableMap[table][0]);
    const rows = tableBody.getElementsByTagName('tr');
    switch (table) {
        case 'plagiarism': {
            if (firstInput === '' || secondInput === '' || thirdInput === '') {
                alert('Please fill in all the fields.');
                return;
            }
            if (!studentFullDataValues.includes(firstInput)) {
                alert('Invalid User ID. Please enter a valid one.');
                return;
            }
            if (thirdInput > 1 || thirdInput < 0) {
                alert('Penalty must be between 0 - 1');
                return;
            }

            // Check for duplicate entries
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const existingFirstInput = row.cells[0].textContent.trim();
                const existingSecondInput = row.cells[1].textContent.trim();

                if (firstInput === existingFirstInput && secondInput === existingSecondInput) {
                    alert('Entry with the same Student ID and Gradeable already exists.');
                    return;
                }
            }
            break;
        }
        case 'manualGrade': {
            if (firstInput === '' || secondInput === '') {
                alert('Please fill in both user ID and final grade.');
                return;
            }
            if (!studentFullDataValues.includes(firstInput)) {
                alert('Invalid User ID. Please enter a valid one.');
                return;
            }
            if (!allowed_grades.includes(secondInput)) {
                alert('Grade must be one of the following: ${allowed_grades.join(\', \')}');
                return;
            }

            // Check for duplicate entries
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const existingFirstInput = row.cells[0].textContent.trim();

                if (firstInput === existingFirstInput) {
                    alert('Entry with the same Student ID already exists.');
                    return;
                }
            }
            break;
        }
        case 'performanceWarnings': {
            if (firstInput === '' || secondInput === '' || thirdInput === '') {
                alert('Please fill in all fields.');
                return;
            }
            const inputGradeables = secondInput.split(', ');
            let entryGradeables = [];
            $('#performance-warnings-table-body tr').each(function () {
                entryGradeables = $(this).find('td:nth-child(2)').text().split(', ');
            });
            const overlappingGradeables = inputGradeables.filter((inputGradeable) => entryGradeables.includes(inputGradeable));
            if (overlappingGradeables.length > 0) {
                alert(`Entry with Gradeable(s) '${overlappingGradeables.join(', ')}' already exists`);
                return;
            }
            if (parseFloat(thirdInput) <= 0) {
                alert('Score must be a number greater than 0');
                return;
            }
            break;
        }
    }

    // Create a new row and cells
    const newRow = tableBody.insertRow();

    const cellFirstInput = newRow.insertCell();
    cellFirstInput.textContent = firstInput;

    const cellSecondInput = newRow.insertCell();
    cellSecondInput.textContent = secondInput;

    const cellThirdInput = newRow.insertCell();
    cellThirdInput.textContent = thirdInput;

    const cellDelete = newRow.insertCell();
    const deleteLink = document.createElement('a');
    const deleteIcon = document.createElement('i');
    deleteIcon.className = 'fas fa-trash';
    deleteLink.appendChild(deleteIcon);
    deleteLink.onclick = function () {
        deleteRow(this);
        if (table === 'performanceWarnings') {
            manageWarningsGradeables('delete');
        }
    };
    cellDelete.appendChild(deleteLink);

    // Clear the form fields
    document.getElementById(tableMap[table][1]).value = '';
    document.getElementById(tableMap[table][2]).value = '';
    document.getElementById(tableMap[table][3]).value = '';
    saveChanges();
}

function deleteRow(button) {
    const row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
    saveChanges();
}

/**
 * Enables or disables gradeable options in the performance warnings table
 *
 * @param submitOrDelete 'submit' or 'delete'
 */
function manageWarningsGradeables(submitOrDelete) {
    let entryGradeables = [];
    $('#performance-warnings-table-body tr').each(function () {
        entryGradeables = entryGradeables.concat($(this).find('td:nth-child(2)').text().split(', '));
    });
    if (submitOrDelete === 'submit') {
        $('#performance-warnings-gradeables option').each(function () {
            if (entryGradeables.includes($(this).val())) {
                $(this).attr('disabled', 'disabled');
            }
        });
    }
    else if (submitOrDelete === 'delete') {
        $('#performance-warnings-gradeables option').each(function () {
            if (!entryGradeables.includes($(this).val())) {
                $(this).removeAttr('disabled');
            }
        });
    }
}

function getMessages() {
    const messages = [];

    const message = $('#cust_messages_textarea').val();

    if (message) {
        messages.push(message);
    }

    return messages;
}

function getBenchmarkPercent() {
    // Collect benchmark percents
    const benchmark_percent = {};
    const selected_benchmarks = getSelectedCurveBenchmarks();

    $('.benchmark_percent_input').each(function () {
        // Get data
        const benchmark = this.getAttribute('data-benchmark').toString();
        const percent = this.value;

        if (selected_benchmarks.includes(benchmark)) {
            // Verify percent is not empty
            if (percent === '') {
                throw 'All benchmark percents must have a value before saving.';
            }

            // Verify percent is a floating point number
            if (isNaN(parseFloat(percent))) {
                throw 'Benchmark percent input must be a floating point number.';
            }

            // Add to sections
            benchmark_percent[benchmark] = percent;
        }
    });

    return benchmark_percent;
}

function getFinalCutoffPercent() {
    // Verify that final_grade is used, otherwise set values to default (which will be unused)
    if (!$("input[value='final_grade']:checked").val()) {
        return {
            'A': 93.0,
            'A-': 90.0,
            'B+': 87.0,
            'B': 83.0,
            'B-': 80.0,
            'C+': 77.0,
            'C': 73.0,
            'C-': 70.0,
            'D+': 67.0,
            'D': 60.0,
        };
    }

    // Collect benchmark percents
    const final_cutoff = {};

    $('.final_cutoff_input').each(function () {
        // Get data
        const letter_grade = this.getAttribute('data-benchmark').toString();
        const percent = this.value;

        if (allowed_grades_excluding_f.includes(letter_grade)) {
            // Verify percent is not empty
            if (percent === '') {
                throw 'All final cutoffs must have a value before saving.';
            }

            // Verify percent is a floating point number
            if (isNaN(parseFloat(percent))) {
                throw 'Final cutoff input must be a floating point number.';
            }

            // Add to sections
            final_cutoff[letter_grade] = percent;
        }
    });

    return final_cutoff;
}

// This function constructs a JSON representation of all the form input
function buildJSON() {
    // Build the overall json
    let ret = {
        display: getDisplay(),
        display_benchmark: getDisplayBenchmark(),
        benchmark_percent: getBenchmarkPercent(),
        final_cutoff: getFinalCutoffPercent(),
        section: getSection(),
        gradeables: getGradeableBuckets(),
        messages: getMessages(),
        plagiarism: getTableData('plagiarism'),
        manual_grade: getTableData('manualGrade'),
        warning: getTableData('performanceWarnings'),
    };

    ret = JSON.stringify(ret);
    return ret;
}

function showLogButton(responseData) {
    $('#show_log_button').show();
    $('#save_status_log').empty();
    $('#save_status_log').append(`<pre>${responseData}</pre>`);
}

function sendSelectedValue() {
    return new Promise((resolve, reject) => {
        const selected_value = $("input[name='customization']:checked").val();
        // eslint-disable-next-line no-undef
        const url = buildCourseUrl(['reports', 'rainbow_grades_customization', 'manual_or_gui']);
        const formData = new FormData();
        // eslint-disable-next-line no-undef
        formData.append('csrf_token', csrfToken);
        formData.append('selected_value', selected_value);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (data) {
                console.log(data);
                if (data['status'] === 'success') {
                    resolve(data);
                }
                else {
                    reject(data['message']);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log('AJAX error:', jqXHR, textStatus, errorThrown);
                let errorMsg = `An error occurred: Server response: ${jqXHR.status} ${jqXHR.statusText}`;
                try {
                    // Attempt to parse JSON, if there's HTML, this will fail
                    const responseText = jqXHR.responseText;
                    const jsonStartIndex = responseText.indexOf('{');
                    if (jsonStartIndex !== -1) {
                        const jsonResponse = JSON.parse(responseText.substring(jsonStartIndex));
                        errorMsg = `${jsonResponse.message || jsonResponse.status}`;
                    }
                }
                catch (e) {
                    console.error('Failed to parse JSON response', e);
                }
                reject(errorMsg);
            },
        });
    });
}

// eslint-disable-next-line no-unused-vars
function runBuild() {
    // eslint-disable-next-line no-undef
    const url = buildCourseUrl(['reports', 'build_form']);

    sendSelectedValue()
        .then(() => {
            $.ajax({
                type: 'POST',
                url: url,
                data: { csrf_token: csrfToken },
                dataType: 'json',
                success: function (response) {
                    console.log(response);
                    if (response.status === 'success') {
                        $('#save_status').text('Generating rainbow grades, please wait...');
                        checkBuildStatus();
                    }
                    else {
                        $('#save_status').text('An error occurred while building');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log('AJAX error:', jqXHR, textStatus, errorThrown);
                    $('#save_status').text('An error occurred while making the request');
                },
            });
        })
        .catch((error) => {
            console.error('Caught error:', error);
            $('#save_status').text(`An error occurred: ${error}`);
        });
}

function checkBuildStatus() {
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['reports', 'rainbow_grades_status']),
        data: { csrf_token: csrfToken },
        dataType: 'json',
        success: function (response) {
            console.log(response);
            if (response.status === 'success') {
                $('#save_status').text('Rainbow grades successfully generated!');
                showLogButton(response.data);
            }
            else if (response.status === 'fail') {
                $('#save_status').text('A failure occurred generating rainbow grades');
                showLogButton(response.message);
            }
            else {
                $('#save_status').text('Internal Server Error');
                console.log(response);
            }
        },
        error: function (xhr, status, error) {
            console.error(`Failed to parse response from server: ${xhr.responseText}`);
        },
    });
}

$(document).ready(() => {
    $("input[name*='display']").change(() => {
        saveChanges();
    });
    // Register change handlers to update the status message when form inputs change
    $("input[name*='display_benchmarks']").change(() => {
        saveChanges();
    });
    $('#cust_messages_textarea').on('change keyup paste focusout', () => {
        saveChanges();
    });
    $('.sections_and_labels').on('change keyup paste', () => {
        saveChanges();
    });
    $('.final_cutoff_input').on('change keyup paste', () => {
        saveChanges();
    });
    // Attach a focusout event handler to all input and textarea elements within #gradeables after user finishes typing
    $('#gradeables').find('input, textarea').on('focusout', () => {
        saveChanges();
    });

    // This mutation observer catches changes to bucket assignments (available buckets to assigned buckets, and vice versa)
    const targetBucketReassignment = document.querySelector('#buckets_used_list');
    const observerBucketReassignment = new MutationObserver((mutations) => {
        saveChanges();
    });
    const configBucketReassignment = { attributes: true, childList: true, characterData: true };
    observerBucketReassignment.observe(targetBucketReassignment, configBucketReassignment);

    // This mutation observer catches automatic bucket assignments on page load
    const targetAutomaticBucketAssignment = document.querySelector('.bucket_detail_div');
    const observerAutomaticBucketAssignment = new MutationObserver((mutations) => {
        saveChanges();
    });
    const configAutomaticBucketAssignment = { attributes: true, attributeFilter: ['style'] };
    observerAutomaticBucketAssignment.observe(targetAutomaticBucketAssignment, configAutomaticBucketAssignment);
});

function saveChanges() {
    $('#save_status').text('Change detected Saving ...');
    const url = buildCourseUrl(['reports', 'rainbow_grades_customization_save']);
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    try {
        formData.append('json_string', buildJSON());
    }
    catch (err) {
        console.error(err);
        $('#save_status').text('An error occurred while saving.');
        return;
    }

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function (response) {
            if (response['status'] === 'success') {
                $('#save_status').text('All changes saved');
            }
            else {
                console.error(response);
            }
        },
        // error: function (jqXHR, textStatus, errorThrown) {
        //     console.error(`Error status: ${textStatus}`);
        //     console.error(`Error thrown: ${errorThrown}`);
        //     console.error(`Server response: ${jqXHR.status} ${jqXHR.statusText}`);
        // },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log('AJAX error:', jqXHR, textStatus, errorThrown);
            let errorMsg = `An error occurred: Server response: ${jqXHR.status} ${jqXHR.statusText}`;
            try {
                // Attempt to parse JSON, if there's HTML, this will fail
                const responseText = jqXHR.responseText;
                const jsonStartIndex = responseText.indexOf('{');
                if (jsonStartIndex !== -1) {
                    const jsonResponse = JSON.parse(responseText.substring(jsonStartIndex));
                    errorMsg = `${jsonResponse.message || jsonResponse.status}`;
                }
            }
            catch (e) {
                console.error('Failed to parse JSON response', e);
            }
        },
    });
}

$(document).ready(() => {
    $("input[name='customization']").change(() => {
        $('#save_status').text('Switched customization, need to rebuild');
    });
});

/**
 * Sets the visibility for 'benchmark percent' input boxes and also per-gradeable curve input boxes
 * based upon boxes in 'display benchmark' being selected / un-selected
 *
 * @param elem The checkbox input element captured from 'display benchmark'
 */
function setInputsVisibility(elem) {
    const benchmark = elem.value;
    const is_checked = elem.checked;

    // Only care about inputs which are part of the benchmarks_with_input_fields
    if (benchmarks_with_input_fields.includes(benchmark)) {
        if (is_checked) {
            $(`.${benchmark}`).show();
        }
        else {
            $(`.${benchmark}`).hide();
        }
    }

    // If all boxes are unchecked can hide benchmark percent box and all per-gradeable curve options
    if (getSelectedCurveBenchmarks().length === 0) {
        $('#benchmark_percents').hide();
        $('.fa-gradeable-curve').hide();
        $('.gradeable-li-curve').hide();
    }
    else {
        $('#benchmark_percents').show();
        $('.fa-gradeable-curve').show();
    }
}

/**
 * Sets the visibility for input boxes other than benchmark percents
 * based on the corresponding boxes in 'display' being selected / un-selected
 * */
function setCustomizationItemVisibility(elem) {
    // maps a checkbox name to the corresponding customization item id
    const checkbox_to_cust_item = {
        final_grade: '#final_grade_cutoffs',
        messages: '#cust_messages',
        section: '#section_labels',
        warning: '#performance-warnings',
    };
    const checkbox_name = elem.value;
    const cust_item_id = checkbox_to_cust_item[checkbox_name];
    const is_checked = elem.checked;

    $(cust_item_id).toggle(is_checked);

    // manual grading is dependent on final grade cutoffs
    if (checkbox_name === 'final_grade') {
        $('#manual-grading').toggle(is_checked);
    }
}

$(document).ready(() => {
    // Make the per-gradeable curve inputs toggle when the icon is clicked
    // eslint-disable-next-line no-unused-vars
    $('.fa-gradeable-curve').click(function (event) {
        const id = jQuery(this).attr('id').split('-')[3];
        $(`#gradeable-curve-div-${id}`).toggle();
    });

    // By default, open the input fields for per-gradable curves which have been previously set
    $('.gradeable-li-curve').each(function () {
        let has_at_least_one_value = false;

        // Determine if any of the input boxes had a value pre-loaded into them
        $(this).children('input').each(function () {
            if (this.value) {
                has_at_least_one_value = true;
            }
        });

        // If so then open the per-gradeable curve input div
        if (has_at_least_one_value) {
            const id = jQuery(this).attr('id').split('-')[3];
            $(`#gradeable-curve-div-${id}`).toggle();
        }
    });

    /**
     * Configure visibility handlers for curve input boxes
     * Curve input boxes include the benchmark percent input boxes and also the per-gradeable curve input boxes
     * Visibility is controlled by which boxes are selected in the display benchmarks area
     */
    $('#display_benchmarks input').each(function () {
        // Set the initial visibility on load
        setInputsVisibility(this);

        // Register a click handler to adjust visibility when boxes are selected / un-selected
        $(this).change(function () {
            setInputsVisibility(this);
        });
    });

    /**
     * Configure visibility handler for all customization items other than benchmark percents
     * Visibility is controlled by whether the corresponding boxes are selected in the display area
     */
    const dropdown_checkboxes = ['final_grade', 'messages', 'section', 'warning'];
    $('#display input').each(function () {
        if (dropdown_checkboxes.includes(this.value)) {
            // Set the initial visibility on load
            setCustomizationItemVisibility(this);

            // Register a click handler to adjust visibility when boxes are selected / un-selected
            $(this).change(function () {
                setCustomizationItemVisibility(this);
            });
        }
    });

    // Display auto rainbow grades log on button click
    $('#show_log_button').click(() => {
        $('#save_status_log').toggle();
    });

    // Hide the loading div and display the form once all form configuration is complete
    $(document).ready(() => {
        $('#rg_web_ui_loading').hide();
        $('#rg_web_ui').show();
    });
});

$(document).ready(() => {
    // Button click event
    $('#btn-upload-customization').click(() => {
        $('#config-upload').click();
    });

    // File input change event
    $('#config-upload').on('change', function () {
        const selected_file = $(this)[0].files[0];
        console.log('Selected File: ', selected_file);

        // eslint-disable-next-line no-undef
        const url = buildCourseUrl(['reports', 'rainbow_grades_customization', 'upload']);
        console.log('URL: ', url);

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('config_upload', selected_file);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (jsonData) {
                const data = JSON.parse(jsonData);
                console.log(`Data: ${JSON.stringify(data)}`);

                // Check if server reports that file exists
                const manual_customization_exists = data['data']['manual_customization_exists'];
                console.log(`manual_customization_exists: ${manual_customization_exists}`);

                if (data['status'] === 'fail') {
                    displayErrorMessage(data['message']);
                    $('#config-upload').focus();
                }
                else {
                    displaySuccessMessage('Manual Customization uploaded successfully');
                    if (manual_customization_exists) {
                        $('#ask_which_customization').show();
                        $('#manual_customization').prop('checked', true);
                        $('#gui_customization').prop('checked', false);
                    }
                    else {
                        $('#ask_which_customization').hide();
                        $('#manual_customization').prop('checked', false);
                        $('#gui_customization').prop('checked', true);
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(`Error status: ${textStatus}`);
                console.log(`Error thrown: ${errorThrown}`);
                console.log(`Server response: ${jqXHR.status} ${jqXHR.statusText}`);
            },
        });
        $(this).val('');
    });
});

$(document).ready(() => {
    $('#pencilIcon').click((event) => {
        event.stopPropagation();
        const checkboxControls = $('#checkboxControls');
        const dropLowestDiv = $('#dropLowestDiv');

        checkboxControls.css('display') === 'none'
            ? checkboxControls.show()
            : checkboxControls.hide() && dropLowestDiv.hide();
    });
    $('#drop_lowest_checkbox').change(function (event) {
        event.stopPropagation();
        const dropLowestDivs = $('div[id^="dropLowestDiv-"]');
        const isChecked = $(this).is(':checked');

        dropLowestDivs.each((index, dropLowestDiv) => {
            $(dropLowestDiv).css('display', isChecked ? 'block' : 'none');
        });
    });

    { // Manage performance warnings table
        $('#performance-warnings-gradeables').select2({
            theme: 'bootstrap-5',
            placeholder: ' -- select an option -- ',
            multiple: true,
            allowClear: true,
        });
        const gradeablesDropdownOptions = $('#performance-warnings-gradeables option');
        // Remove empty option to trick browser
        gradeablesDropdownOptions[0].remove();
        // Hide selected gradeables
        let entryGradeables = [];
        $('#performance-warnings-table-body tr').each(function () {
            entryGradeables = entryGradeables.concat($(this).find('td:nth-child(2)').text().split(', '));
        });
        gradeablesDropdownOptions.each(function () {
            const gradeableID = $(this).val();
            if (entryGradeables.includes(gradeableID)) {
                $(this).attr('disabled', 'disabled');
            }
        });
    }

    // Per Gradeable Percents checked on-ready if at least one Per Gradeable Percents is checked
    const enablePerGradeablePercents = $('#enable-per-gradeable-percents');
    const perGradeablePercentsCheckboxes = $('input[id^="per-gradeable-percents-checkbox-"]');
    perGradeablePercentsCheckboxes.each((index, perGradeablePercentsCheckboxDOMElement) => {
        if ($(perGradeablePercentsCheckboxDOMElement).is(':checked')) {
            enablePerGradeablePercents.prop('checked', true);
            return false; // Break loop
        }
    });

    // Control visibility of per gradeable percent checkboxes
    const perGradeablePercentsLabels = $('label[id^="per-gradeable-percents-label-"]');
    const perGradeablePercentsReset = $('button[id^="per-gradeable-percents-reset-"]');
    const isChecked = enablePerGradeablePercents.is(':checked');
    perGradeablePercentsCheckboxes.each((index, checkbox) => {
        $(checkbox).toggle(isChecked);
    });
    perGradeablePercentsLabels.each((index, label) => {
        $(label).toggle(isChecked);
    });
    perGradeablePercentsReset.each((index, button) => {
        if (isChecked === false) { // Only hide, otherwise element will be out of place
            $(button).hide();
        }
    });
    enablePerGradeablePercents.change(function (event) {
        event.stopPropagation();
        const isChecked = $(this).is(':checked');
        perGradeablePercentsCheckboxes.each((index, checkbox) => {
            $(checkbox).toggle(isChecked);
        });
        perGradeablePercentsLabels.each((index, label) => {
            $(label).toggle(isChecked);
        });
        perGradeablePercentsReset.each((index, button) => {
            if (isChecked === false) { // Only hide, otherwise element will be out of place
                $(button).hide();
            }
        });
    });

    // Control visibility of per gradeable percent input boxes
    perGradeablePercentsCheckboxes.each((index, perGradeablePercentsCheckboxDOMElement) => {
        const perGradeablePercentsCheckbox = $(perGradeablePercentsCheckboxDOMElement);
        const bucket = perGradeablePercentsCheckbox[0].id.match(/^per-gradeable-percents-checkbox-(.+)$/)[1];
        const percentsInputsInBucket = $(`div[id^="gradeable-percents-div-${bucket}"]`);
        const resetButtonInBucket = $(`button[id^="per-gradeable-percents-reset-${bucket}"]`);
        ClampPerGradeablePercents(percentsInputsInBucket.children()[0], bucket);

        const isChecked = perGradeablePercentsCheckbox.is(':checked');
        percentsInputsInBucket.each((index, percentInput) => {
            $(percentInput).toggle(isChecked);
        });
        resetButtonInBucket.each((index, resetButton) => {
            $(resetButton).toggle(isChecked);
        });

        perGradeablePercentsCheckbox.change(function (event) {
            event.stopPropagation();
            const isChecked = $(this).is(':checked');
            percentsInputsInBucket.each((index, percentInput) => {
                $(percentInput).toggle(isChecked);
            });
            resetButtonInBucket.each((index, resetButton) => {
                $(resetButton).toggle(isChecked);
            });
        });
    });
});

$(document).ready(() => {
    // Bind click listener to grade summaries button
    $('#grade-summaries-button').click(() => {
        $('#grade-summaries-last-run').text('Running...');
    });
});
