// Map to store the XMLHttpRequest object returned by getJSON when getting the
// output to display in loadTestcaseOutput()
// Key: div_name ("testcase_" + index)   Value: XMLHttpRequest object
const loading_testcases_xml_http_requests = new Map();

/**
 * Collapses Test Case Output.
 *
 * @param {string} div_name - id of div containing the output of the test case
 * @param {string} index - index of test case
 * @param {object} loadingTools - loading tools used by span containing the text that
 *                                correspond to the state of the test case (show, hide, loading)
 */
function CollapseTestcaseOutput(div_name, index, loadingTools) {
    $(`#show_char_${index}`).toggle();
    $(`#${div_name}`).empty();
    // eslint-disable-next-line no-undef
    toggleDiv(div_name);

    loadingTools.find('.loading-tools-hide').hide();
    loadingTools.find('.loading-tools-in-progress').hide();
    loadingTools.find('.loading-tools-show').show();
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
 * @param {number} version - submission version
 */
// eslint-disable-next-line no-unused-vars
function loadTestcaseOutput(div_name, gradeable_id, who_id, index, version = '') {
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
        CollapseTestcaseOutput(orig_div_name, index, loadingTools);
    }
    // Output is not visible - Expand test case output
    else {
        $(`#show_char_${index}`).toggle();
        // eslint-disable-next-line no-undef
        const url = `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output'])}?who_id=${who_id}&index=${index}&version=${version}`;

        loadingTools.find('.loading-tools-show').hide();
        loadingTools.find('.loading-tools-in-progress').show();

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
                    CollapseTestcaseOutput(orig_div_name, index, loadingTools);
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
function loadAllTestcaseOutput(div_name, test_cases, show_hidden, show_hidden_details, gradeable_id, who_id, version = '')
{
    const parsed_test_cases = JSON.parse(test_cases);

    // Process total div
    const loadingTools = $(`#${div_name}`).find('.loading-tools');

    //window.stop();
    let expand_all = false;
    if (loadingTools.find('.loading-tools-hide').is(':visible')) {
        // All Test Cases should be Collapsed
        expand_all = false;
        loadingTools.find('.loading-tools-hide').hide();
        loadingTools.find('.loading-tools-in-progress').hide();
        loadingTools.find('.loading-tools-show').show();
    }
    else {
        // All Test Cases should be Expanded
        expand_all = true;
        loadingTools.find('.loading-tools-show').hide();
        loadingTools.find('.loading-tools-in-progress').hide();
        loadingTools.find('.loading-tools-hide').show();
    }

    // Expand/Collapse all test cases
    parsed_test_cases.forEach(function callback(test_case, index) {
        const test_case_div_name = `testcase_${index}`;
        const isTestCaseLoadingToolsShowVisible = $(`#tc_${index}`).find('.loading-tools').find('.loading-tools-show').is(':visible');
        const can_view_details = (!test_case.hidden || (show_hidden_details || test_case.release_hidden_details) && show_hidden);

        // Check if test case should be expanded/collapsed
        if (can_view_details && test_case.has_extra_results &&
            ((expand_all && isTestCaseLoadingToolsShowVisible) || (!expand_all && !isTestCaseLoadingToolsShowVisible))) {
            loadTestcaseOutput(test_case_div_name, gradeable_id, who_id, index, version);
        }
    });
}
