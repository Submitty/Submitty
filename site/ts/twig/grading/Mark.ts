import { onDeleteMark, onGetMarkStats, onMarkPointsChange, onMarkPublishChange, onRestoreMark, onToggleMark } from '../../grading/rubric-dom-callback';

function changeMarkColor (me: HTMLInputElement, countUp: string): void {
    if (countUp === 'No Credit') {
        if (parseInt(me.value) < 0) {
            me.style.backgroundColor='var(--standard-vibrant-yellow)';
        }
        else {
            me.style.backgroundColor='var(--default-white)';
        }
    }
    else {
        if (parseInt(me.value) > 0) {
            me.style.backgroundColor='var(--standard-vibrant-yellow)';
        }
        else {
            me.style.backgroundColor='var(--default-white)';
        }
    }
}

$(() => {
    $(document).on('click', "div[id^='mark-'][data-toggle-mark='false']", function() {
        onToggleMark(this);
    });

    $(document).on('click', '.mark-see-who-got', function(event: JQuery.Event) {
        onGetMarkStats(this);
        event.stopPropagation();
    });

    $(document).on('change', "input[id^='mark-editor-']", function() {
        onMarkPointsChange(this);
        changeMarkColor(this as HTMLInputElement, $(this).attr('overall') as string);
    });
    $(document).on('mouseup', "input[id^='mark-editor-']", function() {
        onMarkPointsChange(this);
        changeMarkColor(this as HTMLInputElement, $(this).attr('overall') as string);
    });
    let overallTitle = '';
    $("input[id^='mark-editor-']").each(function() {
        const points = parseInt($(this).parent().attr('data-points') as string);
        if ($(this).attr('data-first-mark')) {
            overallTitle = $(this).attr('data-mark-title') as string;
        }
        $(this).attr('overall', overallTitle);
        if ($(this).attr('overall') === 'Full Credit'){
            if (points > 0) {
                $(this)[0].style.backgroundColor='var(--standard-vibrant-yellow)';
            }
            else {
                $(this)[0].style.backgroundColor='var(--default-white)';
            }
        }
        else {
            if (points < 0) {
                $(this)[0].style.backgroundColor='var(--standard-vibrant-yellow)';
            }
            else {
                $(this)[0].style.backgroundColor='var(--default-white)';
            }
        }
    });

    $(document).on('keyup', '.mark-edit-textarea', function() {
        auto_grow(this);
    });

    $(document).on('change', "input[id^='show-all-marks-']", function() {
        onMarkPublishChange(this);
    });

    $(document).on('click', '.delete-mark-container', function() {
        onDeleteMark(this);
    });

    $(document).on('click', '.restore-mark-container', function() {
        onRestoreMark(this);
    });
});
