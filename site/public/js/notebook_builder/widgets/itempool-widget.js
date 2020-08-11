class ItempoolWidget extends Widget {
    constructor(builder) {
        super(builder);

        this.dom_pointer;

        this.builder;
    }

    render() {
        // Setup container
        const container = this.getContainer('Itempool Notebook');
        container.classList.add('itempool-widget');

        // Add item_name field input field to heading area
        const item_name_div = document.createElement('div');
        item_name_div.innerHTML = this.getTemplate();

        const heading_area = container.querySelector('.heading-container');
        heading_area.appendChild(item_name_div)

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        this.builder = new ItempoolBuilder(interactive_area, 'Add Cell to Itempool Notebook');

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
        const notebook = this.builder.getJSON();

        if (item_name && notebook.length > 0) {
            cell.item_name = item_name;
            cell.notebook = notebook;
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
