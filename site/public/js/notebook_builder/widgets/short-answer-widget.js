class ShortAnswerWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'short_answer',
            filename: 'default.txt'
        };

        this.codebox_pointer;
    }

    render() {
        // Setup container
        const container = this.getContainer('Short Answer');
        container.classList.add('short-answer-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        interactive_area.innerHTML = this.getShortAnswerTemplate();

        this.init(interactive_area);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        if (!this.dom_pointer) {
            return;
        }

        this.state.filename = this.dom_pointer.querySelector('.filename-input').value;

        const rows_input = this.dom_pointer.querySelector('.rows-input');
        rows_input.value ? this.state.rows = parseInt(rows_input.value) : delete this.state.rows;

        const answer_type_selector = this.dom_pointer.querySelector('.answer-type');

        answer_type_selector.value !== 'Default' ? this.state.programming_language = answer_type_selector.value : delete this.state.programming_language;
        const codebox_value = this.codebox_pointer.getValue();
        codebox_value ? this.state.initial_value = codebox_value : delete this.state.initial_value;
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    /**
     * Attach event handlers to appropriate elements and handle loading codemirror boxes.
     *
     * @param {HTMLDivElement} interactive_area
     */
    init(interactive_area) {
        interactive_area.querySelector('.filename-input').value = this.state.filename;

        const answer_type_selector = interactive_area.querySelector('.answer-type');
        answer_type_selector.value = this.state.programming_language ? this.state.programming_language : 'Default';

        const rows_selector = interactive_area.querySelector('.rows-input');
        rows_selector.value = this.state.rows ? this.state.rows : '';

        const initial_value_div = interactive_area.querySelector('.initial-value-div');

        const generateInputBox = () => {
            this.commitState();
            initial_value_div.innerHTML = '';

            const msg = document.createElement('p');
            msg.classList.add('initial-value-msg');
            msg.innerText = 'The box below is what the submitter will see to enter their answer in.  You may specify an initial value for them by entering it in the box.';
            initial_value_div.appendChild(msg);

            const codebox_config = {
                value: this.state.initial_value ? this.state.initial_value : '',
                theme: localStorage.theme ? (localStorage.theme === 'light' ? 'eclipse' : 'monokai') : 'eclipse',
                lineWrapping: answer_type_selector.value === 'Default' && this.state.rows
            };

            if (answer_type_selector.value !== 'Default') {
                codebox_config.mode = builder_data.codemirror_langauges[answer_type_selector.value];
                codebox_config.lineNumbers = true;
            }

            if (this.state.rows) {
                const height = this.state.rows ? rowsToPixels(this.state.rows) : null;

                this.codebox_pointer = getLargeCodeMirror(initial_value_div, codebox_config);
                this.codebox_pointer.setSize(null, height);
            }
            else {
                this.codebox_pointer = getSmallCodeMirror(initial_value_div, codebox_config);
            }
        }

        rows_selector.onchange = () => {
            generateInputBox();
        }

        rows_selector.onkeyup = () => {
            generateInputBox()
        }

        answer_type_selector.onchange = () => {
            generateInputBox();
        }

        // Manually fire off a change event to setup the input boxes on initial load
        answer_type_selector.dispatchEvent(new Event('change'));
    }

    /**
     * Get the markup for the short answer widget.
     *
     * @returns {string}
     */
    getShortAnswerTemplate() {
        return `
        <div class="type-options">
            <div>
                Type:
                <select class="answer-type">
                    ${this.getTypeOptions()}
                </select>
                <i>Some languages may be shown multiple times with slightly different names.  They are identical.</i>
            </div>
        </div>
        <div class="basic-options">
            <div>
                Height: <input class="rows-input" type="number" placeholder="Default" min="1">
            </div>
            <div>
                Filename: <input class="filename-input" type="text">
            </div>
        </div>
        <hr />
        <div class="initial-value-div"></div>`;
    }

    /**
     * Generate the <option> markup for all the options available in the language selection drop down.
     *
     * @returns {string}
     */
    getTypeOptions() {
        let all_modes = ['Default'];
        all_modes = all_modes.concat(Object.keys(builder_data.codemirror_langauges));

        let result = '';
        all_modes.forEach(mode => {
            result = result.concat(`<option value="${mode}">${mode}</option>`);
        });

        return result;
    }

    /**
     * Codemirror needs to be refreshed after becoming visible on screen.  This function provides a way for the
     * NotebookBuilder object to access the codemirror object when adding the widget to the screen.
     */
    codeMirrorRefresh() {
        if (this.codebox_pointer) {
            this.codebox_pointer.refresh();
        }
    }
}
