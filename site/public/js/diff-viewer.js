$(document).ready(() => {
    const types = ['expected', 'actual'];
    const hoverOn = function(e) {
        const args = $(e.currentTarget).attr('id').split('_');
        console.log(args);
        let id = args[0];
        for (let i = 1; i < args.length-2; i++) {
            id += `_${args[i]}`;
        }
        types.forEach((type) => {
            $(`#${id}_${type}_${args[args.length-1]}`).children().each(function() {
                $(this).addClass('highlight-hover');
            });
        });
    };

    const hoverOff = function(e) {
        const args = $(e.currentTarget).attr('id').split('_');
        let id = args.length-1;
        id = args[0];
        for (let i = 1; i < args.length-2; i++) {
            id += `_${args[i]}`;
        }
        types.forEach((type) => {
            $(`#${id}_${type}_${args[args.length-1]}`).children().each(function() {
                $(this).removeClass('highlight-hover');
            });
        });
    };

    $('.highlight').hover(hoverOn, hoverOff);

    const lineNumbers = document.getElementsByClassName('line_number');

    // Constant, change if font changes from Inconsolata monospace; measuring during runtime is overkill
    const widthPerCharacter = 8;
    const padding = 2;

    // Calculate width of largest number = width per digit * number of digits
    const maxWidth = widthPerCharacter * Math.floor(Math.log10(lineNumbers.length)+1) + padding;

    // Set the width of each line_number element to the max width so they are all aligned
    $('.line_number').css('width', `${maxWidth}px`);
});
