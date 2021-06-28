/**
 * Resets the filter field to be empty strings
 */
export function clear(){
    document.getElementById('gradeable_access_date').value = '';
    document.getElementById('gradeable_submission_date').value = '';
    document.getElementById('forum_view').value = '';
    document.getElementById('form_post').value = '';
    document.getElementById('num_poll_responses').value = '';
    document.getElementById('office_hours_queue_date').value = '';
}

/**
 * Sorts the table by the column n given by user
 * This function will toggle between sorting by ascending order and descending order
 * @param {int} n
 */
export function sortTable(n) {
    let i, dir;
    const table = document.getElementById('data-table');

    if ($(`#${n}`).children('i').hasClass('fa-angle-up')){
        $(`#${n}`).children('i').removeClass('fa-angle-up').addClass('fa-angle-down');
        dir = 'desc';
    }
    else {
        $(`#${n}`).children('i').removeClass('fa-angle-down').addClass('fa-angle-up');
        dir = 'asc';
    }

    for (i = 0; i < 10; i++){
        if (i != n && $(`#${i}`).children('i').hasClass('fa-angle-up')){
            $(`#${i}`).children('i').removeClass('fa-angle-up');
        }
        else if (i != n && $(`#${i}`).children('i').hasClass('fa-angle-down')){
            $(`#${i}`).children('i').removeClass('fa-angle-down');
        }
    }

    // Comparator used to compare 2 data entries for sorting
    const comparator = function (row1, row2) {
        if (dir == 'desc' && helper(row1[n].innerHTML, row2[n].innerHTML, n)) {
            return true;
        }
        else if (dir == 'desc' && helper(row2[n].innerHTML, row1[n].innerHTML, n)) {
            // row2 > row1
            return false;
        }
        else if (dir == 'asc' && !helper(row1[n].innerHTML, row2[n].innerHTML, n)) {
            return true;
        }
        else if (dir == 'asc' && !helper(row2[n].innerHTML, row1[n].innerHTML, n)) {
            return false;
        }
        for (i = 1; i < 4; i++){
            if (i != n && helper(row1[i].innerHTML, row2[i].innerHTML, i) && !helper(row2[i].innerHTML, row1[i].innerHTML, i)) {
                return true;
            }
        }
        return false;
    };
    // if n == 0 or n == 8
    // returns true if x < y, digits < strings < empty strings
    const helper = function (x, y, i) {
        if (i == 0 || i == 8){
            const xIsDigit = /^\d+$/.test(x);
            const yIsDigit = /^\d+$/.test(y);
            if (xIsDigit && yIsDigit) {
                return Number(x) < Number(y);
            }
            else if (!xIsDigit ^ !yIsDigit){
                return xIsDigit;
            }
            else {
                if (x != '' && y == '') {
                    return true;
                }
                else if (x == '' && y != '') {
                    return false;
                }
                return x < y;
            }
        }
        else {
            // other columns
            return x < y;
        }
    };
    const merge = function(arr1, arr2) {
        const res = [];
        let i = 0, j = 0;
        while (i < arr1.length && j < arr2.length) {
            if (comparator(arr1[i].getElementsByTagName('TD'), arr2[j].getElementsByTagName('TD'))) {
                res.push(arr1[i]);
                i++;
            }
            else {
                res.push(arr2[j]);
                j++;
            }
        }
        while (i < arr1.length) {
            res.push(arr1[i]);
            i++;
        }
        while (j < arr2.length) {
            res.push(arr2[j]);
            j++;
        }
        return res;
    };

    const mergeSort = function (arr) {
        if (arr.length <= 1) {
            return arr;
        }

        const mid = Math.floor(arr.length/2);
        const left = mergeSort(arr.slice(0, mid));
        const right = mergeSort(arr.slice(mid));
        const res = merge(left,right);
        return res;
    };
    const rows = table.rows;
    const sorted = mergeSort(Array.prototype.slice.call(rows).slice(1));
    // inserting rows back into table to update the order
    for (i = 0; i < table.rows.length-1; i++) {
        rows[i+1].parentNode.insertBefore(sorted[i], rows[i+1]);
    }
}
