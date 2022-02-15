import { getCsrfToken } from "../utils/server";

var HIGHEST_VERSION: number;
var GRADEABLE_ID: string;
var USER_ID: string;

function regrade(single_regrade: number) {
    //if only regrading active version, late day fields left as 0 because they are irrelevant for regrading
    if (single_regrade) {
        window.handleRegrade(HIGHEST_VERSION, getCsrfToken(), GRADEABLE_ID, USER_ID, true)
    }
    //regrading all versions
    else {
        window.handleRegrade(HIGHEST_VERSION, getCsrfToken(), GRADEABLE_ID, USER_ID, false, true)
    }
}

// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
    // show all divs whose id starts with testcase_
    let clickable_divs = $("[id^='tc_']");

    for (let i = 0; i < clickable_divs.length; i++) {
        let clickable_div = clickable_divs[i];
        let num = clickable_div.id.split("_")[1];
        let content_div = $('#testcase_' + num);
        if (content_div.css("display") == "none") {
            clickable_div.click();
        }
    }
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
    // hide all divs whose id starts with testcase_
    $("[id^='testcase_']").hide();
    $("[id^='details_tc_']").find("span").hide();
    $("[id^='details_tc_']").find(".loading-tools-show").show();
}

export function init(highest_version: number, gradeable_id: string, user_id: string) {
    HIGHEST_VERSION = highest_version;
    GRADEABLE_ID = gradeable_id;
    USER_ID = user_id;

    $("#autograding-results-open-all").on("click", () => {
        openAllAutoGrading();
    });
    $("#autograding-results-close-all").on("click", () => {
        closeAllAutoGrading();
    });
    $("#autograding-results-regrade-active").on("click", () => {
        regrade(1);
    });
    $("#autograding-results-regrade-all").on("click", () => {
        regrade(0);
    })
}