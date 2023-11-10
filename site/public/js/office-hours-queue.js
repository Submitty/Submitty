//this helps update the frontend when the page refreshes because without this the sort icon would reset and the sort state would not
/* eslint prefer-arrow-callback: [ "error", { "allowNamedFunctions": true } ] */
document.addEventListener('DOMContentLoaded', function() {
    let sortIndicator = document.getElementById('sortIndicator');
    let sortState = localStorage.getItem('sortIndicator');

    if (sortState === null) {
        sortState = 'off';
        localStorage.setItem('sortIndicator', sortState);
    }

    if (sortState === 'up') {
        sortIndicator.innerHTML = '<i class="fas fa-sort-up"></i>';
    }
    else if (sortState === 'off') {
        sortIndicator.innerHTML = '<i class="fa-solid fa-sort"></i>';
    }
    else if (sortState === 'down') {
        sortIndicator.innerHTML = '<i class="fas fa-sort-down"></i>';
    }
    adjustRows();
});

// eslint-disable-next-line no-unused-vars
/* eslint prefer-arrow-callback: [ "error", { "allowNamedFunctions": true } ] */
/* exported toggleSort */
function toggleSort(column) {
    const sortIndicator = document.getElementById('sortIndicator');
    if (column === 'HelpStartedBy') {
        if (localStorage.getItem('sortIndicator') === 'off') {
            localStorage.setItem('sortIndicator', 'up');
            sortIndicator.innerHTML = '<i class="fas fa-sort-up"></i>';
        }
        else if (localStorage.getItem('sortIndicator') === 'up') {
            localStorage.setItem('sortIndicator', 'down');
            sortIndicator.innerHTML = '<i class="fas fa-sort-down"></i>';
        }
        else if (localStorage.getItem('sortIndicator') === 'down') {
            localStorage.setItem('sortIndicator', 'off');
            sortIndicator.innerHTML = '<i class="fa-solid fa-sort"></i>';
        }
    }
    adjustRows();
}
/* eslint prefer-arrow-callback: [ "error", { "allowNamedFunctions": true } ] */
function adjustRows() {
    const rows = $('.queue_history_row').toArray();
    rows.sort((a, b) => {
        if (localStorage.getItem('sortIndicator') === 'up') {
            if ($(a).find('.helpStarted').text()>$(b).find('.helpStarted').text()) {
                return -1;
            }
            else {
                return 1;
            }
        }
        else if (localStorage.getItem('sortIndicator') === 'down') {
            if ($(a).find('.helpStarted').text()>$(b).find('.helpStarted').text()) {
                return 1;
            }
            else {
                return -1;
            }
        }
        else {
            if (parseInt($(a).find('.numberCount').text())<parseInt($(b).find('.numberCount').text())) {
                return -1;
            }
            else {
                return 1;
            }
        }
    });
    $('#queueHistoryTable').empty();
    for (let i=0; i < rows.length; i++) {
        $('#queueHistoryTable').append($(rows[i]));
    }
}

