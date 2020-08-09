/**
 * RootBuilder sits at the root of the notebook builder form and should probably only ever be instantiated a single
 * time.
 */
class RootBuilder extends AbstractBuilder {
    constructor(attachment_div) {
        super(attachment_div);

        this.form_options = new FormOptionsWidget();
        attachment_div.appendChild(this.form_options.render());

        this.load();
    }
}
