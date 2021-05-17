/* global attachCollapsiblePanel */
$(document).ready(() => {
    // Attach the collapsible panel on details-table
    attachCollapsiblePanel('#details-table .details-info-header', 951, 'panel-head-active');

    // Creating and adding style for the psuedo selector in the details-table
    const style = document.createElement('style');
    let content = '';
    // loop over the head row of `details-table`
    $('#details-table thead tr th').each(function (idx) {
        if (idx) {
            // the content to be added is inside this data attr
            content = $(this).data('col-title');
            style.innerHTML += `
              #details-table td:nth-of-type(${idx + 1}):before {
                  content: "${content}";
              }
            `;
        }
    });
    document.head.appendChild(style);
});

