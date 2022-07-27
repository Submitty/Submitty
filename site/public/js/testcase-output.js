// Map to store the XMLHttpRequest object returned by getJSON when getting the
// output to display in loadTestcaseOutput()
// Key: div_name ("testcase_" + index)   Value: XMLHttpRequest object
const loading_testcases_xml_http_requests = new Map();

/**
 * Collapses test case output. If all test cases have been collapsed,
 * set "Expand All Test Cases" toggle to "Collapse All Test Cases".
 *
 * @param {string} div_name - id of div containing the output of the test case
 * @param {string} index - index of test case
 * @param {object} loadingTools - loading tools used by span containing the text that
 *                                correspond to the state of the test case (show, hide, loading)
 * @param {object} set_all_loadingTools - loading tools used by span containing the text that
 *                                        correspond to expand/collapse all test cases (show, hide)
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 */
function CollapseTestcaseOutput(div_name, index, loadingTools, set_all_loadingTools, test_cases, show_hidden, show_hidden_details) {
    $(`#show_char_${index}`).toggle();
    $(`#${div_name}`).empty();
    // eslint-disable-next-line no-undef
    toggleDiv(div_name);

    loadingTools.find('.loading-tools-hide').hide();
    loadingTools.find('.loading-tools-in-progress').hide();
    loadingTools.find('.loading-tools-show').show();

    // Check if all test cases are all collapsed
    ChcekStatesOfAllTestcases(set_all_loadingTools, test_cases, show_hidden, show_hidden_details, false);
}

/**
 * If all test cases have been expanded or collapsed.  If so, set "Expand All Test Cases" toggle to "Collapse All Test Cases".
 *
 * @param {object} set_all_loadingTools - loading tools used by span containing the text that
 *                                        correspond to expand/collapse all test cases (show, hide)
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 * @param {boolean} are_test_cases_expanded - should the call check if all test cases have been expanded
 */
function ChcekStatesOfAllTestcases(set_all_loadingTools, test_cases, show_hidden, show_hidden_details, are_test_cases_expanded) {
    const parsed_test_cases = JSON.parse(test_cases);
    // Expand/Collapse all test cases
    let test_cases_have_same_state = true;
    parsed_test_cases.forEach((test_case, index)  => {
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
            // All test cases are all expanded. Set expand/collapse all toggle to collapse all.
            set_all_loadingTools.find('.loading-tools-hide').show();
            set_all_loadingTools.find('.loading-tools-show').hide();
        }
        else {
            // All test cases are all collapsed. Set expand/collapse all toggle to expand all.
            set_all_loadingTools.find('.loading-tools-hide').hide();
            set_all_loadingTools.find('.loading-tools-show').show();
        }
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
 * @param {string} set_all_div_name - ID of div that calls this function
 * @param {Array.<JSON>} test_cases - array of test case objects encoded using JSON
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 * @param {number} version - submission version
 */
// eslint-disable-next-line no-unused-vars
function loadTestcaseOutput(div_name, gradeable_id, who_id, index, set_all_div_name, test_cases, show_hidden, show_hidden_details, version = '') {
    const orig_div_name = div_name;
    div_name = `#${div_name}`;

    const loadingTools = $(`#tc_${index}`).find('.loading-tools');
    const set_all_loadingTools = $(`#${set_all_div_name}`).find('.loading-tools');

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
        CollapseTestcaseOutput(orig_div_name, index, loadingTools, set_all_loadingTools, test_cases, show_hidden, show_hidden_details);
    }
    // Output is not visible - Expand test case output
    else {
        $(`#show_char_${index}`).toggle();
        // eslint-disable-next-line no-undef
        const url = `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output'])}?who_id=${who_id}&index=${index}&version=${version}`;

        loadingTools.find('.loading-tools-show').hide();
        loadingTools.find('.loading-tools-in-progress').show();

        // Check if all test cases are all expanded
        ChcekStatesOfAllTestcases(set_all_loadingTools, test_cases, show_hidden, show_hidden_details, true);

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
                    CollapseTestcaseOutput(orig_div_name, index, loadingTools, set_all_loadingTools, test_cases, show_hidden, show_hidden_details);
                    console.log('JSON Load Aborted');
                }
                else {
                    alert('Could not load diff, please refresh the page and try again.');
                    console.log(e);
                    // eslint-disable-next-line no-undef
                    displayAjaxError(e);
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
 * @param {boolean} show_hidden - should hidden test cases be displayed
 * @param {boolean} show_hidden_details - should hidden details be displayed
 * @param {string} gradeable_id - ID of the gradeable
 * @param {string} who_id - ID of the submitter
 * @param {number} version - submission version
 */
// eslint-disable-next-line no-unused-vars
function loadAllTestcaseOutput(div_name, test_cases, show_hidden, show_hidden_details, gradeable_id, who_id, version = '') {
    const parsed_test_cases = JSON.parse(test_cases);
    const loadingTools = $(`#${div_name}`).find('.loading-tools');
    const expand_all = loadingTools.find('.loading-tools-show').is(':visible');

    // Expand/Collapse all test cases
    parsed_test_cases.forEach((test_case, index)  => {
        const test_case_div_name = `testcase_${index}`;
        const isTestCaseLoadingToolsShowVisible = $(`#tc_${index}`).find('.loading-tools').find('.loading-tools-show').is(':visible');
        const can_view_details = (!test_case.hidden || (show_hidden_details || test_case.release_hidden_details) && show_hidden);

        // Check if test case should be expanded/collapsed
        if (can_view_details && test_case.has_extra_results &&
            ((expand_all && isTestCaseLoadingToolsShowVisible) || (!expand_all && !isTestCaseLoadingToolsShowVisible))) {
            loadTestcaseOutput(test_case_div_name, gradeable_id, who_id, index, div_name, test_cases, version);
        }
    });
}
