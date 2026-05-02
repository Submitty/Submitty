/* global Widget*/
/* exported FileSubmissionWidget */

class FileSubmissionWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'file_submission',
            label: 'Submit a file',
            directory: 'Submission',
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('File Submission');
        container.classList.add('file-submission-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        // eslint-disable-next-line no-unsanitized/property
        interactive_area.innerHTML = this.getFileSubmissionTemplate();

        this.init(interactive_area);

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
    }

    /**
     * Attach initial values to handle loading of label and directory.
     *
     * @param {HTMLDivElement} interactive_area
     */
    init(interactive_area) {
        interactive_area.querySelector('.label-input').value = this.state.label;
        interactive_area.querySelector('.directory-input').value = this.state.directory;
    }

    /**
     * Get the markup for the file submission widget.
     *
     * @returns {string}
     */
    getFileSubmissionTemplate() {
        return `
        <div class="basic-options">
            <div>
                Label: <input class="label-input" type="text">
            </div>
            <div>
                Directory: <input class="directory-input" type="text">
            </div>
        </div>`;
    }
}
