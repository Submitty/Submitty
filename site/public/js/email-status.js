function onExpandButtonClick(button){
    const expandTarget = $(button).data('target');
    console.log(expandTarget);
    if($(expandTarget).is(":visible")){
        $(expandTarget).hide();
    } else {
        $(expandTarget).show();
    }
}