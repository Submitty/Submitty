function setMultipleChoices(mc_field_id)
{
    var parts = mc_field_id.split("_");
    var id = parts[2];
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

$(document).ready(function () {

    $(".mc_recent").click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        var items = this.id.split("_");
        var index = items[1];
        var field_set_id = "mc_field_" + index;

        setMultipleChoices(field_set_id);
        $(this).attr("disabled", true);
    });

    $(".mc").change(function() {

        var items = this.id.split("_");
        var index = items[2];

        // Enable reset button
        $("#mc_" + index + "_recent_button").attr("disabled", false);
    });

    // Setup click events for short answer buttons
    $(".sa_clear_reset").click(function() {

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
        }
        else
        {
            var data_to_set = $(field_id).attr("data-recent_submission");
        }

        // Set the data into the textbox
        $(field_id).val(data_to_set);

        // Set button states
        if(button_action == "clear")
        {
            $(field_id + "_clear_button").attr("disabled", true);
            $(field_id + "_recent_button").attr("disabled", false);
        }
        else
        {
            $(field_id + "_clear_button").attr("disabled", false);
            $(field_id + "_recent_button").attr("disabled", true);
        }
    });

    // Setup keyup event for short answer boxes
    $(".sa_box").keyup(function() {

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