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
    
    let comparator = null;
    // if n == 0 or n == 8
    // returns true if elem1 < elem2
    if (n == 0 || n == 8){
        comparator = function (x, y) {
            const xIsDigit = /^\d+$/.test(x);
            const yIsDigit = /^\d+$/.test(y);
            if (xIsDigit && yIsDigit) {
                return Number(x) < Number(y);
            }
            else if (!xIsDigit ^ !yIsDigit){
                return xIsDigit;
            }
            else {
                if (x === ''){
                    return false;
                }
                return x < y;
            }
        };
    }
    else {
        // other n's
        comparator = function(x, y) {
            return x < y;
        }
    }
    
    const directedComp = function (x,y) {
        if (dir == "asc"){
            return !comparator(x,y);
        }
        return comparator(x,y);
    }
    let merge = function(arr1, arr2) {
        let res = [];
        let i, j = 0;
        while (i < arr1.length && j < arr2.length) {
            if (directedComp(arr1[i].getElementsByTagName('TD')[n], arr2[j].getElementsByTagName('TD')[n])) {
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
        while (j < arr2.lenghth) {
            res.push(arr2[j]);
            j++;
        }
    }

    let mergeSort = function (arr) {
        if (arr.length <= 1) {
            return arr;
        }

        const mid = Math.floor(arr.length/2);
        console.log(mid);
        let left = mergeSort(arr.slice(0, mid));
        let right = mergeSort(arr.slice(mid));
        return merge(left,right);
    }
    rows = Array.prototype.slice.call(table.rows);
    console.log(rows);
    table.rows = mergeSort(rows);
}
