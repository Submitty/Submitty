/**
 * grader-submission.ts contains logic for special submission modes available to full access graders and instructors
 *
 * Some of the logic is shared between notebook gradeables which does not have the full set of grader submission options
 * like bulk upload, so for certain UI elements their presence needs to be checked like the QR input fields or file dropzones
 */

// TODO: this should be removed as the file upload logic is moved into a module
declare global {
    interface Window {
        file_array: File[][];
        num_submission_boxes: number;
        deleteFiles(part: number): void;
        addFile(file: File, part: number, check_duplicate_zip: boolean): void;
        loadPreviousFilesOnDropBoxes(): void;
        gradeable_id: string;
    }
}

const warning_banner = document.getElementById('submission-mode-warning');

function initialSubmissionMode() {
    const radioBulk = $('#radio-bulk');
    const pdfSubmitButton = $('#pdf-submit-button');
    const warningBanner = $('#warning-banner');

    if (radioBulk.length) {
        if (radioBulk.prop('checked')) {
            if (pdfSubmitButton.length) {
                pdfSubmitButton.show();
            }
            sessionStorage.setItem(`${window.gradeable_id}-submission_mode`, 'bulk-upload');

            if (warningBanner.length && warningBanner.children().length) {
                const message = 'Warning: Submitting files for bulk upload!';
                warningBanner.children().first().text(message);
            }
        }
        else if (pdfSubmitButton.length) {
            pdfSubmitButton.hide();
        }
    }
}

function init() {
    initialSubmissionMode();

    document.getElementsByName('submission-type')
        .forEach((radio_btn) => radio_btn.addEventListener('click', changeSubmissionMode));

    if (warning_banner) {
        warning_banner.textContent = '';
    }

    // load previous setting if any
    const prevSetting = sessionStorage.getItem(`${window.gradeable_id}-submission_mode`);
    if (prevSetting) {
        if (prevSetting === 'normal') {
            document.getElementById('radio-normal')!.click();
        }
        else if (prevSetting === 'for-student') {
            document.getElementById('radio-student')!.click();
        }
        else if (prevSetting === 'bulk-upload') {
            document.getElementById('radio-bulk')!.click();
        }
    }

    const qrPrefixInput = document.getElementById('qr_prefix') as HTMLInputElement;
    const qrSuffixInput = document.getElementById('qr_suffix') as HTMLInputElement;
    const useQRCheckBox = document.getElementById('use-qr') as HTMLInputElement;
    const useScanIdsCheckBox = document.getElementById('use-ocr') as HTMLInputElement | null;

    if (qrPrefixInput) {
        qrPrefixInput.addEventListener('change', (event: Event) => {
            sessionStorage.setItem(`${window.gradeable_id}-qr-prefix`, (event.target as HTMLInputElement).value);
        });
        qrSuffixInput.addEventListener('change', (event: Event) => {
            sessionStorage.setItem(`${window.gradeable_id}-qr-suffix`, (event.target as HTMLInputElement).value);
        });
    }

    if (useQRCheckBox) {
        useQRCheckBox.addEventListener('click', switchBulkUploadOptions);
        if (useScanIdsCheckBox !== null) {
            useScanIdsCheckBox.addEventListener('click', (event: Event) => {
                sessionStorage.setItem(`${window.gradeable_id}-scan_setting`, (event.target as HTMLInputElement).checked.toString());
            });
        }
    }

    const prevQRPrefix = sessionStorage.getItem(`${window.gradeable_id}-qr-prefix`);
    const prevQRSuffix = sessionStorage.getItem(`${window.gradeable_id}-qr-suffix`);
    const prevScanSetting = sessionStorage.getItem(`${window.gradeable_id}-scan_setting`);

    if (prevQRPrefix) {
        qrPrefixInput.value = prevQRPrefix;
    }

    if (prevQRSuffix) {
        qrSuffixInput.value = prevQRSuffix;
    }

    if (prevScanSetting) {
        useScanIdsCheckBox!.checked = prevScanSetting === 'true';
    }
}

/**
 * handle switching between normal, submit for student, and bulk upload modes
 */
function changeSubmissionMode(event: Event) {
    const element = event.target as HTMLInputElement;

    const submitForStudentOpts = document.getElementById('user-id-input');
    const bulkUploadOpts = document.getElementById('pdf-submit-button');
    const qrUploadOpts = document.getElementById('qr-split-opts');
    const numericUploadOpts = document.getElementById('numeric-split-opts');
    const useQRCheckBox = document.getElementById('use-qr') as HTMLInputElement;
    const useScanIdsCheckBox = document.getElementById('use-ocr') as HTMLInputElement | null;
    const scanIdsOpts = document.getElementById('toggle-id-scan');
    const SubmitButton = document.getElementById('submit');

    [submitForStudentOpts, bulkUploadOpts, qrUploadOpts, numericUploadOpts].forEach((element) => element!.style.display = 'none');
    useQRCheckBox.checked = false;
    if (useScanIdsCheckBox !== null) {
        useScanIdsCheckBox.checked = false;
    }

    if (window.file_array[0] && (window.file_array as File[][])[0].length > 0) {
        if (!confirm('Switching submission modes will remove all unsubmitted files, are you sure?')) {
            return;
        }
    }

    // remove all files in each submission box
    for (let idx = 1; idx <= window.num_submission_boxes; idx++) {
        window.deleteFiles(idx);
    }

    const prevBulkSetting = sessionStorage.getItem(`${window.gradeable_id}-bulk_setting`);

    let message = '';
    switch (element.id) {
        case 'radio-normal':
            window.loadPreviousFilesOnDropBoxes();
            sessionStorage.setItem(`${window.gradeable_id}-submission_mode`, 'normal');
            message = '';
            SubmitButton!.innerText = 'Submit';
            break;
        case 'radio-student':
            submitForStudentOpts!.style.display = 'block';
            sessionStorage.setItem(`${window.gradeable_id}-submission_mode`, 'for-student');
            message = 'Warning: Submitting files for a student!';
            SubmitButton!.innerText = 'Submit';
            break;
        case 'radio-bulk':
            bulkUploadOpts!.style.display = 'block';
            SubmitButton!.innerText = 'Bulk Upload';

            sessionStorage.setItem(`${window.gradeable_id}-submission_mode`, 'bulk-upload');
            message = 'Warning: Submitting files for bulk upload!';

            if (prevBulkSetting && prevBulkSetting === 'qr') {
                qrUploadOpts!.style.display = 'inline';
                useQRCheckBox.click();
            }
            else {
                $('#qrUploadOpts').hide();
                numericUploadOpts!.style.display = 'inline';
                sessionStorage.setItem(`${window.gradeable_id}-bulk_setting`, 'numeric');

                if (scanIdsOpts !== null) {
                    scanIdsOpts.style.display = 'none';
                }
            }
    }

    if (warning_banner) {
        if (!warning_banner.hasChildNodes()) {
            const child = warning_banner.appendChild(document.createElement('h2'));
            child.classList.add('warning');
        }

        warning_banner.firstChild!.textContent = message;
    }
}

function switchBulkUploadOptions(event: Event) {
    const element = event.target as HTMLInputElement;
    const scanIdsOpts = document.getElementById('toggle-id-scan');
    const useScanIdsCheckBox = document.getElementById('use-ocr') as HTMLInputElement | null;
    const numericUploadOpts = document.getElementById('numeric-split-opts');
    const qrUploadOpts = document.getElementById('qr-split-opts');

    sessionStorage.setItem(`${window.gradeable_id}-bulk_setting`, element.checked ? 'qr' : 'numeric');

    if (useScanIdsCheckBox !== null) {
        useScanIdsCheckBox.checked = sessionStorage.getItem(`${window.gradeable_id}-scan_setting`) === 'true';
    }
    if (element.checked) {
        qrUploadOpts!.style.display = 'block';
        numericUploadOpts!.style.display = 'none';

        if (scanIdsOpts !== null) {
            scanIdsOpts.style.display = 'inline';
        }
    }
    else {
        qrUploadOpts!.style.display = 'none';
        numericUploadOpts!.style.display = 'inline';

        if (scanIdsOpts !== null) {
            scanIdsOpts.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => init());

// export or import statement required to modify Window interface to global scope
// otherwise TypeScript will assume everything in the file is in the global scope
export { };
