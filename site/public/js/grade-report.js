/*exported promptCustomizationUpload */

function promptCustomizationUpload() {
    $('#config-upload').trigger('click');
}

$(() => {
    $('#config-upload').on('change', function(){
        $(this).closest('form').submit();
    });

    $('#toggle-json').on('click', function(){
        $('#customization-json').toggle();
        if ($('#customization-json').is(':visible')) {
            $(this).html('Hide JSON');
        }
        else {
            $(this).html('Show JSON');
        }
    });
});
