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
        if (i != n && $(`#${i}`).children('i').hasClass('fa-angle-up')){
            $(`#${i}`).children('i').removeClass('fa-angle-up').addClass('fa-angle-down');
        }
    }
    if ($(`#${n}`).children('i').hasClass('fa-angle-up')){
        $(`#${n}`).children('i').removeClass('fa-angle-up').addClass('fa-angle-down');
        dir = "desc";
    }
    else {
        $(`#${n}`).children('i').removeClass('fa-angle-down').addClass('fa-angle-up');
        dir = "asc";
    }
    switching = true;
    console.log(dir);
    // Comparator used to compare 2 data entries for sorting
    let comparator = function (row1, row2) {
        if (dir == "desc" && helper(row1[n].innerHTML, row2[n].innerHTML, n)) {
            return true;
        }
        else if (dir == "desc" && helper(row2[n].innerHTML, row1[n].innerHTML, n)) {
            // row2 > row1
            return false;
        }
        else if (dir == "asc" && !helper(row1[n].innerHTML, row2[n].innerHTML, n)) {
            return true;
        }
        else if (dir == "asc" && !helper(row2[n].innerHTML, row1[n].innerHTML, n)) {
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
    let helper = function (x, y, i) {
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
            // other n's
            return x < y;
        }
    };
    
    console.log(comparator);
    let merge = function(arr1, arr2) {
        let res = [];
        let i = 0, j = 0;
        while (i < arr1.length && j < arr2.length) {
            //console.log(arr1[i].getElementsByTagName('TD')[n].innerHTML, arr2[j].getElementsByTagName('TD')[n].innerHTML)
            //console.log(comparator(arr1[i].getElementsByTagName('TD'), arr2[j].getElementsByTagName('TD')));
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
    }

    let mergeSort = function (arr) {
        if (arr.length <= 1) {
            return arr;
        }

        const mid = Math.floor(arr.length/2);
        let left = mergeSort(arr.slice(0, mid));
        let right = mergeSort(arr.slice(mid));
        let res = merge(left,right);
        //console.log(res);
        return res;
    }
    rows = table.rows;
    //console.log(rows);
    const sorted = mergeSort(Array.prototype.slice.call(rows).slice(1));
    document.getElementById('data-table');
    //console.log(table.rows.length);
    for (i = 1; i < table.rows.length; i++){
        table.rows[i-1].nextSibling.parentNode.insertBefore(sorted[i-1],rows[i-1].nextSibling);
    }
}
