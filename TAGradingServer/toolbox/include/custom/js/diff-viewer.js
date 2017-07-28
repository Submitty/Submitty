$(document).ready(function() {
    var types = ['expected', 'actual'];
    var hoverOn = function(e) {
        var args = $(e.currentTarget).attr('id').split("_");
        var id = args[0];
        for (var i = 1; i < args.length-2; i++) {
            id += "_" + args[i];
        }
        types.forEach(function(type) {
            $('#'+id+'_'+type+'_' + args[args.length-1]).children().each(function() {
                $(this).addClass('highlight-hover');
            });
        });
    };

    var hoverOff = function(e) {
        var args = $(e.currentTarget).attr('id').split("_");
        var id = args.length-1;
        id = args[0];
        for (var i = 1; i < args.length-2; i++) {
            id += "_" + args[i];
        }
        types.forEach(function(type) {
            $('#'+id+'_'+type+'_' + args[args.length-1]).children().each(function() {
                $(this).removeClass('highlight-hover');
            });
        });
    };

    $('.highlight').hover(hoverOn, hoverOff);
});