class ItempoolWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.itempool_builder;
    }

    render() {
        // Setup container
        const container = this.getContainer('Itempool');
        container.classList.add('itempool-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        this.itempool_builder = new ItempoolBuilder(interactive_area);

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
