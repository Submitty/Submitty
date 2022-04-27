import { onClickCountDown, onClickCountUp, onComponentPageNumberChange, onComponentPointsChange, onComponentTitleChange } from '../../grading/rubric-dom-callback';

$(() => {
    $(document).on('keyup', "input[id^='component-title-']", function() {
        onComponentTitleChange(this);
    });

    $(document).on('change', "input[id^='page-number-']", function() {
        onComponentPageNumberChange(this);
    });
    $(document).on('mouseup', "input[id^='page-number-']", function() {
        onComponentPageNumberChange(this);
    });

    $(document).on('keyup', "textarea[id^='ta-comment-']", function() {
        auto_grow(this);
    });

    $(document).on('keyup', "textarea[id^='student-comment-']", function() {
        auto_grow(this);
    });

    $(document).on('change', "input[id^='yes-link-item-pool-']", function() {
        const componentId = $(this).attr('data-component-id');
        onItemPoolOptionChange(componentId);
    });
    $(document).on('change', "input[id^='no-link-item-pool-']", function() {
        const componentId = $(this).attr('data-component-id');
        onItemPoolOptionChange(componentId);
    });

    $(document).on('change', "input[id^='max-points-']", function() {
        onComponentPointsChange(this);
    });
    $(document).on('mouseup', "input[id^='max-points-']", function() {
        onComponentPointsChange(this);
    });

    $(document).on('change', "input[id^='extra-credit-points-']", function() {
        onComponentPointsChange(this);
    });
    $(document).on('mouseup', "input[id^='extra-credit-points-']", function() {
        onComponentPointsChange(this);
    });

    $(document).on('change', "input[id^='penalty-points-']", function() {
        onComponentPointsChange(this);
    });
    $(document).on('mouseup', "input[id^='penalty-points-']", function() {
        onComponentPointsChange(this);
    });

    $(document).on('click', '.count-up-selector', function() {
        onClickCountUp(this);
    });

    $(document).on('click', '.count-down-selector', function() {
        onClickCountDown(this);
    });
});
