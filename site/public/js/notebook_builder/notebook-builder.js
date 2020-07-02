// Attach notebook_builder object to the window
window.addEventListener('DOMContentLoaded', (event) => {
    window.notebook_builder = new NotebookBuilder();
    notebook_builder.render();
});

class NotebookBuilder {
    constructor() {
        this.widgets = [];
        this.selector = new SelectorWidget();
        this.form_options = new FormOptionsWidget();
    }

    /**
     * Display all widgets the notebook builder is keeping track of.
     */
    render() {
        // Get a handle on the main div
        const main_div = document.getElementById('notebook-builder');

        // Clear
        main_div.innerHTML = '';

        // Draw widgets
        this.widgets.forEach(widget => {
            main_div.appendChild(widget.render());
        });

        main_div.appendChild(this.selector.render());
        main_div.appendChild(this.form_options.render());
    }

    /**
     * From the list of widgets compile and return a 'notebook' json.
     */
    getJSON() {
        const notebook_array = [];

        this.widgets.forEach(widget => {
            // Ensure we got something back before adding to the notebook_array
            const widget_json = widget.getJSON();
            if (widget_json) {
                notebook_array.push(widget_json);
            }
        });

        return {
            notebook: notebook_array,
            testcases: []
        };
    }

    /**
     * Add a widget to the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetAdd(widget) {
        this.widgets.push(widget);
        this.render();
    }

    /**
     * Remove a widget from the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetRemove(widget) {
        const index = this.widgets.indexOf(widget);
        this.widgets.splice(index, 1);
        this.render();
    }

    /**
     * Move a widget up one position in the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetUp(widget) {
        const index = this.widgets.indexOf(widget);

        // If index is 0 then do nothing
        if (index === 0) {
            return;
        }

        this.widgets.splice(index, 1);
        this.widgets.splice(index - 1, 0, widget);
        this.render();
    }

    /**
     * Move a widget down one position in the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetDown(widget) {
        const index = this.widgets.indexOf(widget);

        // If widget is already at the end of the form then do nothing
        if (index === this.widgets.length - 1) {
            return;
        }

        this.widgets.splice(index, 1);
        this.widgets.splice(index + 1, 0, widget);
        this.render();
    }
}
