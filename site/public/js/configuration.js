function showEmailSeatingOption() {
    $('#email-seating-assignment').show();
    $('#email-seating-assignment_label').show();
}

function hideEmailSeatingOption() {
    $('#email-seating-assignment').hide();
    $('#email-seating-assignment-label').hide();
}

var selected_seating_gradeable = $('#room-seating-gradeable-id').val();
if(!selected_seating_gradeable) {
    hideEmailSeatingOption();
}

$(document).ready(function() {
    updateForumMessage();

    function updateForumMessage() {
        if ($('#forum-enabled').is(":checked")) {
            $('#forum-enabled-message').show();
        } else {
            $('#forum-enabled-message').hide();
        }
    }

    $(document).on('change', '#forum-enabled', updateForumMessage);
    
    $(document).on('change', '#room-seating-gradeable-id', function(){
            var selected_seating_gradeable = $('#room-seating-gradeable-id').val();

            if(!selected_seating_gradeable){
                hideEmailSeatingOption();
            }
            else{
            showEmailSeatingOption();
        }
    });
});
