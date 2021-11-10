interface Window{
    file_array: File[][];
    num_submission_boxes: number;
    deleteFiles(Number): void;
    addFile(File, Number, Boolean):void;
    loadPreviousFilesOnDropBoxes():void;
}

const warning_banner = document.getElementById('submission-mode-warning');

function init(){
    document.getElementsByName('submission-type')
        .forEach(radio_btn => radio_btn.addEventListener('change', changeSubmissionMode));

    warning_banner.textContent = '';
}


/**
 * handle switching between normal, submit for student, and bulk upload modes
 */
function changeSubmissionMode(event: Event){
    const element = event.target as HTMLInputElement;

    if (window.file_array[0].length > 0){
        if (!confirm('Switching submission modes will remove all unsubmitted files, are you sure?')){
            return;
        }
    }

    //remove all files in each submission box
    for (let idx = 1; idx <= window.num_submission_boxes; idx++){
        window.deleteFiles(idx);
    }

    switch (element.id){
        case 'radio-normal':
            window.loadPreviousFilesOnDropBoxes();
            warning_banner.textContent = '';
            break;
        case 'radio-student':
            warning_banner.textContent = 'Warning: Submitting files for a student!';
            break;
        case 'radio-bulk':
            warning_banner.textContent = 'Warning: Submitting files for bulk upload!';

    }
}


document.addEventListener('DOMContentLoaded', () => init());
