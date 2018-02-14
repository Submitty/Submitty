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

    var lineNumbers = document.getElementsByClassName('line_number');
  
    // Constant, change if font changes from Inconsolata monospace; measuring during runtime is overkill
    var widthPerCharacter = 8;
    var padding = 2;

    // Calculate width of largest number = width per digit * number of digits
    var maxWidth = widthPerCharacter * Math.floor(Math.log10(lineNumbers.length)+1) + padding;
    
    // Set the width of each line_number element to the max width so they are all aligned
    $('.line_number').css('width', maxWidth+'px');

});