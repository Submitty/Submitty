/* exported registerSelect2Widgets */

function registerSelect2Widget(widget_id, belongingModalId = "off") {
	// Inspired by https://stackoverflow.com/a/30021059
	options = {
        tags: true,
        theme: "bootstrap-5",
        createTag: (params) => ({
            id: params.term,
            text: params.term
        })
    };

    if (belongingModalId !== "off") {
    	options.dropdownParent = $(`#${belongingModalId}`);
    }

    $(`#${widget_id}`).select2(options);
}