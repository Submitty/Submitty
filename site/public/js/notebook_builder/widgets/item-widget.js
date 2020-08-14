class ItemWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {}
    }

    render() {
        // Setup container
        const container = this.getContainer('Item');
        container.classList.add('item-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        if (!this.dom_pointer) {
            return;
        }
    }

    getJSON() {
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    update() {
        console.log('Update method called!');
    }

    block() {
        console.log('Block method called!');
    }
}
