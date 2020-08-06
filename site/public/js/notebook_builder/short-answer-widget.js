class ShortAnswerWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'short_answer',
            filename: 'default.txt'
        };

        this.place_holder_msg = 'You may set an initial value by entering it here.';

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

        const text_area = this.dom_pointer.querySelector('.sa-textarea');

        if (text_area) {
            text_area.value ? this.state.initial_value = text_area.value : delete this.state.initial_value;
            delete this.state.programming_language;
        }
        else  {
            this.state.programming_language = answer_type_selector.value;
            const codebox_value = this.codebox_pointer.getValue();
            codebox_value ? this.state.initial_value = codebox_value : delete this.state.initial_value;
        }
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    /**
     * Attach event handlers to appropriate elements and handle loading textarea / codemirror boxes.
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

            if (answer_type_selector.value === 'Default') {
                const text_area = document.createElement('textarea');
                text_area.classList.add('sa-textarea');
                text_area.placeholder = this.place_holder_msg;
                text_area.value = this.state.initial_value ? this.state.initial_value : '';
                enableTabsInTextArea(text_area);
                initial_value_div.appendChild(text_area);
            }
            else  {
                const codebox_config = {
                    lineNumbers: true,
                    mode: builder_data.codemirror_langauges[answer_type_selector.value],
                    value: this.state.initial_value ? this.state.initial_value : '',
                    theme: 'eclipse',
                    placeholder: this.place_holder_msg
                };

                const height = this.state.rows ? rowsToPixels(this.state.rows) : null;

                this.codebox_pointer = CodeMirror(initial_value_div, codebox_config);
                this.codebox_pointer.setSize(null, height);

                makeCodeMirrorAccessible(this.codebox_pointer);
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
        <p class="accessibility-msg">Press TAB to indent. Press ESC to advance from input area.</p>
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
