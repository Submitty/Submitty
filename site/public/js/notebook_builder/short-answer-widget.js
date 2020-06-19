class ShortAnswerWidget extends Widget {
    constructor() {
        super();

        this.size_selector = document.createElement('form');
        this.size_selector.innerHTML = this.getSizeSelectorTemplate();
    }

    render() {
        // Setup container
        const container = this.getContainer('Short Answer');
        container.classList.add('short-answer-widget');

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(this.size_selector);

        return container;
    }

    getJSON() {
        const result = {};

        result.type = 'short_answer';

        if (this.getSizeSelection() === 'large') {
            result.rows = 5;
        }

        return result;
    }

    getSizeSelection() {
        return this.size_selector.querySelector('input[name="size"]:checked').value;
    }

    getSizeSelectorTemplate() {
        return `
        <p>What size should the input box be?</p>
        <div>
            <input type="radio" id="small" value="small" name="size" checked>
            <label for="small">Small</label>
        </div>
        <div>
            <input type="radio" id="large" value="large" name="size">
            <label for="large">Large</label>
        </div>`;
    }
}
