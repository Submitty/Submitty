class ItempoolWidget extends Widget {
    constructor(builder) {
        super(builder);

        this.dom_pointer;

        this.builder;
    }

    render() {
        // Setup container
        const container = this.getContainer('Itempool');
        container.classList.add('itempool-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        this.builder = new ItempoolBuilder(interactive_area, 'Add Item to Itempool');

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        if (!this.dom_pointer) {
            return;
        }
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }
}
