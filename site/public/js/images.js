$(document).ready(function(){

$('.no-image-available').each(function(i, obj) {
    // create a base64 encoded PNG
    var data = new Identicon(randomString(32), 420).toString();
    // write to a data URI
    $(obj).replaceWith('<img width=210 height=210 src="data:image/png;base64,' + data + '">');
});

function randomString(len, charSet) {
    charSet = charSet || '0123456789abcdefghijklmn0123456789opqrstuvwxyz0123456789';
    var randomString = '';
    for (var i = 0; i < len; i++) {
        var randomPoz = Math.floor(Math.random() * charSet.length);
        randomString += charSet.substring(randomPoz,randomPoz+1);
    }
    return randomString;
}

});