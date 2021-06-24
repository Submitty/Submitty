/* exported clear, sortTable */
function clear(){
    document.getElementById('gradeable_access_date').value = '';
    document.getElementById('gradeable_submission_date').value = '';
    document.getElementById('forum_view').value = '';
    document.getElementById('form_post').value = '';
    document.getElementById('num_poll_responses').value = '';
    document.getElementById('office_hours_queue_date').value = '';
}

function sortTable(n, flag) {
    let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
    const table = document.getElementById('data-table');
    for (i = 0; i < 10; i++){
        if (i != n && $(`#{i}`).children('i').hasClass('fa-angle-up')){
            $(`#${i}`).children('i').removeClass('fa-angle-up').addClass('fa-angle-down');
        }
    }
    if ($(`#${n}`).children('i').hasClass('fa-angle-up')){
        $(`#${n}`).children('i').removeClass('fa-angle-up').addClass('fa-angle-down');
    }
    else {
        $(`#${n}`).children('i').removeClass('fa-angle-down').addClass('fa-angle-up');
    }
    switching = true;
    // Set the sorting direction to ascending:
    dir = 'asc';
    if (flag){
        dir = 'desc';
    }
    /* Make a loop that will continue until
    no switching has been done: */
    while (switching) {
        // Start by saying: no switching is done:
        switching = false;
        rows = table.rows;
        /* Loop through all table rows (except the
        first, which contains table headers): */
        for (i = 1; i < (rows.length - 1); i++) {
            // Start by saying there should be no switching:
            shouldSwitch = false;
            /* Get the two elements you want to compare,
            one from current row and one from the next: */
            x = rows[i].getElementsByTagName('TD')[n];
            y = rows[i + 1].getElementsByTagName('TD')[n];
            /* Check if the two rows should switch place,
            based on the direction, asc or desc: */
            const xIsDigit = /^\d+$/.test(x);
            const yIsDigit = /^\d+$/.test(y);
            if (dir == 'asc') {
                // Data that should be interpreted as a number

                if ((n == 0 && (xIsDigit || yIsDigit)) || n == 8) {
                    if (xIsDigit && yIsDigit && Number(x.innerHTML) > Number(y.innerHTML)) {
                        shouldSwitch = true;
                        break;
                    }
                    if (!xIsDigit) {
                        shouldSwitch = true;
                        break;
                    }
                }
                // Other data
                else if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                    // If so, mark as a switch and break the loop:
                    shouldSwitch = true;
                    break;
                }
            }
            else if (dir == 'desc') {
                // Data that should be interpreted as a number
                if ((n == 0 && (xIsDigit || yIsDigit))|| n == 8) {
                    if (xIsDigit && yIsDigit && Number(x.innerHTML) < Number(y.innerHTML)) {
                        shouldSwitch = true;
                        break;
                    }
                    if (!yIsDigit) {
                        shouldSwitch = true;
                        break;
                    }
                }
                // Other data
                else if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                    // If so, mark as a switch and break the loop:
                    shouldSwitch = true;
                    break;
                }
            }
        }
        if (shouldSwitch) {
            /* If a switch has been marked, make the switch
            and mark that a switch has been done: */
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            // Each time a switch is done, increase this count by 1:
            switchcount ++;
        }
        else {
            /* If no switching has been done AND the direction is 'asc',
            set the direction to 'desc' and run the while loop again. */
            if (switchcount == 0 && dir == 'asc') {
                dir = 'desc';
                switching = true;
            }
        }
    }
}
