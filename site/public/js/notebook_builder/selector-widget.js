class SelectorWidget extends AbstractWidget {
    constructor() {
        super()
        this.options = ['Markdown', 'Multiple Choice', 'Short Answer']
    }

    render() {
        // Collect nodes
        const container = document.createElement('div')
        const drop_down = document.createElement('select')
        const add_button = document.createElement('button')

        // Attach options to select node
        this.options.forEach((option_string) => {
            const option_node = document.createElement('option')
            option_node.innerText = option_string
            drop_down.appendChild(option_node)
        })

        // Setup button text
        add_button.innerText = 'Add'

        // Add classes
        container.classList.add('notebook-builder-widget')
        container.classList.add('selector-widget')

        // Place dropdown and button in container
        container.appendChild(drop_down)
        container.appendChild(add_button)

        return container
    }
}