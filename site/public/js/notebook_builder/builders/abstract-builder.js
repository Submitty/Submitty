class AbstractBuilder {
    constructor(attachment_div) {
        this.attachment_div = attachment_div;

        this.reorderable_widgets = [];
        this.itempool_widgets = [];

        this.reorderable_widgets_div = document.createElement('div');
        this.itempool_div = document.createElement('div');

        this.selector_options = ['Multiple Choice', 'Markdown', 'Short Answer', 'Image'];

        // Handle many of the different button clicks that might occur within the notebook builder
        this.attachment_div.onclick = event => {
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
                    case 'Itempool Item':
                        this.widgetAdd(new ItempoolWidget());
                        this.itempoolItemChangeAction();
                        break;
                    case 'Item':
                        this.widgetAdd(new ItemWidget());
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

                event.stopPropagation();
            }
        }

        // Handle updating notebook item widgets when itempool item widgets might have changed
        this.attachment_div.addEventListener('focusout', event => {
            if (event.target.classList.contains('item-name-input')) {
                this.itempoolItemChangeAction();
            }
        });
    }

    getJSON()  { throw 'Implement this method in the child class.'; }

    load(data) {
        if (data.item_pool) {
            data.item_pool.forEach(item => {
                let widget = new ItempoolWidget();
                widget.load(item);
                this.widgetAdd(widget);
            });
        }

        data.notebook.forEach(cell => {
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
                case 'item':
                    widget = new ItemWidget();
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

    itempoolItemChangeAction() {
        const operation = this.allValidItemNames() ? 'update' : 'block';

        this.reorderable_widgets.forEach(widget => {
            if (widget.constructor.name === 'ItemWidget') {
                if (operation === 'update') {
                    const interactive_area = widget.dom_pointer.querySelector('.interactive-container');
                    widget.update(interactive_area);
                }
                else {
                    widget.block();
                }
            }
        });
    }

    /**
     * Determine if all itempool item widgets contain a non-blank and unique 'item_name'.
     *
     * @returns {Boolean} True if all are valid, false otherwise.
     */
    allValidItemNames() {
        const used_item_names = new Set();
        let all_valid = true;

        const item_name_inputs = this.attachment_div.querySelectorAll('.item-name-input');
        item_name_inputs.forEach(item_name_input => {
            if (item_name_input.value === '' || used_item_names.has(item_name_input.value)) {
                all_valid = false;
            }

            used_item_names.add(item_name_input.value);
        });

        return all_valid;
    }

    /**
     * From the array of passed in widgets, get their json object, and if valid add it to the array of valid_jsons.
     *
     * @param {Widget[]} widgets An array of widgets, probably either this.reorderable_widgets or this.itempool_widgets.
     * @param {Object[]} valid_jsons An array which will be filled and data returned by reference.
     */
    collectValidJsons(widgets, valid_jsons) {
        widgets.forEach(widget => {
            const widget_json = widget.getJSON();

            if (Object.keys(widget_json).length > 0) {
                valid_jsons.push(widget_json);
            }
        });
    }

    /**
     * Add a widget to the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetAdd(widget) {
        let widgets_array;
        let widgets_div;

        if (widget.constructor.name === 'ItempoolWidget') {
            widgets_array = this.itempool_widgets;
            widgets_div = this.itempool_div;
        }
        else {
            widgets_array = this.reorderable_widgets;
            widgets_div = this.reorderable_widgets_div;
        }

        widgets_array.push(widget);
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
        const is_itempool_widget = widget.constructor.name === 'ItempoolWidget';
        const widgets_array = is_itempool_widget ? this.itempool_widgets : this.reorderable_widgets;

        widget.dom_pointer.remove();

        const index = widgets_array.indexOf(widget);
        widgets_array.splice(index, 1);

        if (is_itempool_widget) {
            this.itempoolItemChangeAction();
        }
    }

    /**
     * Move a widget up one position in the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetUp(widget) {
        const is_itempool_widget = widget.constructor.name === 'ItempoolWidget';
        const widgets_array = is_itempool_widget ? this.itempool_widgets : this.reorderable_widgets;

        const index = widgets_array.indexOf(widget);

        // If index is 0 then do nothing
        if (index === 0) {
            return;
        }

        widgets_array.splice(index, 1);
        widgets_array.splice(index - 1, 0, widget);

        const elem = widget.dom_pointer;
        elem.parentElement.insertBefore(elem, elem.previousElementSibling);

        if (is_itempool_widget) {
            this.itempoolItemChangeAction();
        }
    }

    /**
     * Move a widget down one position in the notebook builder form.
     *
     * @param {Widget} widget
     */
    widgetDown(widget) {
        const is_itempool_widget = widget.constructor.name === 'ItempoolWidget';
        const widgets_array = is_itempool_widget ? this.itempool_widgets : this.reorderable_widgets;

        const index = widgets_array.indexOf(widget);

        // If widget is already at the end of the form then do nothing
        if (index === widgets_array - 1) {
            return;
        }

        widgets_array.splice(index, 1);
        widgets_array.splice(index + 1, 0, widget);

        const elem = widget.dom_pointer;
        elem.parentElement.insertBefore(elem, elem.nextElementSibling.nextElementSibling);

        if (is_itempool_widget) {
            this.itempoolItemChangeAction();
        }
    }
}
