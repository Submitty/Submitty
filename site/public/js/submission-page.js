/* exported openActionsPopup */
function openActionsPopup(popup_css, element_id) {
    let elem_html = `<link rel="stylesheet" type="text/css" href="${popup_css}" />`;
    elem_html += document.getElementById(element_id).innerHTML;
    const my_window = window.open('', '_blank', 'status=1,width=750,height=500');
    my_window.document.write(elem_html);
    my_window.document.close();
    my_window.focus();
}
