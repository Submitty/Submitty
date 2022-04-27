import { onVerifyComponent } from '../../grading/rubric-dom-callback';

$(() => {
    $(document).on('click', '.grading-component-verify', function(event: JQuery.Event) {
        onVerifyComponent(this);
        event.stopPropagation();
    });
});
