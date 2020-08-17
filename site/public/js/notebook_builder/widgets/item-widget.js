class ItemWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            'type': 'item',
            'from_pool': []
        }
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
        this.commitState();
        this.dom_pointer.querySelector('.interactive-container').innerHTML = `<p>Updated</p>`;
        console.log('Update method called!');
    }

    block() {
        this.commitState();
        this.dom_pointer.querySelector('.interactive-container').innerHTML = this.getBlockedTemplate();
        console.log('Block method called!');
    }

    getBlockedTemplate() {
        return `<p>All itempool items <i>must</i> have unique, non-blank item names</p>`;
    }
}
