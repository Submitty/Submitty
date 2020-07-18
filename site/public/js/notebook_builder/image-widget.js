class ImageWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'image',
        };
    }

    render() {
        const container = this.getContainer('Image');
        container.classList.add('image-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        interactive_area.innerHTML = this.getImageTemplate();
        this.imageSelectedAction(interactive_area);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const height_input = this.dom_pointer.querySelector('.height-input');
        height_input.value ? this.state.height = parseInt(height_input.value) : delete this.state.height;

        const width_input = this.dom_pointer.querySelector('.width-input');
        width_input.value ? this.state.width = parseInt(width_input.value) : delete this.state.width;

        const alt_text_input = this.dom_pointer.querySelector('.alt-text-input');
        alt_text_input.value ? this.state.alt_text = alt_text_input.value : delete this.state.alt_text;
    }

    getJSON() {
        this.commitState();
        return this.state;
    }

    load(data) {
        this.state = data;
    }

    getImageTemplate() {
        return `
        <div class="image-container"></div>
        <input type="file" accept="image/*">
        <div class="image-options">
            <div class="image-col-small">
                <label>
                    Height:
                    <input class="height-input" type="number" placeholder="Default" min="1">
                </label>
            </div>
            <div class="image-col-small">
                <label>
                    Width:
                    <input class="width-input" type="number" placeholder="Default" min="1">
                </label>
            </div>
            <div class="image-col-large">
                <label>
                    Alternate Text:
                    <textarea class="alt-text-input" placeholder="For accessibility, provide a short description of this image's contents."></textarea>
                </label>
            </div>
        </div>`
    }

    imageSelectedAction(interactive_area) {
        const reader = new FileReader();
        const file_selector = interactive_area.querySelector('input[type=file]');
        const image_container = interactive_area.querySelector('.image-container');

        reader.onload = event => {
            const image = document.createElement('img');
            image.src = event.target.result;

            image_container.innerHTML = '';
            image_container.appendChild(image);
        }

        file_selector.addEventListener('change', event => {
            const f = event.target.files[0];
            uploadFile(f, builder_data.g_id, 'input');
            reader.readAsDataURL(f);
        });
    }


}

