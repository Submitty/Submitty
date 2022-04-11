function newEditPeerComponentsForm() {
    $('.popup-form').css('display', 'none');
    $('#edit-peer-components-form').css('display', 'block');
    captureTabInModal('edit-peer-components-form');
}

$(() => {
    const peer_info = $('#peer_info');
    if (peer_info.attr('data-has-active-version')) {
        loadTemplates().then(() => {
            return reloadPeerRubric(peer_info.attr('data-gradeable-id') as string, peer_info.attr('data-anon-id') as string);
        });
    }

    $('#peer-edit-marks-btn').on('click', () => {
        newEditPeerComponentsForm();
    });
});
