class ShortAnswerWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'short_answer',
            filename: 'default.txt'
        };

        this.place_holder_msg = 'You may prepopulate the input by entering that data here.';
    }

    render() {
        // Setup container
        const container = this.getContainer('Short Answer');
        container.classList.add('short-answer-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        interactive_area.innerHTML = this.getShortAnswerTemplate('');
        interactive_area.querySelector('.prepopulate-area').appendChild(this.getTextArea(''));

        interactive_area.querySelector('.filename-input').value = this.state.filename;

        this.dom_pointer = container;
        return container;
    }

    commitState() {

    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    getShortAnswerTemplate(rows) {
        return `
        <div class="type-options">
            <div>
                Answer Type:
                <select>
                    <option>Text</option>
                    <option>Code</option>
                </select>
            </div>
            <div class="language-select-div">
                <select>
                    ${Object.keys(CodeMirror.modes).forEach(mode => `<option>${mode}</option>`)}
                </select>
            </div>
        </div>
        <div class="basic-options">
            <div>
                Height: <input class="rows-input" type="number" value="${rows}" placeholder="Default">
            </div>
            <div>
                Filename: <input class="filename-input" type="text">
            </div>
        </div>
        <div class="prepopulate-area"></div>`;
    }

    getTextArea(value) {
        const text_area = document.createElement('textarea');
        text_area.placeholder = this.place_holder_msg;
        text_area.value = value;
        return text_area;
    }

    getCodeBox(value) {

    }

    /**
     * Get the html template for the size selector part of the short answer widget.
     *
     * @param {int} rows The value which should be display in the input box.
     * @returns {string}
     */
    getSizeSelectorTemplate(rows) {
        return `<span>Height in lines: </span><input class="rows-input" type="number" value="${rows}" min="1">`;
    }
}
