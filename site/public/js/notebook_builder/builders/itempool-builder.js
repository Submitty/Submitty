class ItempoolBuilder extends AbstractBuilder {
    constructor(attachment_div, selector_heading) {
        super(attachment_div, selector_heading);
    }

    getJSON() {
        const notebook_array = [];

        this.reorderable_widgets.forEach(widget => {
            // Ensure we got something back before adding to the notebook_array
            const widget_json = widget.getJSON();
            if (widget_json) {
                notebook_array.push(widget_json);
            }
        });

        return notebook_array;
    }
}
