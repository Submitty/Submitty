// Attach notebook_builder object to the window
window.addEventListener('DOMContentLoaded', (event) => {
    window.notebook_builder = new NotebookBuilder();
    notebook_builder.render();
})

class NotebookBuilder {
    constructor() {
        this.widgets = [new SelectorWidget()];
    }

    render() {
        // Get a handle on the main div
        const main_div = document.getElementById('notebook-builder');

        // Clear
        main_div.innerHTML = '';

        // Draw widgets
        this.widgets.forEach((widget) => {
            main_div.appendChild(widget.render());
        });
    }

    getJSON() {
        const notebook_array = [];

        // Iterate but dont include the final widget which is the selector widget
        let i;
        for (i = 0; i < this.widgets.length - 1; i++) {
            notebook_array.push(this.widgets[i].getJSON());
        }

        return {
            notebook: notebook_array
        };
    }

    /**
     * Add a widget to the notebook builder form
     *
     * @param widget
     */
    addWidget(widget) {
        this.widgets.splice(this.widgets.length - 1, 0, widget);
        this.render();
    }

    /**
     * Remove the passed widget from the notebook builder form
     *
     * @param widget
     */
    removeWidget(widget) {
        const index = this.widgets.indexOf(widget);
        this.widgets.splice(index, 1);
        this.render();
    }

    /**
     * Move the passed in widget up one position in the notebook builder form
     *
     * @param widget
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
     * Move the passed in widget down one position in the notebook builder form
     *
     * @param widget
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
