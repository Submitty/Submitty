import { onAddNewMark, onClickComponent } from '../../grading/rubric-dom-callback';

$(() => {
    $(document).on('click', '.component-click', function() {
        onClickComponent(this, false);
    });

    $(document).on('click', '.component-click-edit', function() {
        onClickComponent(this, true);
    });

    $(document).on('click', '.add-new-mark', function () {
        onAddNewMark(this);
    });
});
