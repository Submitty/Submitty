class ShortAnswerWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'short_answer',
            filename: 'default.txt'
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('Short Answer');
        container.classList.add('short-answer-widget');

        const rows = this.state.rows ? this.state.rows : 1;
        const size_selector = document.createElement('div');
        size_selector.innerHTML = this.getSizeSelectorTemplate(rows);

        const filename = document.createElement('input');
        filename.classList.add('filename-input');
        filename.setAttribute('type', 'text');
        filename.value = this.state.filename;

        const filename_label = document.createElement('label');
        filename_label.innerText = 'Filename: ';
        filename_label.appendChild(filename);

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(size_selector);
        interactive_area.appendChild(filename_label);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const rows = this.dom_pointer.querySelector('.rows-input');
        const rows_int = parseInt(rows.value);
        rows_int === 1 ? delete this.state.rows : this.state.rows = rows_int;

        const filename = this.dom_pointer.querySelector('.filename-input');
        this.state.filename = filename.value;
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    /**
     * Get the html template for the size selector part of the short answer widget.
     *
     * @param {int} rows The value which should be display in the input box.
     * @returns {string}
     */
    getSizeSelectorTemplate(rows) {
        return `<span>Height in lines: </span><input class="rows-input" type="text" value="${rows}">`;
    }
}
