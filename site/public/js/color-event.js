let colorEvent;
if (document.createEvent) {
    colorEvent = document.createEvent('HTMLEvents');
    colorEvent.initEvent('colorchange', true, true);
}
else {
    colorEvent = document.createEventObject();
    colorEvent.eventType = 'colorchange';
}

colorEvent.eventName = 'colorchange';
console.log('hello from color -event');
function emitEvent(elem) {
    console.log(elem);
    if (document.createEvent) {
        elem.dispatchEvent(colorEvent);
    }
}

// $('#custom-color').on('change', function () {
//     emitEvent(this);
// });
//
// document.querySelectorAll('.color.key_to_click').forEach((elem) => {
//     elem.addEventListener('click', function () {
//         emitEvent(this);
//         console.log(this);
//     });
// });
