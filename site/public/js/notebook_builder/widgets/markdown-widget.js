/* global Widget, NUM_MARKDOWN, buildUrl, csrfToken, displayErrorMessage */
/* exported MarkdownWidget */

class MarkdownWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'markdown',
            markdown_string: '',
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('Markdown');
        container.classList.add('markdown-widget');

        // Add hidden label for screen reader
        const label = document.createElement('label');
        label.setAttribute('for', `notebook-builder-markdown-${NUM_MARKDOWN}`);
        label.style.display = 'none';

        label.innerText = `Markdown Input #${NUM_MARKDOWN}`;

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(label);

        const markdownArea = document.createElement('div');
        interactive_area.appendChild(markdownArea);
        window.submitty.render(markdownArea, 'component', 'markdownArea', {
            markdownAreaId: `notebook-builder-markdown-${NUM_MARKDOWN}`,
            markdownAreaName: '',
            markdownAreaValue: this.state.markdown_string,
            class: 'markdown-input',
            placeholder: 'Enter text or markdown...',
            previewDivId: `notebook-builder-markdown-preview-${NUM_MARKDOWN}`,
            previewDivName: `notebook-builder-markdown-preview-${NUM_MARKDOWN}`,
            previewButtonId: `notebook-builder-preview-button-${NUM_MARKDOWN}`,
            renderHeader: true,
            minHeight: '100px',
        });

        // eslint-disable-next-line no-global-assign
        NUM_MARKDOWN++;
        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const text_area = this.dom_pointer.querySelector('.markdown-input');
        text_area.value ? this.state.markdown_string = text_area.value : delete this.state.markdown_string;
    }

    getJSON() {
        this.commitState();

        if (this.state) {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }
}
