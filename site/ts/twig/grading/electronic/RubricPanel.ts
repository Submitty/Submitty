import { reloadGradingRubric } from '../../../grading/rubric';
import { onToggleEditMode, onVerifyAll } from '../../../grading/rubric-dom-callback';

$(() => {
    $('#grading_rubric #verify-all').on('click', () => {
        onVerifyAll();
    });

    $('#grading_rubric #silent-edit-id').on('click', () => {
        updateCookies();
    });

    $('#grading_rubric #edit-mode-enabled').on('click', () => {
        onToggleEditMode();
    });

    if ($('#grading_rubric #grading-box').attr('data-is-ta-grading') && $('#grading_rubric #grading-box').attr('data-has-active-version')) {
        const gradeableId = $('#grading_rubric #grading-box').attr('data-gradeable-id') as string;
        const anonId = $('#grading_rubric #grading-box').attr('data-anon-id') as string;

        loadTemplates().then(() => {
            return reloadGradingRubric(gradeableId, anonId);
        });
    }
});
