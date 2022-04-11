import { getCsrfToken } from '../../../utils/server';

function changeCurrentPeer() {
    $('.edit-peer-components-block').hide();
    $(`#edit-peer-components-form-${$('#edit-peer-select').val()}`).show();
}

function clearPeerMarks(submitter_id: string, gradeable_id: string) {
    const peer_id = $('#edit-peer-select').val();
    const url = buildCourseUrl(['gradeable', gradeable_id, 'grading', 'clear_peer_marks']);
    $.ajax({
        url,
        data: {
            csrf_token: getCsrfToken(),
            peer_id,
            submitter_id,
        },
        type: 'POST',
        success: function () {
            console.log('Successfully deleted peer marks');
            window.location.reload();
        },
        error: function () {
            console.log('Failed to delete');
        },
    });
}

$(() => {
    $('#edit-peer-select').on('change', () => {
        changeCurrentPeer();
    });

    $('.form-edit-peer-components-clear-marks').on('click', (event: JQuery.TriggeredEvent) => {
        const targetElement = $(event.currentTarget!);
        const submitter_id = targetElement.attr('submitter_id')!;
        const gradeable_id = targetElement.attr('gradeable_id')!;

        clearPeerMarks(submitter_id, gradeable_id);
    });
});
