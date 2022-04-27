import { onCustomMarkChange, onToggleCustomMark } from '../../grading/rubric-dom-callback';

function changeExtraColor(ref: string, me: HTMLInputElement): void {
    if (ref === 'Full Credit' && parseInt(me.value) > 0){
        me.style.backgroundColor = 'var(--standard-vibrant-yellow)';
    }
    else if (ref === 'No Credit' && parseInt(me.value) < 0){
        me.style.backgroundColor = 'var(--standard-vibrant-yellow)';
    }
    else {
        me.style.backgroundColor = 'var(--default-white)';
    }
}

function increment(me: HTMLInputElement): void {
    const componentId = parseInt($(me).parent().attr('data-component-id') as string);
    const precision = parseFloat($(me).parent().attr('data-precision') as string);
    $(`#extra-mark-${componentId}`).val(+$(`#extra-mark-${componentId}`).val()! + precision);
    onCustomMarkChange(me);
}

function decrement(me: HTMLInputElement): void {
    const componentId = parseInt($(me).parent().attr('data-component-id') as string);
    const precision = parseFloat($(me).parent().attr('data-precision') as string);
    $(`#extra-mark-${componentId}`).val(+$(`#extra-mark-${componentId}`).val()! - precision);
    onCustomMarkChange(me);
}

$(() => {
    $(document).on('click', '.mark-selector-grading-component', function() {
        onToggleCustomMark(this);
    });

    $(document).on('change', "input[id^='extra-mark-']", function() {
        const title = $(this).attr('data-mark-title') as string;
        onCustomMarkChange(this);
        changeExtraColor(title, this as HTMLInputElement);
    });

    $(document).on('click', '.custom-mark-arrow-up', function() {
        increment(this as HTMLInputElement);
    });
    $(document).on('click', '.custom-mark-arrow-down', function() {
        decrement(this as HTMLInputElement);
    });

    $("input[id^='extra-mark-']").each(function() {
        if ($(this).attr('data-mark-title') === 'Full Credit') {
            if ($(this).val() as number > 0){
                $(this)[0].style.backgroundColor='var(--standard-vibrant-yellow)';
            }
        }
        else if ($(this).attr('data-mark-title') === 'No Credit') {
            if ($(this).val() as number < 0){
                $(this)[0].style.backgroundColor='var(--standard-vibrant-yellow)';
            }
        }
    });

    $(document).on('change', '.mark-note-custom', function() {
        onCustomMarkChange(this);
    });

    $(document).on('keyup', '.mark-note-custom', function() {
        auto_grow(this);
    });
});
