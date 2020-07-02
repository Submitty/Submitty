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

        const params = new URLSearchParams(window.location.search.substr(1));

        const url = buildCourseUrl(['notebook_builder', 'save_new']);

        const form_data = new FormData();
        form_data.append('config_upload', file, 'config.json');
        form_data.append('csrf_token', csrf_token);
        form_data.append('g_id', params.get('g_id'));

        fetch(url, {method: 'POST', body: form_data})
        .then(response => response.json())
        .then(result => {
            const interactive_container = document.querySelector('.form-options-widget .interactive-container');
            let msg;
            if (result.status === 'success') {
                const edit_gradable_url = `${buildCourseUrl(['gradeable', params.get('g_id'), 'update'])}?nav_tab=1`;
                msg = `Your new configuration was successfully uploaded. To use it, select config '${result.data.config_name}' from the drop down on the <a href="${edit_gradable_url}">Edit Gradeable</a> page.`;
            }
            else {
                msg = result.message;
            }
            const span = document.createElement('span');
            span.innerHTML = msg;
            interactive_container.appendChild(span);
        })
        .catch(error => {
            console.error('Error:', error)
        });
    }
}