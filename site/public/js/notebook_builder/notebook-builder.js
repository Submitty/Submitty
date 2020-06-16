// Attach notebook_builder object to the window
window.addEventListener('DOMContentLoaded', (event) => {
    window.notebook_builder = new NotebookBuilder()
    notebook_builder.render()
})

class NotebookBuilder {
    constructor() {
        this.widgets = [new SelectorWidget(), new SelectorWidget(), new SelectorWidget()]
    }

    render() {
        // Get a handle on the main div
        const main_div = document.getElementById('notebook-builder')

        // Clear
        main_div.innerHTML = ''

        // Draw widgets
        this.widgets.forEach((widget) => {
            main_div.appendChild(widget.render())
        })
    }
}
