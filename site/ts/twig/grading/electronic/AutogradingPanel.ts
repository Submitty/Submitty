import { getCsrfToken } from '../../../utils/server';

// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
    // show all divs whose id starts with testcase_
    const clickable_divs = $("[id^='tc_']");

    for (let i = 0; i < clickable_divs.length; i++) {
        const clickable_div = clickable_divs[i];
        const num = clickable_div.id.split('_')[1];
        const content_div = $(`#testcase_${num}`);
        if (content_div.css('display') === 'none') {
            clickable_div.click();
        }
    }
}

// version_to_grade is null when regrading active version
function regrade(single_regrade: number, version_to_grade: number | null, gradeable_id: string, user_id: string) {
    // if only regrading active version, late day fields left as 0 because they are irrelevant for regrading
    if (single_regrade) {
        window.handleRegrade(version_to_grade, getCsrfToken(), gradeable_id, user_id, true);
    }
    // regrading all versions
    else {
        window.handleRegrade(version_to_grade, getCsrfToken(), gradeable_id, user_id, false, true);
    }
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
    // hide all divs whose id starts with testcase_
    $("[id^='testcase_']").hide();
    $("[id^='details_tc_']").find('span').hide();
    $("[id^='details_tc_']").find('.loading-tools-show').show();
}

$(() => {
    const autogradingResultsJQuery: JQuery = $('#autograding_results');
    const GRADEABLE_ID = autogradingResultsJQuery.attr('data-gradeable-id')!;
    const USER_ID = autogradingResultsJQuery.attr('data-user-id')!;

    $('#autograding-results-open-all').on('click', () => {
        openAllAutoGrading();
    });
    $('#autograding-results-close-all').on('click', () => {
        closeAllAutoGrading();
    });
    $('#autograding-results-regrade-active').on('click', () => {
        regrade(1, null, GRADEABLE_ID, USER_ID);
    });
    $('#autograding-results-regrade-all').on('click', () => {
        regrade(0, null, GRADEABLE_ID, USER_ID);
    });
    $('.autograding-panel-regrade').on('click', function () {
        const version_number = $(this).attr('data-version');
        if (version_number) {
            regrade(1, parseInt(version_number), GRADEABLE_ID, USER_ID);
        }
    });
});
