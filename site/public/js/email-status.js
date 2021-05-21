function onExpandButtonClick(button){
    const expandTarget = $(button).data('target');
    if ($(expandTarget).is(':visible')) {
        $(expandTarget).hide();
    }
    else {
        $(expandTarget).show();
    }
}
