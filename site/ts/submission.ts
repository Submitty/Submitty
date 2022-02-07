// TODO: this should be removed as the file upload logic is moved into a module
declare global {
    interface Window{
        file_array: File[][];
        num_submission_boxes: number;
        deleteFiles(part: Number): void;
        addFile(file: File, part: Number, check_duplicate_zip: Boolean):void;
        loadPreviousFilesOnDropBoxes():void;
    }
}

const warning_banner = document.getElementById('submission-mode-warning');

function init(){
    document.getElementsByName('submission-type')
        .forEach(radio_btn => radio_btn.addEventListener('change', changeSubmissionMode));

    warning_banner!.textContent = '';
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

    let message = '';
    switch (element.id){
        case 'radio-normal':
            window.loadPreviousFilesOnDropBoxes();
            message = '';
            break;
        case 'radio-student':
            message = 'Warning: Submitting files for a student!';
            break;
        case 'radio-bulk':
            message = 'Warning: Submitting files for bulk upload!';

    }

    if (!warning_banner!.hasChildNodes()){
        const child = warning_banner!.appendChild( document.createElement('h2') );
        child.classList.add('warning');
    }

    warning_banner!.firstChild!.textContent = message;
}


document.addEventListener('DOMContentLoaded', () => init());

// export or import statement required to modify Window interface to global scope
// otherwise TypeScript will assume everything in the file is in the global scope
export {

};
