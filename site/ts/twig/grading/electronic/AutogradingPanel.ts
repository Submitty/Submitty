import { autograding } from '../../../module/grading';

let HIGHEST_VERSION: number;
let GRADEABLE_ID: string;
let USER_ID: string;

// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
    // show all divs whose id starts with testcase_
    const clickable_divs = $("[id^='tc_']");

    for (let i = 0; i < clickable_divs.length; i++) {
        const clickable_div = clickable_divs[i];
        const num = clickable_div.id.split('_')[1];
        const content_div = $(`#testcase_${num}`);
        if (content_div.css('display') == 'none') {
            clickable_div.click();
        }
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
    HIGHEST_VERSION = parseInt(autogradingResultsJQuery.attr('data-highest-version')!);
    GRADEABLE_ID = autogradingResultsJQuery.attr('data-gradeable-id')!;
    USER_ID = autogradingResultsJQuery.attr('data-user-id')!;

    $('#autograding-results-open-all').on('click', () => {
        openAllAutoGrading();
    });
    $('#autograding-results-close-all').on('click', () => {
        closeAllAutoGrading();
    });
    $('#autograding-results-regrade-active').on('click', () => {
        autograding.regrade(1, HIGHEST_VERSION, GRADEABLE_ID, USER_ID);
    });
    $('#autograding-results-regrade-all').on('click', () => {
        autograding.regrade(0, HIGHEST_VERSION, GRADEABLE_ID, USER_ID);
    });
});

