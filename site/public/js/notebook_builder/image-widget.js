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

    commitState() {}

    getJSON() {
        this.commitState();
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
                    <input type="number" placeholder="Default">
                </label>
            </div>
            <div class="image-col-small">
                <label>
                    Width:
                    <input type="number" placeholder="Default">
                </label>
            </div>
            <div class="image-col-large">
                <label>
                    Description:
                    <textarea placeholder="For accessibility, provide a short description of this image's contents."></textarea>
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
            reader.readAsDataURL(f);
        });
    }
}

