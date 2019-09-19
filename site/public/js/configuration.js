$(document).ready(function() {
    updateForumMessage();
    updateEmailSeatingOption();

    function updateForumMessage() {
        updateForumCategoryWarning();
        if ($("#forum-enabled").is(":checked")) {
            $("#forum-enabled-message").show();
        } else {
            $("#forum-enabled-message").hide();
        }
    }

    function updateForumCategoryWarning() {
        if ($("#forum-category-warning").length) {
            if ($("#forum-enabled").is(":checked")) {
                $("#forum-category-warning").show();
            } else {
                $("#forum-category-warning").hide();
            }
        }
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
