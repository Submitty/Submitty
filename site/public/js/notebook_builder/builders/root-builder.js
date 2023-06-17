/**
 * RootBuilder sits at the root of the notebook builder form and should probably only ever be instantiated a single
 * time.
 */
class RootBuilder extends AbstractBuilder {
    constructor(attachment_div) {
        super(attachment_div);

        const all_options = this.selector_options.concat(['Item']);
        this.main_selector = new SelectorWidget(all_options,  'Add New Notebook Cell');

        this.itempool_selector = new SelectorWidget(['Itempool Item'], 'Add New Itempool Item')
        this.itempool_selector_elem = this.itempool_selector.render();
        this.itempool_selector_elem.classList.add('itempool-selector');

        this.form_options = new FormOptionsWidget();

        attachment_div.appendChild(this.reorderable_widgets_div);
        attachment_div.appendChild(this.main_selector.render());
        attachment_div.appendChild(this.itempool_div);
        attachment_div.appendChild(this.itempool_selector_elem);
        attachment_div.appendChild(this.form_options.render());

        this.load(builder_data.config);
    }

    getJSON() {
        const notebook_array = [];
        this.collectValidJsons(this.reorderable_widgets, notebook_array);

        const itempool_array = [];
        this.collectValidJsons(this.itempool_widgets, itempool_array);

        notebook_array.length > 0 ? builder_data.config.notebook = notebook_array : delete builder_data.config.notebook;
        itempool_array.length > 0 ? builder_data.config.item_pool = itempool_array : delete builder_data.config.item_pool;

        return builder_data.config;
    }
}
