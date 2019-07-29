/**
 * Checks all radio buttons or checkboxes that were previously checked in the recent submission
 *
 * @param mc_field_id The id of the multiple choice fieldset
 */
function setMultipleChoices(mc_field_id)
{
    var prev_checked = $("#" + mc_field_id).attr("data-prev_checked");

    prev_checked = prev_checked.split("\n");

    // For each input inside the fieldset see if its value is inside the prev checked array
    $("#" + mc_field_id + " :input").each(function(index,element) {

        var value = element.getAttribute("value");

        if(prev_checked.includes(value))
        {
            $(element).prop("checked", true);
        }
        else
        {
            $(element).prop("checked", false);
        }
    });
}

/**
 * Sets all checks or radio buttons in a multiple choice question to unchecked
 *
 * @param mc_field_id The id of the multiple choice fieldset
 */
function clearMultipleChoices(mc_field_id)
{
    // For each input inside the fieldset remove the 'checked' attribute
    $("#" + mc_field_id + " :input").each(function(index,element) {

        $(element).prop("checked", false);

    });
}

/**
 * Sets the contents of the codebox
 *
 * @param codebox_id The id of the codebox div
 * @param state May be either clear (set to initial_value) or recent (set to recent submission)
 */
function setCodeBox(codebox_id, state)
{
    // Get initial and previous submission values
    var initial_value = $("#" + codebox_id).attr("data-initial_value");
    var recent_submission = $("#" + codebox_id).attr("data-recent_submission");

    // Get the codebox
    var codebox = $("#" + codebox_id + " .CodeMirror").get(0).CodeMirror;

    if(state == "clear")
    {
        codebox.setValue(initial_value);
    }
    else
    {
        codebox.setValue(recent_submission);
    }
}

$(document).ready(function () {

    // If any button inside the notebook has been clicked then enable the submission button
    $(".notebook button").click(function() {

        // Set global javascript variable to allow submission for notebook
        window.is_notebook = true;

        // Enable submit button
        $("#submit").attr("disabled", false);

    });

    // Register click handler for codebox clear and recent buttons
    $(".codebox-clear-reset").click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        var items = this.id.split("_");
        var index = items[1];
        var action = items[2];
        var button_selector = "#codebox_" + index +  "_";

        setCodeBox("codebox_" + index, action);

        if(action == "clear")
        {
            $(button_selector + "clear_button").attr("disabled", true);
            $(button_selector + "recent_button").attr("disabled", false);
        }
        else
        {
            $(button_selector + "clear_button").attr("disabled", false);
            $(button_selector + "recent_button").attr("disabled", true);
        }
    });

    // Register handler to detect changes inside codeboxes and then enable buttons
    $("div .codebox").keypress(function() {

        // Get index of codebox so we can select appropriate buttons to enable
        var index = this.id.substr(-1);

        // Enable buttons
        $("#codebox_" + index + "_clear_button").attr("disabled", false);
        $("#codebox_" + index + "_recent_button").attr("disabled", false);
    });

    // Register click handler for multiple choice buttons
    $(".mc-clear, .mc-recent").click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        var items = this.id.split("_");
        var index = items[1];
        var action = items[2];
        var field_set_id = "mc_field_" + index;

        if(action == "clear")
        {
            clearMultipleChoices(field_set_id);
            $("#mc_" + index + "_clear_button").attr("disabled", true);
            $("#mc_" + index + "_recent_button").attr("disabled", false);
        }
        else if(action == "recent")
        {
            setMultipleChoices(field_set_id);
            $("#mc_" + index + "_clear_button").attr("disabled", false);
            $("#mc_" + index + "_recent_button").attr("disabled", true);
        }
    });

    // Register change handler to enable buttons when multiple choice inputs change
    $(".mc").change(function() {

        var items = this.id.split("_");
        var index = items[2];

        // Enable recent button
        $("#mc_" + index + "_clear_button").attr("disabled", false);
        $("#mc_" + index + "_recent_button").attr("disabled", false);
    });

    // Setup click events for short answer buttons
    $(".sa-clear-reset").click(function() {

        // Collect the id of the button and split it apart to find out which short answer it is bound to
        // and which action it preforms
        var items = this.id.split("_");

        var index_num = items[2];
        var button_action = items[3];
        var field_id = "#short_answer_" + index_num;

        var data_to_set = "";

        // Collect data from the data-* attribute of the text box
        if(button_action == "clear")
        {
            var data_to_set = $(field_id).attr("data-initial_value");
            $(field_id + "_clear_button").attr("disabled", true);
            $(field_id + "_recent_button").attr("disabled", false);
        }
        else
        {
            var data_to_set = $(field_id).attr("data-recent_submission");
            $(field_id + "_clear_button").attr("disabled", false);
            $(field_id + "_recent_button").attr("disabled", true);
        }

        // Set the data into the textbox
        $(field_id).val(data_to_set);
    });

    // Setup keyup event for short answer boxes
    $(".sa-box").keyup(function() {

        var items = this.id.split("_");

        var index_num = items[2];

        var initial_value = this.getAttribute("data-initial_value");
        var recent_submission = this.getAttribute("data-recent_submission");

        var text_box_id = "#short_answer_" + index_num;
        var clear_button_id = "#short_answer_" + index_num + "_clear_button";
        var recent_button_id = "#short_answer_" + index_num + "_recent_button";

        if($(text_box_id).val() == initial_value)
        {
            $(clear_button_id).attr("disabled", true);
        }
        else
        {
            $(clear_button_id).attr("disabled", false);
        }

        if($(text_box_id).val() == recent_submission)
        {
            $(recent_button_id).attr("disabled", true);
        }
        else
        {
            $(recent_button_id).attr("disabled", false);
        }
    });

});