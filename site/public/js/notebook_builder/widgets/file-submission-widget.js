/* global Widget, escapeSpecialChars */
/* exported FileSubmissionWidget */

class FileSubmissionWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'file_submission',
            label: '',
            directory: '',
        };
    }

    render() {
        const container = this.getContainer('File Submission');
        container.classList.add('file-submission-widget');

        const interactive_area = container.querySelector('.interactive-container');
        // eslint-disable-next-line no-unsanitized/property
        interactive_area.innerHTML = this.getFileSubmissionTemplate(this.state.label, this.state.directory);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        if (!this.dom_pointer) {
            return;
        }

        this.state.label = this.dom_pointer.querySelector('.label-input').value;
        this.state.directory = this.dom_pointer.querySelector('.directory-input').value;
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
        this.state.label = this.state.label ?? '';
        this.state.directory = this.state.directory ?? '';
    }

    /**
     * Get the markup for the file submission widget.
     *
     * @param {string} label Value shown in the label box (displayed to the submitter).
     * @param {string} directory Value shown in the directory box (folder the files are stored in).
     * @returns {string}
     */
    getFileSubmissionTemplate(label, directory) {
        return `
        <div class="basic-options">
            <div>
                Label: <input class="label-input" type="text" placeholder="Optional" value="${label ? escapeSpecialChars(label) : ''}">
                <i>Shown to the submitter in the file upload area (e.g. "Drag your <b>Problem 2</b> file(s) here").</i>
            </div>
            <div>
                Directory: <input class="directory-input" type="text" placeholder="Required (e.g. problem_1)" value="${directory ? escapeSpecialChars(directory) : ''}">
                <i>Required.  Folder the uploaded files are stored in.  Use a unique directory for each file submission cell.</i>
            </div>
        </div>`;
    }
}
