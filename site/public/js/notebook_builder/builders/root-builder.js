/**
 * RootBuilder sits at the root of the notebook builder form and should probably only ever be instantiated a single
 * time.
 */
class RootBuilder extends AbstractBuilder {
    constructor(attachment_div, selector_heading) {
        super(attachment_div, selector_heading);

        this.form_options = new FormOptionsWidget();
        attachment_div.appendChild(this.form_options.render());

        this.load();
    }

    getJSON() {
        const notebook_array = [];
        const itempool_array = [];

        this.reorderable_widgets.forEach(widget => {
            // Ensure we got something back before adding to the notebook_array
            const widget_json = widget.getJSON();
            if (Object.keys(widget_json).length > 0 && widget.constructor.name === 'ItempoolWidget') {
                itempool_array.push(widget_json);
            }
            else if (Object.keys(widget_json).length > 0) {
                notebook_array.push(widget_json);
            }
        });

        builder_data.config.notebook = notebook_array;

        itempool_array.length > 0 ? builder_data.config.item_pool = itempool_array : delete builder_data.config.item_pool;

        return builder_data.config;
    }
}
