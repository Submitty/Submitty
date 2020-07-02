class FormOptionsWidget extends Widget {
    constructor() {
        super();
    }

    render() {
        const heading_container = this.getHeadingContainer('Form Options');

        const interactive_container = this.getInteractiveContainer();
        interactive_container.innerHTML = this.getFormOptionsTemplate();

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('form-options-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        const save_button = container.querySelector('.save-button');
        save_button.addEventListener('click', event => {
            this.saveButtonAction();
        })

        return container;
    }

    getFormOptionsTemplate() {
        return `
        <input type="button" class="save-button" value="Save">`;
    }

    saveButtonAction() {
        const file = new File([JSON.stringify(notebook_builder.getJSON(), null, 2)], 'config.json', {type: "text/plain"});
        // file.text().then(text => console.log(text));


    }
}