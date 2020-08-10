class AbstractBuilder {
    constructor(attachment_div, selector_heading) {
        this.reorderable_widgets = [];
        this.reorderable_widgets_div = document.createElement('div');

        this.selector = new SelectorWidget(this, selector_heading);

        attachment_div.appendChild(this.reorderable_widgets_div);
        attachment_div.appendChild(this.selector.render());
    }

    /**
     * From the list of widgets compile and return a 'notebook' json.
     */
    getJSON() {
        const notebook_array = [];

        this.reorderable_widgets.forEach(widget => {
            // Ensure we got something back before adding to the notebook_array
            const widget_json = widget.getJSON();
            if (widget_json) {
                notebook_array.push(widget_json);
            }
        });

        builder_data.config.notebook = notebook_array;
        return builder_data.config;
    }

    load() {
        builder_data.config.notebook.forEach(cell => {
            let widget;

            switch (cell.type) {
                case 'multiple_choice':
                    widget = new MultipleChoiceWidget(this);
                    break;
                case 'markdown':
                    widget = new MarkdownWidget(this);
                    break;
                case 'short_answer':
                    widget = new ShortAnswerWidget(this);
                    break;
                case 'image':
                    widget = new ImageWidget(this);
                    break;
                default:
                    break;
            }

            if (widget) {
                widget.load(cell);
                this.widgetAdd(widget);
            }
        });
    }

    /**
     * Add a widget to the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetAdd(widget) {
        this.reorderable_widgets.push(widget);

        this.reorderable_widgets_div.appendChild(widget.render());

        // Codemirror boxes inside the ShortAnswerWidget require special handling
        // Codeboxes won't render correctly unless refreshed AFTER appended to the dom
        if (widget.constructor.name === 'ShortAnswerWidget') {
            widget.codeMirrorRefresh();
        }
    }

    /**
     * Remove a widget from the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetRemove(widget) {
        widget.dom_pointer.remove();

        const index = this.reorderable_widgets.indexOf(widget);
        this.reorderable_widgets.splice(index, 1);
    }

    /**
     * Move a widget up one position in the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetUp(widget) {
        const index = this.reorderable_widgets.indexOf(widget);

        // If index is 0 then do nothing
        if (index === 0) {
            return;
        }

        this.reorderable_widgets.splice(index, 1);
        this.reorderable_widgets.splice(index - 1, 0, widget);

        const elem = widget.dom_pointer;
        elem.parentElement.insertBefore(elem, elem.previousElementSibling);
    }

    /**
     * Move a widget down one position in the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetDown(widget) {
        const index = this.reorderable_widgets.indexOf(widget);

        // If widget is already at the end of the form then do nothing
        if (index === this.reorderable_widgets.length - 1) {
            return;
        }

        this.reorderable_widgets.splice(index, 1);
        this.reorderable_widgets.splice(index + 1, 0, widget);

        const elem = widget.dom_pointer;
        elem.parentElement.insertBefore(elem, elem.nextElementSibling.nextElementSibling);
    }
}
