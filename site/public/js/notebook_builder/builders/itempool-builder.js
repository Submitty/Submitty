class ItempoolBuilder extends AbstractBuilder {
    constructor(attachment_div) {
        super(attachment_div);

        this.selector = new SelectorWidget(this.selector_options, 'Add Cell To Itempool Notebook');

        attachment_div.appendChild(this.reorderable_widgets_div);
        attachment_div.appendChild(this.selector.render());
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
