class AbstractBuilder {
    constructor(attachment_div) {
        this.reorderable_widgets = [];
        this.reorderable_widgets_div = document.createElement('div');

        this.selector_options = ['Multiple Choice', 'Markdown', 'Short Answer', 'Image'];

        attachment_div.onclick = event => {
            if (event.target.getAttribute('type') === 'button') {
                switch (event.target.value) {
                    case 'Multiple Choice':
                        this.widgetAdd(new MultipleChoiceWidget());
                        break;
                    case 'Markdown':
                        this.widgetAdd(new MarkdownWidget());
                        break;
                    case 'Short Answer':
                        this.widgetAdd(new ShortAnswerWidget());
                        break;
                    case 'Image':
                        this.widgetAdd(new ImageWidget());
                        break;
                    case 'Itempool':
                        this.widgetAdd(new ItempoolWidget());
                        break;
                    case 'Item':
                        break;
                    case 'Up':
                        this.widgetUp(event.target.widget)
                        break;
                    case 'Down':
                        this.widgetDown(event.target.widget)
                        break;
                    case 'Remove':
                        this.widgetRemove(event.target.widget)
                        break;
                    default:
                        break;
                }
            }

            event.stopPropagation();
        }
    }

    getJSON()  { throw 'Implement this method in the child class.'; }

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
