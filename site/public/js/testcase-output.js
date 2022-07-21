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
 * Expands or Collapses Test Case Output.
 *
 * @global {loading_testcases_xml_http_requests} : Map (Key - string [orig_div_name]; Value - XMLHttpRequest object)
 *                         to store the XMLHttpRequest object returned by getJSON when getting the output to display
 *
 * @param {string} div_name - id of div containing the output of the test case
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


/*
 * Function to toggle all of the given test cases, resulting in all to be expanded or collapsed.  
 * 
 * @param total_div_name {string} name of div id encapsulating loading tools
 * @param test_cases {array of test cases} array of all test cases for gradable that should be affected by toggle
 * 
 * Requires total_div_name to contain 3 spans: 
 *  loading-tools-show
 *  loading-tools-hide
 *  loading-tools-in-progress
*/
function loadAllTestcaseOutput(total_div_name, test_cases, show_hidden, show_hidden_details, gradeable_id, who_id, version = '')
{
    const parsed_test_cases = JSON.parse(test_cases);

    // Process total div
    let loadingTools = $("#" + total_div_name).find(".loading-tools");

    //window.stop();
    var expand_all = false;
    if(loadingTools.find(".loading-tools-hide").is(":visible"))         // Collapse Test Cases
    {
        expand_all = false;

        loadingTools.find("span").hide();
        loadingTools.find(".loading-tools-show").show();
    }

    else //(loadingTools.find(".loading-tools-show").is(":visible"))    // Expand Test Cases
    {
        expand_all = true;

        loadingTools.find("span").hide();
        loadingTools.find(".loading-tools-in-progress").show();
    }


    // Expand/Collapse all test cases
    parsed_test_cases.forEach(function callback(test_case, index) {

        // var can_view = (!test_case.hidden || show_hidden);
        var can_view_details = (!test_case.hidden || (show_hidden_details || test_case.release_hidden_details) && show_hidden)

        var div_name = "testcase_" + index;

        // Check if test case should be expanded/collapsed
        if (can_view_details && test_case.has_extra_results)
        {
            if($("#" + div_name).is(":visible") != expand_all)
            {
                loadTestcaseOutput(div_name, gradeable_id, who_id, index, version);
            } 
            else{
                console.log("Failed on #" + div_name + " | expected to" + (expand_all ? "expand" : "hide") + " all");
            }
        }
    });
    
    // If loading is completed, set Collapse All text to be visible
    if(loadingTools.find(".loading-tools-in-progress").is(":visible"))
    {
        // WAIT UNTIL ALL test cases are loaded ... OR force shut down (don't even care about it being fully loaded)

        loadingTools.find("span").hide();
        loadingTools.find(".loading-tools-hide").show();
    }
}
