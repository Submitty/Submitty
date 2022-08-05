// Map to store the XMLHttpRequest object returned by getJSON when getting the
// output to display in loadTestcaseOutput()
// Key: div_name ("testcase_" + index)   Value: XMLHttpRequest object
const loading_testcases_xml_http_requests = new Map();

// String containing the name of the div for expand all toggle
const expand_all_toggle_div_name = '#tc_expand_all';

// String containing the name of the div for collapse all toggle
const collapse_all_toggle_div_name = '#tc_collapse_all';


/**
 * Collapses test case output. If all test cases have been collapsed,
 * enables "Expand All Test Cases" toggle and disables  "Collapse All Test Cases" toggle.
 *
 * @param {string} div_name - id of div containing the output of the test case
 * @param {string} index - index of test case
 * @param {object} loadingTools - loading tools used by span containing the text that
 *                                correspond to the state of the test case (show, hide, loading)
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 */
function CollapseTestcaseOutput(div_name, index, loadingTools, test_cases, show_hidden, show_hidden_details) {
    $(`#show_char_${index}`).toggle();
    $(`#${div_name}`).empty();
    // eslint-disable-next-line no-undef
    toggleDiv(div_name);

    loadingTools.find('.loading-tools-hide').hide();
    loadingTools.find('.loading-tools-in-progress').hide();
    loadingTools.find('.loading-tools-show').show();

    // Check if all test cases are all collapsed
    CheckStatesOfAllTestcases(test_cases, show_hidden, show_hidden_details, false);
}

/**
 * Enables "Expand All Test Cases" toggle or "Collapse All Test Cases" toggle.
 *
 * @global {expand_all_toggle_div_name} : String - name of the div for expand all toggle
 * @global {collapse_all_loading_tools} : String - name of the div for collapse all toggle
 *
 * @param {boolean} enable_expand_all - should the "Expand All Test Cases" toggle (true) or
 *                                       the "Collapse All Test Cases" toggle be enabled
 */
function EnableExpandALLOrCollapseAllToggles(enable_expand_all) {
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
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 * @param {boolean} are_test_cases_expanded - should the call check if all test cases have been expanded
 */
function CheckStatesOfAllTestcases(test_cases, show_hidden, show_hidden_details, are_test_cases_expanded) {
    const parsed_test_cases = JSON.parse(test_cases);
    // Expand/Collapse all test cases
    let test_cases_have_same_state = true;
    parsed_test_cases.forEach((test_case, index) => {
        const isTestCaseLoadingToolsShowVisible = $(`#tc_${index}`).find('.loading-tools').find('.loading-tools-show').is(':visible');
        const can_view_details = (!test_case.hidden || (show_hidden_details || test_case.release_hidden_details) && show_hidden);

        // Check if test case can be expanded/collapsed
        if (can_view_details && test_case.has_extra_results &&
            ((are_test_cases_expanded && isTestCaseLoadingToolsShowVisible) || (!are_test_cases_expanded && !isTestCaseLoadingToolsShowVisible))) {
            test_cases_have_same_state = false;
        }
    });

    // Are all test cases set to the same state
    if (test_cases_have_same_state) {
        if (are_test_cases_expanded) {
            EnableExpandALLOrCollapseAllToggles(false);
            $(expand_all_toggle_div_name).css('cursor', 'default');
            const expand_all_loading_tools = $(expand_all_toggle_div_name).find('.loading-tools');
            expand_all_loading_tools.find('.loading-tools-show').css('color', 'var(--standard-medium-dark-gray)');
            expand_all_loading_tools.find('.loading-tools-show').css('textDecoration', 'none');
        }
        else {
            EnableExpandALLOrCollapseAllToggles(true);
            $(collapse_all_toggle_div_name).css('cursor', 'default');
            const collapse_all_loading_tools = $(collapse_all_toggle_div_name).find('.loading-tools');
            collapse_all_loading_tools.find('.loading-tools-hide').css('color', 'var(--standard-medium-dark-gray)');
            collapse_all_loading_tools.find('.loading-tools-hide').css('textDecoration', 'none');
        }
    }
    else {
        EnableExpandALLOrCollapseAllToggles(true);
        EnableExpandALLOrCollapseAllToggles(false);
    }
}

/**
 * Expands or Collapses test case output.
 *
 * @global {loading_testcases_xml_http_requests} : Map (div_name ("testcase_" + index); Value - XMLHttpRequest object)
 *                         to store the XMLHttpRequest object returned by getJSON when getting the output to display
 *
 * @param {string} div_name - ID of div containing the output of the test case
 * @param {string} gradeable_id - ID of the gradeable
 * @param {string} who_id - ID of the submitter
 * @param {string} index - index of test case
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 * @param {number} version - submission version
 */
// eslint-disable-next-line no-unused-vars
function loadTestcaseOutput(div_name, gradeable_id, who_id, index, test_cases, show_hidden, show_hidden_details, version = '') {
    const orig_div_name = div_name;
    div_name = `#${div_name}`;

    const loadingTools = $(`#tc_${index}`).find('.loading-tools');

    // Checks if output for this div is being loaded
    if (loading_testcases_xml_http_requests.has(orig_div_name)) {
        // Checks if xhr is defined and has not succeeded yet
        // If so, abort loading
        const xhr = loading_testcases_xml_http_requests.get(orig_div_name);
        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
            loading_testcases_xml_http_requests.delete(orig_div_name);
        }
    }

    // Checks if test case output is visible
    // Output is visible - Collapse test case output
    if ($(div_name).is(':visible')) {
        CollapseTestcaseOutput(orig_div_name, index, loadingTools, test_cases, show_hidden, show_hidden_details);
    }
    // Output is not visible - Expand test case output
    else {
        $(`#show_char_${index}`).toggle();
        // eslint-disable-next-line no-undef
        const url = `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output'])}?who_id=${who_id}&index=${index}&version=${version}`;

        loadingTools.find('.loading-tools-show').hide();
        loadingTools.find('.loading-tools-in-progress').show();

        // Check if all test cases are all expanded
        CheckStatesOfAllTestcases(test_cases, show_hidden, show_hidden_details, true);

        loading_testcases_xml_http_requests.set(orig_div_name, $.getJSON({
            url: url,
            async: true,
            success: function(response) {
                if (response.status !== 'success') {
                    alert(`Error getting file diff: ${response.message}`);
                    return;
                }
                loading_testcases_xml_http_requests.delete(orig_div_name);

                $(div_name).empty();
                $(div_name).html(response.data);
                // eslint-disable-next-line no-undef
                toggleDiv(orig_div_name);

                loadingTools.find('.loading-tools-in-progress').hide();
                loadingTools.find('.loading-tools-hide').show();

                // eslint-disable-next-line no-undef
                enableKeyToClick();
            },
            error: function(e) {
                if (loading_testcases_xml_http_requests.has(orig_div_name) && loading_testcases_xml_http_requests.get(orig_div_name).readyState === 0) {
                    loading_testcases_xml_http_requests.delete(orig_div_name);
                    CollapseTestcaseOutput(orig_div_name, index, loadingTools, test_cases, show_hidden, show_hidden_details);
                }
                else {
                    loading_testcases_xml_http_requests.delete(orig_div_name);
                    if (loading_testcases_xml_http_requests.size === 0) {
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
 * @global {loading_testcases_xml_http_requests} : Map (Key - string [orig_div_name]; Value - XMLHttpRequest object)
 *                         to store the XMLHttpRequest object returned by getJSON when getting the output to display
 *
 * @param {string} div_name - ID of div that calls this function
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} expand_all - should all test cases be expanded
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 * @param {string} gradeable_id - ID of the gradeable
 * @param {string} who_id - ID of the submitter
 * @param {number} version - submission version
 */
// eslint-disable-next-line no-unused-vars
function loadAllTestcaseOutput(div_name, test_cases, expand_all, show_hidden, show_hidden_details, gradeable_id, who_id, version = '') {
    const parsed_test_cases = JSON.parse(test_cases);

    // Check if toggle should not be interacted with
    if ($(`#${div_name}`).css('cursor') === 'default') {
        return;
    }

    // Expand/Collapse all test cases
    parsed_test_cases.forEach((test_case, index)  => {
        const test_case_div_name = `testcase_${index}`;
        const isTestCaseLoadingToolsShowVisible = $(`#tc_${index}`).find('.loading-tools').find('.loading-tools-show').is(':visible');
        const can_view_details = (!test_case.hidden || (show_hidden_details || test_case.release_hidden_details) && show_hidden);

        // Check if test case should be expanded/collapsed
        if (can_view_details && test_case.has_extra_results &&
            ((expand_all && isTestCaseLoadingToolsShowVisible) || (!expand_all && !isTestCaseLoadingToolsShowVisible))) {
            loadTestcaseOutput(test_case_div_name, gradeable_id, who_id, index, test_cases, version);
        }
    });
}
