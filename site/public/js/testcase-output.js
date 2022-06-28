// Map to store the XMLHttpRequest object returned by getJSON when getting the
// output to display in loadTestcaseOutput()
// Key: orig_div_name   Value: XMLHttpRequest object
const output_xhrs = new Map();

/**
 * Collapses Test Case Output.
 *
 * @param {string} div_name - id of div containing the output of the test case
 * @param {string} index - index of test case
 * @param {object} loadingTools - loading tools used by span containing the text that
 *                                correspond to the state of the test case (show, hide, loading)
 */
function CollapseTestcaseOutput(div_name, index, loadingTools) {
    $(`show_char_${index}`).toggle();
    $(`#${div_name}`).empty();
    // eslint-disable-next-line no-undef
    toggleDiv(div_name);

    loadingTools.find('span').hide();
    loadingTools.find('.loading-tools-show').show();
}

/**
 * Expands or Collapses Test Case Output.
 *
 * @global {output_xhrs} : Map (Key - string [orig_div_name]; Value - XMLHttpRequest object) to store the
 *                         XMLHttpRequest object returned by getJSON when getting the output to display
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
    if (output_xhrs.has(orig_div_name)) {
        // Checks if xhr is defined and has not succeeded yet
        // If so, abort loading
        const xhr = output_xhrs.get(orig_div_name);
        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
            output_xhrs.delete(orig_div_name);
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

        loadingTools.find('span').hide();
        loadingTools.find('.loading-tools-in-progress').show();

        output_xhrs.set(orig_div_name, $.getJSON({
            url: url,
            async: true,
            success: function(response) {
                if (response.status !== 'success') {
                    alert(`Error getting file diff: ${response.message}`);
                    return;
                }
                output_xhrs.delete(orig_div_name);

                $(div_name).empty();
                $(div_name).html(response.data);
                // eslint-disable-next-line no-undef
                toggleDiv(orig_div_name);

                loadingTools.find('span').hide();
                loadingTools.find('.loading-tools-hide').show();
                // eslint-disable-next-line no-undef
                enableKeyToClick();
            },
            error: function(e) {
                if (output_xhrs.has(orig_div_name) && output_xhrs.get(orig_div_name).readyState === 0) {
                    output_xhrs.delete(orig_div_name);
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
