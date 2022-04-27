import { reloadInstructorEditRubric } from '../../../../grading/rubric';

$(() => {
    $('#point_precision_id').on('change', () => {
        onPrecisionChange();
    });

    $('#yes_pdf_page').on('change', () => {
        updatePdfPageSettings();
    });
    $('#no_pdf_page').on('change', () => {
        updatePdfPageSettings();
    });

    $('#no_pdf_page_student').on('change', () => {
        updatePdfPageSettings();
    });
    $('#yes_pdf_page_student').on('change', () => {
        updatePdfPageSettings();
    });

    if ($('#no_custom_marks').is(':checked')) {
        $('#custom_marks_warning').show();
    }
    loadTemplates().then(() => {
        reloadInstructorEditRubric($('#g_id').val() as string, !!($('#gradeable_rubric').attr('data-notebook')!), JSON.parse($('#gradeable_rubric').attr('data-itempool-options')!)).then( () => {
            $('body').on('DOMSubtreeModified', '.component-container', () => {
                const yesPeer = ($('.peer-component-container').length == 0) ? false : true;
                if (yesPeer) {
                    $('#page_4_nav').show();
                    $('#page_4_nav').removeClass('hidden-no-peers');
                }
                else {
                    $('#page_4_nav').hide();
                    $('#page_4_nav').addClass('hidden-no-peers');
                }
            });
        });
    });
    //toggle warning message if custom marks have already been used and the settings are changed
    $('#no_custom_marks').on('click', () => {
        $('#custom_marks_warning').show();
    });
    $('#yes_custom_marks').on('click', () => {
        $('#custom_marks_warning').hide();
    });
});
