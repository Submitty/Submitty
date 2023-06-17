class ItempoolWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.builder;

        this.state = {}
    }

    render() {
        // Setup container
        const container = this.getContainer('Itempool Item');
        container.classList.add('itempool-widget');

        // Modify default heading area
        const item_name_div = document.createElement('div');
        item_name_div.innerHTML = this.getTemplate();

        const heading_area = container.querySelector('.heading-container');
        heading_area.innerHTML = '';
        heading_area.appendChild(item_name_div)

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        this.builder = new ItempoolBuilder(interactive_area);

        if (Object.keys(this.state).length > 0) {
            item_name_div.querySelector('.item-name-input').value = this.state.item_name;
            this.builder.load(this.state);
        }

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        if (!this.dom_pointer) {
            return;
        }
    }

    getJSON() {
        const cell = {};

        const item_name = this.dom_pointer.querySelector('.item-name-input').value;

        if (item_name) {
            cell.item_name = item_name;
            cell.notebook = this.builder.getJSON();
        }

        return cell;
    }

    load(data) {
        this.state = data;
    }

    getTemplate() {
        return `
        <label>
            Item name: <input type="text" class="item-name-input">
        </label>`;
    }
}
