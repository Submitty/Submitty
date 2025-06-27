/* exported collapseSection, confirmationDialog, removeImage, addImage, updateImage */
/**
* toggles visibility of a content sections on the Docker UI
* @param {string} id of the section to toggle
* @param {string} btn_id id of the button calling this function
*/
function collapseSection(id, btn_id) {
    const tgt = document.getElementById(id);
    const btn = document.getElementById(btn_id);

    if (tgt.style.display === 'block') {
        tgt.style.display = 'none';
        btn.innerHTML = 'Expand';
    }
    else {
        tgt.style.display = 'block';
        btn.innerHTML = 'Collapse';
    }
}

function filterOnClick() {
    const this_filter = $(this).data('capability');

    $('.filter-buttons').each(function () {
        $(this).addClass('fully-transparent');
    });

    $(this).removeClass('fully-transparent');

    $('.image-row').each(function () {
        const this_row = $(this);
        let hide = true;
        $(this).find('.badge').each(function () {
            if ($(this).text() === this_filter) {
                hide = false;
            }
        });
        if (hide) {
            this_row.hide();
        }
        else {
            this_row.show();
        }
    });
}

function showAll() {
    $('.image-row').show();
    $('.filter-buttons').removeClass('fully-transparent');
}

function addFieldOnChange() {
    const command = $(this).val();
    const regex = new RegExp('^[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+/[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$');
    if (!regex.test(command)) {
        $('#send-button').attr('disabled', true);
        if (command !== '') {
            $('#docker-warning').css('display', '');
        }
    }
    else {
        $('#send-button').attr('disabled', false);
        $('#docker-warning').css('display', 'none');
    }
}

function confirmationDialog(url, id) {
    if (confirm(`Are you sure you want to remove ${id} image?`)) {
        removeImage(url, id);
    }
}

function removeImage(url, id) {
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            image: id,
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                location.reload();
                // eslint-disable-next-line no-undef
                displaySuccessMessage(json.data);
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function addImage(url) {
    const capability = $('#capability-form').val();
    const image = $('#add-field').val();
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            capability: capability,
            image: image,
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                $('#add-field').val('');
                // eslint-disable-next-line no-undef
                displaySuccessMessage(json.data);
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function updateImage(url) {
    $.ajax({
        url: url,
        type: 'GET',
        data: {
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                // eslint-disable-next-line no-undef
                displaySuccessMessage(json.data);
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

$(document).ready(() => {
    $('.filter-buttons').on('click', filterOnClick);
    $('#show-all').on('click', showAll);
    $('#add-field').on('input', addFieldOnChange);
    $('#add-field').trigger('input');
});

function sortTableByColumn(sortKey) {
    const currentSort = Cookies.get('docker_table_key');
    const currentDirection = Cookies.get('docker_table_direction') || 'ASC';

    let newDirection;
    if (currentSort === sortKey) {
        newDirection = (currentDirection === 'ASC' ? 'DESC' : 'ASC');
    }
    else {
        newDirection = 'ASC';
    }

    Cookies.set('docker_table_key', sortKey, { path: '/admin/docker' });
    Cookies.set('docker_table_direction', newDirection, { path: '/admin/docker' });

    applySort(sortKey, newDirection);
    updateSortIcons(sortKey, newDirection);
}

function applySort(sortKey, direction) {
    const table = document.getElementById('docker-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const colMap = { name: 0, size: 4, created: 5 };
    const colIndex = colMap[sortKey];

    rows.sort((rowA, rowB) => {
        const aText = rowA.children[colIndex].textContent.trim();
        const bText = rowB.children[colIndex].textContent.trim();
        let cmp = 0;
        if (sortKey === 'name') {
            const nameA = rowA.children[0].textContent.trim();
            const nameB = rowB.children[0].textContent.trim();
            cmp = nameA.localeCompare(nameB);
            if (cmp === 0) {
                const tagA = rowA.children[1].textContent.trim();
                const tagB = rowB.children[1].textContent.trim();
                const numA = parseFloat(tagA);
                const numB = parseFloat(tagB);
                const isNumA = !isNaN(numA);
                const isNumB = !isNaN(numB);
                // Tag is descending regardless of Image Name
                if (isNumA && isNumB) {
                    cmp = numB - numA;
                }
                else {
                    cmp = tagB.localeCompare(tagA);
                }
                return cmp;
            }
        }
        else if (sortKey === 'size') {
            const valA = parseFloat(aText.replace('MB', ''));
            const valB = parseFloat(bText.replace('MB', ''));
            cmp = valA - valB;
        }
        else if (sortKey === 'created') {
            const dateA = new Date(aText);
            const dateB = new Date(bText);
            cmp = dateA - dateB;
        }
        return direction === 'ASC' ? cmp : -cmp;
    });
    rows.forEach((row) => tbody.appendChild(row));
}

function updateSortIcons(activeKey, direction) {
    document.querySelectorAll('.sortable-header').forEach((link) => {
        const icon = link.querySelector('i');
        const key = link.dataset.sortKey;

        icon.classList.remove('fa-sort-up', 'fa-sort-down');
        icon.classList.add('fa-sort');

        if (key === activeKey) {
            icon.classList.remove('fa-sort');
            icon.classList.add(direction === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');
        }
    });
}

// Keeps the specified sort on reload
window.addEventListener('DOMContentLoaded', () => {
    const savedSort = Cookies.get('docker_table_key');
    const savedDirection = Cookies.get('docker_table_direction') || 'ASC';
    if (savedSort) {
        applySort(savedSort, savedDirection);
        updateSortIcons(savedSort, savedDirection);
    }
});
