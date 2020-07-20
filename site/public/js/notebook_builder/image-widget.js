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
        interactive_area.innerHTML = this.getImageTemplate(this.state.height, this.state.width, this.state.alt_text);

        if (this.state.image) {
            const file_selector = interactive_area.querySelector('input[type=file]');
            file_selector.style.display = 'none';

            this.loadExistingImage(builder_data.images[this.state.image], interactive_area.querySelector('.image-container'))
        }
        else {
            this.captureNewImage(interactive_area);
        }

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

        if (this.state.image) {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }

    getImageTemplate(height, width, alt_text) {
        return `
        <div class="image-container"></div>
        <input type="file" accept="image/*">
        <div class="image-options">
            <div class="image-col-small">
                <label>
                    Height:
                    <input class="height-input" type="number" placeholder="Default" min="1" value="${height ? height : ''}">
                </label>
            </div>
            <div class="image-col-small">
                <label>
                    Width:
                    <input class="width-input" type="number" placeholder="Default" min="1" value="${width ? width : ''}">
                </label>
            </div>
            <div class="image-col-large">
                <label>
                    Alternate Text:
                    <textarea class="alt-text-input" placeholder="For accessibility, provide a short description of this image's contents.">${alt_text ? alt_text : ''}</textarea>
                </label>
            </div>
        </div>`
    }

    captureNewImage(interactive_area) {
        const reader = new FileReader();
        const image = new Image();
        const file_selector = interactive_area.querySelector('input[type=file]');
        const image_container = interactive_area.querySelector('.image-container');

        this.attachImageOnLoadHandler(image, image_container);

        reader.onload = event => {
            image.src = event.target.result;
            image_container.prepend(image);
        }

        file_selector.onchange = event => {
            const file = event.target.files[0];

            if (file) {
                this.state.image = file.name;
                reader.readAsDataURL(file);
                file_selector.style.display = 'none';

                const file_name_msg = document.createElement('p');
                file_name_msg.innerText = `Filename: ${file.name}`;

                image_container.appendChild(file_name_msg);
            }
        }
    }

    loadExistingImage(image_data_url, image_container) {
        const image = new Image();
        this.attachImageOnLoadHandler(image, image_container);
        image.src = image_data_url;
        image_container.prepend(image);
    }

    attachImageOnLoadHandler(image, image_container) {
        image.onload = () => {
            const msg = document.createElement('p');
            msg.innerText = `Dimensions (width x height)\nNative: ${image.naturalWidth} x ${image.naturalHeight}\nShown at: ${image.width} x ${image.height}`;
            image_container.appendChild(msg);
        }
    }
}
