$(document).ready(function() {
    var types = ['expected', 'actual'];
    var hoverOn = function(e) {
        var args = $(e.currentTarget).attr('id').split("_");
        var id = args.length-1;
        types.forEach(function(type) {
            $('#'+args[0]+'_'+type+'_' + args[id]).children().each(function() {
                $(this).addClass('highlight-hover');
            });
        });
    };

    var hoverOff = function(e) {
        var args = $(e.currentTarget).attr('id').split("_");
        var id = args.length-1;
        types.forEach(function(type) {
            $('#'+args[0]+'_'+type+'_' + args[id]).children().each(function() {
                $(this).removeClass('highlight-hover');
            });
        });
    };

    $('.highlight').hover(hoverOn, hoverOff);
});