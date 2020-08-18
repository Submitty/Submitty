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
        this.update(interactive_area);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        if (!this.dom_pointer) {
            return;
        }

        // Skip saving state if the widget is currently showing a blocked message
        if (!this.dom_pointer.querySelector('.item-options')) {
            return;
        }

        this.state.from_pool = [];
        this.dom_pointer.querySelectorAll('input[type=checkbox]:checked').forEach(checkbox => {
            this.state.from_pool.push(checkbox.value);
        });

        const points_input = this.dom_pointer.querySelector('.points-input');
        points_input.value ? this.state.points = parseInt(points_input.value) : delete this.state.points;

        const label_input = this.dom_pointer.querySelector('.label-input');
        label_input.value ? this.state.item_label = label_input.value : delete this.state.item_label;
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    update(interactive_area) {
        this.commitState();

        interactive_area.innerHTML = this.getUpdatedTemplate();

        const from_pool_div = interactive_area.querySelector('.from-pool-div');

        document.querySelectorAll('.item-name-input').forEach(item_name_input => {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = item_name_input.value;

            if (this.state.from_pool.includes(checkbox.value)) {
                checkbox.checked = true;
            }

            const label_text = document.createElement('span');
            label_text.innerText = item_name_input.value;

            const label = document.createElement('label');
            label.appendChild(checkbox);
            label.appendChild(label_text);

            from_pool_div.appendChild(label);
        });

        const points_input = interactive_area.querySelector('.points-input');
        if (this.state.points) {
            points_input.value = this.state.points;
        }

        const label_input = interactive_area.querySelector('.label-input');
        if (this.state.item_label) {
            label_input.value = this.state.item_label;
        }
    }

    getUpdatedTemplate() {
        return `
        <div class="item-options">
            <label>
                Points: <input class="points-input" type="number" min="1" placeholder="None">
            </label>
            <label>
                Label: <input class="label-input" type="text" placeholder="None">
            </label>
        </div>
        <p>Select the itempool items you would like as a possible option for this item:</p>
        <div class="from-pool-div"></div>`;
    }

    block() {
        this.commitState();
        this.dom_pointer.querySelector('.interactive-container').innerHTML = this.getBlockedTemplate();
    }

    getBlockedTemplate() {
        return `<p>All itempool items <i>must</i> have unique, non-blank item names</p>`;
    }
}
