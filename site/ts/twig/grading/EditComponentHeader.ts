import { onDeleteComponent } from '../../grading/rubric-dom-callback';

$(() => {
    $(document).on('click', '.reorder-component-container', (event: JQuery.Event) => {
        event.stopPropagation();
    });

    $(document).on('click', '.delete-component-container', function(event: JQuery.Event) {
        onDeleteComponent(this);
        event.stopPropagation();
    });
});
