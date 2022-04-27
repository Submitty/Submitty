import { reloadGradingRubric } from '../../../grading/rubric';

$(() => {
    if ($('#grading-box-peer').attr('data-has-active-version') === 'true') {
        const gradeableId = $('#grading-box-peer').attr('data-gradeable-id')!;
        const anonId = $('#grading-box-peer').attr('data-anon-id')!;
        loadTemplates().then(() => {
            return reloadGradingRubric(gradeableId, anonId);
        });
    }
});
