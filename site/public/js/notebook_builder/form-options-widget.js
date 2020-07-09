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

    /**
     * Get the template used in the form options widget
     *
     * @returns {string}
     */
    getFormOptionsTemplate() {
        return `
        <div class="buttons">
            <input type="button" class="save-button" value="Save">
        </div>
        <div class="status"></div>`;
    }

    /**
     * Pack up all the configuration data from the GUI into a nice config.json and submit it to the server
     */
    saveButtonAction() {
        const file = new File([JSON.stringify(notebook_builder.getJSON(), null, 2)], 'config.json', {type: "text/plain"});
        const g_id = window.location.pathname.split('/').pop();
        const url = buildCourseUrl(['notebook_builder', 'save']);

        const form_data = new FormData();
        form_data.append('config_upload', file, 'config.json');
        form_data.append('csrf_token', csrfToken);
        form_data.append('g_id', g_id);

        const makeRequest = async () => {
            const status_div = document.querySelector('.form-options-widget .status');
            status_div.innerHTML = 'Saving...';

            const response = await fetch(url, {method: 'POST', body: form_data});
            const result = await response.json();

            status_div.innerHTML = '';

            if (result.status === 'success') {
                const gradeable_submission_url = buildCourseUrl(['gradeable', g_id]);
                const edit_gradeable_url = buildCourseUrl(['gradeable', g_id, 'update']);

                const submission_msg = document.createElement('p');
                submission_msg.innerHTML = `Your gradeable is being installed.  To view it visit the <a href="${gradeable_submission_url}">submission page</a>.`;

                const edit_msg = document.createElement('p');
                edit_msg.innerHTML = `To make other changes to the gradeable configuration visit the <a href="${edit_gradeable_url}">edit gradeable page</a>.`;

                status_div.appendChild(submission_msg);
                status_div.appendChild(edit_msg);
            }
            else {
                const msg = document.createElement('p');
                msg.innerHTML = result.message;
                status_div.appendChild(msg);
            }
        };

        makeRequest().catch(err => console.error(err));
    }
}
