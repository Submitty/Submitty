class ItempoolBuilder extends AbstractBuilder {
    constructor(attachment_div) {
        super(attachment_div);

        this.selector = new SelectorWidget(this.selector_options, 'Add Cell To Itempool Item');

        attachment_div.appendChild(this.reorderable_widgets_div);
        attachment_div.appendChild(this.selector.render());
    }

    getJSON() {
        const notebook_array = [];
        this.collectValidJsons(this.reorderable_widgets, notebook_array);

        return notebook_array;
    }
}
