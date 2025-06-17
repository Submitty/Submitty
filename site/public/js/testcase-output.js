// Map to store the XMLHttpRequest object returned by getJSON when getting the
// output to display in loadTestCaseOutput()
// Key: div_name ("testcase_" + index)   Value: XMLHttpRequest object
const loading_test_cases_xml_http_requests = new Map();

// String containing the name of the div for expand all toggle
const expand_all_toggle_div_name = '#tc_expand_all';

// String containing the name of the div for collapse all toggle
const collapse_all_toggle_div_name = '#tc_collapse_all';

/**
 * Collapses test case output. If all test cases have been collapsed,
 * enables "Expand All Test Cases" toggle and disables "Collapse All Test Cases" toggle.
 *
 * @param {string} div_name - id of div containing the output of the test case
 * @param {string} index - index of test case
 * @param {int} num_test_cases - number of test cases
 * @param {object} loadingTools - loading tools used by span containing the text that
 *                                correspond to the state of the test case (show, hide, loading)
 * @param {bool} check_all_test_cases_states - should the states of all test cases be checked to
 *                                             set the state the Expand/Collapse All Toggles
 */
function CollapseTestCaseOutput(div_name, index, num_test_cases, loadingTools, check_all_test_cases_states) {
    $(`#show_char_${index}`).toggle();
    $(`#${div_name}`).empty();
    // eslint-disable-next-line no-undef
    toggleDiv(div_name);

    loadingTools.find('.loading-tools-hide').hide();
    loadingTools.find('.loading-tools-in-progress').hide();
    loadingTools.find('.loading-tools-show').show();

    // Check if all test cases are all collapsed
    if (check_all_test_cases_states) {
        CheckStatesOfAllTestCases(num_test_cases, false);
    }
}

/**
 * Enables "Expand All Test Cases" toggle or "Collapse All Test Cases" toggle.
 *
 * @global {expand_all_toggle_div_name} : String - name of the div for expand all toggle
 * @global {collapse_all_loading_tools} : String - name of the div for collapse all toggle
 *
 * @param {boolean} enable_expand_all - should the "Expand All Test Cases" toggle (true) or
 *                                      the "Collapse All Test Cases" toggle be enabled
 */
function EnableExpandAllOrCollapseAllToggles(enable_expand_all) {
    if (enable_expand_all) {
        $(expand_all_toggle_div_name).css('cursor', 'pointer');
        const expand_all_loading_tools = $(expand_all_toggle_div_name).find('.loading-tools');
        expand_all_loading_tools.find('.loading-tools-show').css('color', 'var(--standard-deep-blue)');
        expand_all_loading_tools.find('.loading-tools-show').css('textDecoration', 'underline');
    }
    else {
        $(collapse_all_toggle_div_name).css('cursor', 'pointer');
        const collapse_all_loading_tools = $(collapse_all_toggle_div_name).find('.loading-tools');
        collapse_all_loading_tools.find('.loading-tools-hide').css('color', 'var(--standard-deep-blue)');
        collapse_all_loading_tools.find('.loading-tools-hide').css('textDecoration', 'underline');
    }
}

/**
 * If all test cases have been expanded or collapsed.
 * If all are expanded, disable "Expand All Test Cases" toggle and enable "Collapse All Test Cases" toggle.
 * If all are collapse, disable "Collapse All Test Cases" toggle and enable "Expand All Test Cases" toggle.
 * If some are expanded and some are collapse, enable "Expand All Test Cases" and "Collapse All Test Cases" toggles.
 *
 * @global {expand_all_toggle_div_name} : String - name of the div for expand all toggle
 * @global {collapse_all_loading_tools} : String - name of the div for collapse all toggle
 *
 * @param {int} num_test_cases - number of test cases
 * @param {boolean} are_test_cases_expanded - should the call check if all test cases have been expanded
 */
function CheckStatesOfAllTestCases(num_test_cases, are_test_cases_expanded) {
    // Expand/Collapse all test cases
    let test_cases_have_same_state = true;
    for (let index = 0; index < num_test_cases; index++) {
        // Check if test case has loading tools (can be expanded/collapsed)
        if ($(`#tc_${index}`).find('.loading-tools').length === 1) {
            const isTestCaseLoadingToolsShowVisible = $(`#tc_${index}`).find('.loading-tools').find('.loading-tools-show').is(':visible');

            // Check if test case can be expanded/collapsed
            if ((are_test_cases_expanded && isTestCaseLoadingToolsShowVisible) || (!are_test_cases_expanded && !isTestCaseLoadingToolsShowVisible)) {
                test_cases_have_same_state = false;
                break;
            }
        }
    }

    // Are all test cases set to the same state
    if (test_cases_have_same_state) {
        if (are_test_cases_expanded) {
            EnableExpandAllOrCollapseAllToggles(false);
            $(expand_all_toggle_div_name).css('cursor', 'default');
            const expand_all_loading_tools = $(expand_all_toggle_div_name).find('.loading-tools');
            expand_all_loading_tools.find('.loading-tools-show').css('color', 'var(--standard-medium-dark-gray)');
            expand_all_loading_tools.find('.loading-tools-show').css('textDecoration', 'none');
        }
        else {
            EnableExpandAllOrCollapseAllToggles(true);
            $(collapse_all_toggle_div_name).css('cursor', 'default');
            const collapse_all_loading_tools = $(collapse_all_toggle_div_name).find('.loading-tools');
            collapse_all_loading_tools.find('.loading-tools-hide').css('color', 'var(--standard-medium-dark-gray)');
            collapse_all_loading_tools.find('.loading-tools-hide').css('textDecoration', 'none');
        }
    }
    else {
        EnableExpandAllOrCollapseAllToggles(true);
        EnableExpandAllOrCollapseAllToggles(false);
    }
}

/**
 * Expands or Collapses test case output.
 *
 * @global {loading_test_cases_xml_http_requests} : Map (div_name ("testcase_" + index); Value - XMLHttpRequest object)
 *                        to store the XMLHttpRequest object returned by getJSON when getting the output to display
 *
 * @param {string} div_name - ID of div containing the output of the test case
 * @param {string} gradeable_id - ID of the gradeable
 * @param {string} who_id - ID of the submitter
 * @param {string} index - index of test case
 * @param {int} num_test_cases - number of test cases
 * @param {number} version - submission version
 * @param {bool} check_all_test_cases_states - should the states of all test cases be checked to
 *                                             set the state the Expand/Collapse All Toggles
 */
function loadTestCaseOutput(div_name, gradeable_id, who_id, index, num_test_cases, version = '', check_all_test_cases_states = true) {
    const orig_div_name = div_name;
    div_name = `#${div_name}`;

    const loadingTools = $(`#tc_${index}`).find('.loading-tools');

    // Checks if output for this div is being loaded
    if (loading_test_cases_xml_http_requests.has(orig_div_name)) {
        // Checks if xhr is defined and has not succeeded yet
        // If so, abort loading
        const xhr = loading_test_cases_xml_http_requests.get(orig_div_name);
        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
            loading_test_cases_xml_http_requests.delete(orig_div_name);
        }
    }

    // Checks if test case output is visible
    // Output is visible - Collapse test case output
    if ($(div_name).is(':visible')) {
        CollapseTestCaseOutput(orig_div_name, index, num_test_cases, loadingTools, check_all_test_cases_states);
    }
    // Output is not visible - Expand test case output
    else {
        $(`#show_char_${index}`).toggle();
        // eslint-disable-next-line no-undef
        const url = `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output'])}?who_id=${who_id}&index=${index}&version=${version}`;

        loadingTools.find('.loading-tools-show').hide();
        loadingTools.find('.loading-tools-in-progress').show();

        // Check if all test cases are all expanded
        if (check_all_test_cases_states) {
            CheckStatesOfAllTestCases(num_test_cases, true);
        }

        loading_test_cases_xml_http_requests.set(orig_div_name, $.getJSON({
            url: url,
            async: true,
            success: function (response) {
                if (response.status !== 'success') {
                    alert(`Error getting file diff: ${response.message}`);
                    return;
                }
                loading_test_cases_xml_http_requests.delete(orig_div_name);

                $(div_name).empty();
                $(div_name).html(response.data);
                // eslint-disable-next-line no-undef
                toggleDiv(orig_div_name);

                loadingTools.find('.loading-tools-in-progress').hide();
                loadingTools.find('.loading-tools-hide').show();

                // eslint-disable-next-line no-undef
                enableKeyToClick();
            },
            error: function (e) {
                // Check if error was occurred by candelling a test case
                if (loading_test_cases_xml_http_requests.has(orig_div_name) && loading_test_cases_xml_http_requests.get(orig_div_name).readyState === 0) {
                    loading_test_cases_xml_http_requests.delete(orig_div_name);
                    CollapseTestCaseOutput(orig_div_name, index, num_test_cases, loadingTools, check_all_test_cases_states);
                }
                else {
                    // Display error message once
                    loading_test_cases_xml_http_requests.delete(orig_div_name);
                    if (loading_test_cases_xml_http_requests.size === 0) {
                        alert('Could not load diff, please refresh the page and try again.');
                        console.log(e);
                        // eslint-disable-next-line no-undef
                        displayAjaxError(e);
                    }
                }
            },
        }));
    }
}

/**
 * Expands or Collapses all test case outputs.
 *
 * @global {loading_test_cases_xml_http_requests} : Map (Key - string [orig_div_name]; Value - XMLHttpRequest object)
 *                        to store the XMLHttpRequest object returned by getJSON when getting the output to display
 *
 * @param {string} div_name - ID of div that calls this function
 * @param {boolean} expand_all - should all test cases be expanded
 * @param {int} num_test_cases - number of test cases
 * @param {string} gradeable_id - ID of the gradeable
 * @param {string} who_id - ID of the submitter
 * @param {number} version - submission version
 */
function loadAllTestCaseOutput(div_name, expand_all, num_test_cases, gradeable_id, who_id, version = '') {
    // Check if toggle should not be interacted with
    if ($(`#${div_name}`).css('cursor') === 'default') {
        return;
    }

    // Expand/Collapse all test cases
    for (let index = 0; index < num_test_cases; index++) {
        // Check if test case has loading tools (can be expanded/collapsed)
        if ($(`#tc_${index}`).find('.loading-tools').length === 1) {
            const test_case_div_name = `testcase_${index}`;
            const isTestCaseLoadingToolsShowVisible = $(`#tc_${index}`).find('.loading-tools').find('.loading-tools-show').is(':visible');

            // Check if test case should be expanded/collapsed
            if ((expand_all && isTestCaseLoadingToolsShowVisible) || (!expand_all && !isTestCaseLoadingToolsShowVisible)) {
                loadTestCaseOutput(test_case_div_name, gradeable_id, who_id, index, num_test_cases, version, false);
            }
        }
    }

    CheckStatesOfAllTestCases(num_test_cases, expand_all);
}
