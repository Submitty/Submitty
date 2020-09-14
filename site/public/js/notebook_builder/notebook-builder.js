// Attach notebook_builder object to the window
window.addEventListener('DOMContentLoaded', (event) => {
    window.notebook_builder = new NotebookBuilder();
});

class NotebookBuilder {
    constructor() {
        // Setup object properties
        this.reorderable_widgets = [];
        this.selector = new SelectorWidget();
        this.form_options = new FormOptionsWidget();

        // Setup fixed position widgets
        const main_div = document.getElementById('notebook-builder');
        main_div.appendChild(this.selector.render());
        main_div.appendChild(this.form_options.render());

        // Load and render reorderable notebook widgets
        this.load();
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
                    widget = new MultipleChoiceWidget();
                    break;
                case 'markdown':
                    widget = new MarkdownWidget();
                    break;
                case 'short_answer':
                    widget = new ShortAnswerWidget();
                    break;
                case 'image':
                    widget = new ImageWidget();
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

        const widgets_div = document.getElementById('reorderable-widgets');
        widgets_div.appendChild(widget.render());

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
