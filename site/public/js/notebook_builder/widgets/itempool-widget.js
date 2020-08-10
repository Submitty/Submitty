class ItempoolWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.builders = [];
    }

    render() {
        // Setup container
        const container = this.getContainer('Itempool');
        container.classList.add('itempool-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        interactive_area.innerHTML = this.getTemplate();

        const new_group_btn = interactive_area.querySelector('.new-group-btn');
        new_group_btn.onclick = () => {
            const div = document.createElement('div');
            interactive_area.prepend(div);
            const builder = new ItempoolBuilder(div, 'Add Itempool Item');
            this.builders.push(builder);
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
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    getTemplate() {
        return `
        <hr />
        <div class="group-buttons">
            <button class="new-group-btn">Create New Group</button>
            <button class="duplicate-group-btn">Duplicate Previous Group</button>
        </div>
        `;
    }
}
