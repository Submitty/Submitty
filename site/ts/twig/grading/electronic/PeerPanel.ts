import { reloadPeerRubric } from '../../../grading/rubric';

$(() => {
    $('#edit-peer-marks-btn').on('click', () => {
        newEditPeerComponentsForm();
    });

    if ($('#peer-grading-box').attr('data-has-active-version') === 'true') {
        const gradeableId = $('#peer-grading-box').attr('data-gradeable-id')!;
        const anonId = $('#peer-grading-box').attr('data-anon-id')!;
        loadTemplates().then(() => {
            return reloadPeerRubric(gradeableId, anonId);
        });
    }
});
