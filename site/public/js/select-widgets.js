/* exported registerSelect2Widget */

function registerSelect2Widget(widget_id, belongingModalId = 'off') {
    // Inspired by https://stackoverflow.com/a/30021059
    const options = {
        tags: true,
        theme: 'bootstrap-5',
        createTag: (params) => ({
            id: params.term,
            text: params.term,
        }),
    };

    if (belongingModalId !== 'off') {
        options.dropdownParent = $(`#${belongingModalId}`);
    }

    $(`#${widget_id}`).select2(options);
}
