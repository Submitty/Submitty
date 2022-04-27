import { onCancelComponent, onCancelEditRubricComponent } from '../../grading/rubric-dom-callback';

$(() => {
    $(document).on('click', '.save-tools-cancel', function(event: JQuery.Event) {
        const cancelOnClick = $(this).attr('data-cancel-on-click');
        if (cancelOnClick === 'edit-rubric-component') {
            onCancelEditRubricComponent(this);
        }
        else if (cancelOnClick === 'cancel-component') {
            onCancelComponent(this);
        }
        else {
            console.error('SavingTools.twig - unknown callback for cancelling on click.');
        }
        event.stopPropagation();
    });
});
