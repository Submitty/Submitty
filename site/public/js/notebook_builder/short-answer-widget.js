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
        interactive_area.querySelector('.filename-input').value = this.state.filename;

        this.setupAnswerTypeHandlers(interactive_area);

        if (this.state.rows) {
            interactive_area.querySelector('.rows-input').value = this.state.rows;
        }

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

    setupAnswerTypeHandlers(interactive_area) {
        const answer_type_selector = interactive_area.querySelector('.answer-type');
        const initial_value_div = interactive_area.querySelector('.initial-value-div');

        answer_type_selector.onchange = () => {
            this.commitState();
            initial_value_div.innerHTML = '';

            if (answer_type_selector.value === 'Default') {
                const text_area = document.createElement('textarea');
                text_area.classList.add('sa-textarea');
                text_area.placeholder = this.place_holder_msg;
                text_area.value = this.state.initial_value ? this.state.initial_value : '';
                initial_value_div.appendChild(text_area);
            }
            else  {
                const codebox_config = {
                    lineNumbers: true,
                    mode: answer_type_selector.value,
                    value: this.state.initial_value ? this.state.initial_value : ''
                };

                this.codebox_pointer = CodeMirror(initial_value_div, codebox_config);
            }
        }

        answer_type_selector.value = this.state.programming_language ? this.state.programming_language : 'Default';
        answer_type_selector.dispatchEvent(new Event('change'));
    }

    getShortAnswerTemplate() {
        return `
        <div class="type-options">
            <div>
                Type:
                <select class="answer-type">
                    ${this.getTypeOptions()}
                </select>
                <span class="italics">Note: Some languages may listed several times with different names.  They are identical.</span>
            </div>
        </div>
        <div class="basic-options">
            <div>
                Height: <input class="rows-input" type="number" placeholder="Default">
            </div>
            <div>
                Filename: <input class="filename-input" type="text">
            </div>
        </div>
        <div class="initial-value-div"></div>`;
    }

    getTypeOptions() {
        let all_modes = ['Default'];
        all_modes = all_modes.concat(builder_data.codemirror_langauges);

        let result = '';
        all_modes.forEach(mode => {
            result = result.concat(`<option value="${mode}">${mode}</option>`);
        });

        return result;
    }

    codeMirrorRefresh() {
        if (this.codebox_pointer) {
            this.codebox_pointer.refresh();
        }
    }
}
