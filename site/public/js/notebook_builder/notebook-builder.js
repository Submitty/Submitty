// Attach notebook_builder object to the window
window.addEventListener('DOMContentLoaded', (event) => {
    window.notebook_builder = new NotebookBuilder();
    notebook_builder.render();
});

class NotebookBuilder {
    constructor() {
        this.widgets = [new SelectorWidget()];
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
    }

    /**
     * From the list of widgets compile and return a 'notebook' json.
     */
    getJSON() {
        const notebook_array = [];

        // Iterate but dont include the final widget which is the selector widget
        let i;
        for (i = 0; i < this.widgets.length - 1; i++) {

            // Ensure we got something back before adding to the notebook_array
            const widget_json = this.widgets[i].getJSON();
            if (widget_json) {
                notebook_array.push(widget_json);
            }
        }

        return {
            notebook: notebook_array
        };
    }

    /**
     * Add a widget to the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetAdd(widget) {
        this.widgets.splice(this.widgets.length - 1, 0, widget);
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
        if (index === this.widgets.length - 2) {
            return;
        }

        this.widgets.splice(index, 1);
        this.widgets.splice(index + 1, 0, widget);
        this.render();
    }
}
