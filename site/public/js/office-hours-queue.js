//this helps update the frontend when the page refreshes because without this the sort icon would reset and the sort state would not
/* eslint prefer-arrow-callback: [ 'error', { 'allowNamedFunctions': true } ] */
document.addEventListener('DOMContentLoaded', () => {
    const sortIndicator = document.getElementById('sort-indicator');
    let sortState = localStorage.getItem('sort-indicator');

    if (sortState === null) {
        sortState = 'off';
        localStorage.setItem('sort-indicator', sortState);
    }

    if (sortState === 'up') {
        sortIndicator.classList.add('fas', 'fa-sort-up');
    }
    else if (sortState === 'off') {
        sortIndicator.classList.add('fa-solid', 'fa-sort');
    }
    else if (sortState === 'down') {
        sortIndicator.classList.add('fas', 'fa-sort-down');
    }

    adjustRows();
});

// eslint-disable-next-line no-unused-vars
function toggleSort(column) {
    const sortIndicator = $('#sort-indicator');
    if (column === 'HelpStartedBy') {
        if (localStorage.getItem('sort-indicator') === 'off') {
            localStorage.setItem('sort-indicator', 'up');
            sortIndicator.attr('class', 'fas fa-sort-up');
        }
        else if (localStorage.getItem('sort-indicator') === 'up') {
            localStorage.setItem('sort-indicator', 'down');
            sortIndicator.attr('class', 'fas fa-sort-down');
        }
        else if (localStorage.getItem('sort-indicator') === 'down') {
            localStorage.setItem('sort-indicator', 'off');
            sortIndicator.attr('class', 'fa-solid fa-sort');
        }
    }
    adjustRows();
}
function adjustRows() {
    const rows = $('.queue_history_row').toArray();
    rows.sort((a, b) => {
        if (localStorage.getItem('sort-indicator') === 'up') {
            return $(a).find('.help-started').text() > $(b).find('.help-started').text() ? -1 : 1;
        }
        else if (localStorage.getItem('sort-indicator') === 'down') {
            return $(a).find('.help-started').text() > $(b).find('.help-started').text() ? 1 : -1;

        }
        else {
            return parseInt($(a).find('.number-count').text()) < parseInt($(b).find('.number-count').text()) ? -1 : 1;
        }
    });
    $('#queue-history-table').empty();
    for (let i=0; i < rows.length; i++) {
        $('#queue-history-table').append($(rows[i]));
    }
}

