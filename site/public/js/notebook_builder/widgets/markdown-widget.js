class MarkdownWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'markdown',
            markdown_string: ''
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('Markdown');
        container.classList.add('markdown-widget');

        const markdownArea = $(Twig.twig({
            ref: "MarkdownArea"
        }).render ({
            markdown_area_id : `notebook-builder-markdown-${NUM_MARKDOWN}`,
            markdown_area_name : '',
            markdown_area_value : this.state.markdown_string,
            placeholder : 'Enter text or markdown...',
            preview_div_id : `notebook-builder-markdown-preview-${NUM_MARKDOWN}`,
            preview_div_name : `notebook-builder-markdown-preview-${NUM_MARKDOWN}`,
            preview_button_id : `notebook-builder-preview-button-${NUM_MARKDOWN}`,
            onclick : `previewNotebookBuilderMarkdown.call(this, ${NUM_MARKDOWN})`,
            render_buttons : true,
            min_height : "100px",
        }));

        //Add hidden label for screen reader
        const label = document.createElement('label');
        label.setAttribute('for', `notebook-builder-markdown-${NUM_MARKDOWN}`);
        label.style.display = 'none';
        label.innerHTML =  `Markdown Input #${NUM_MARKDOWN}`;
        NUM_MARKDOWN++;

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(label);
        $(interactive_area).append(markdownArea);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const text_area = this.dom_pointer.querySelector('.markdown-input');
        this.state.markdown_string = text_area.value;
    }

    getJSON() {
        this.commitState();

        if (this.state.markdown_string !== '') {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }
}

Twig.twig({
    id: "MarkdownArea",
    href: "/templates/misc/MarkdownArea.twig",
    async: true
});

function previewNotebookBuilderMarkdown(markdown_num) {
    const markdown_area = $(`#notebook-builder-markdown-${markdown_num}`);
    const preview_element = $(`#notebook-builder-markdown-preview-${markdown_num}`);
    const preview_button = $(this);
    const url = buildCourseUrl(['notebook_builder', 'preview']);
    const markdown_content = markdown_area.val();


    console.log(markdown_num, markdown_area, preview_element, preview_button, url, markdown_content);

    previewMarkdown(markdown_area, preview_element, preview_button, url, { content: markdown_content });
}