//this helps update the frontend when the page refreshes because without this the sort icon would reset and the sort state would not
/* eslint prefer-arrow-callback: [ "error", { "allowNamedFunctions": true } ] */
document.addEventListener('DOMContentLoaded', () => {
    const sortIndicator = document.getElementById('sort-indicator');
    let sortState = localStorage.getItem('sort-indicator');

    if (sortState === null) {
        sortState = 'off';
        localStorage.setItem('sort-indicator', sortState);
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

/* exported toggleSort */
function toggleSort(column) {
    const sortIndicator = document.getElementById('sort-indicator');
    if (column === 'HelpStartedBy') {
        if (localStorage.getItem('sort-indicator') === 'off') {
            localStorage.setItem('sort-indicator', 'up');
            sortIndicator.innerHTML = '<i class="fas fa-sort-up"></i>';
        }
        else if (localStorage.getItem('sort-indicator') === 'up') {
            localStorage.setItem('sort-indicator', 'down');
            sortIndicator.innerHTML = '<i class="fas fa-sort-down"></i>';
        }
        else if (localStorage.getItem('sort-indicator') === 'down') {
            localStorage.setItem('sort-indicator', 'off');
            sortIndicator.innerHTML = '<i class="fa-solid fa-sort"></i>';
        }
    }
    adjustRows();
}
function adjustRows() {
    const rows = $('.queue_history_row').toArray();
    rows.sort((a, b) => {
        if (localStorage.getItem('sort-indicator') === 'up') {
            if ($(a).find('.help-started').text()>$(b).find('.help-started').text()) {
                return -1;
            }
            else {
                return 1;
            }
        }
        else if (localStorage.getItem('sort-indicator') === 'down') {
            if ($(a).find('.help-started').text()>$(b).find('.help-started').text()) {
                return 1;
            }
            else {
                return -1;
            }
        }
        else {
            if (parseInt($(a).find('.number-count').text())<parseInt($(b).find('.number-count').text())) {
                return -1;
            }
            else {
                return 1;
            }
        }
    });
    $('#queue-history-table').empty();
    for (let i=0; i < rows.length; i++) {
        $('#queue-history-table').append($(rows[i]));
    }
}

