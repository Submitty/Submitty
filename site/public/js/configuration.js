$(document).ready(function() {
    updateEmailSeatingOption();

    function updateForumMessage() {
        $("#forum-enabled-message").toggle();
        $("#forum-category-warning").toggle();
    }

    $(document).on("change", "#forum-enabled", updateForumMessage);

    function showEmailSeatingOption() {
        $("#email-seating-assignment").show();
        $("#email-seating-assignment_label").show();
    }
    
    function hideEmailSeatingOption() {
        $("#email-seating-assignment").hide();
        $("#email-seating-assignment-label").hide();
    }

    function updateEmailSeatingOption() {
        if ($("#room-seating-gradeable-id").val()) {
            showEmailSeatingOption();
        } else {
            hideEmailSeatingOption();
        }
    }
    
    $(document).on("change", "#room-seating-gradeable-id", updateEmailSeatingOption);
});
