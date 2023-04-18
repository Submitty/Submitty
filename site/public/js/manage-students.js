

//Data structure for active columns
let activeColumns = new Array(10).fill(1);

//opens modal with initial settings for new student
function toggleColumnsForm() {
    //loadColumns();

    //$('.popup-form').css('display', 'none');
    var form = $("#toggle-columns-form");
    form.css("display", "block");
    //form.find('.form-body').scrollTop(0);
    //captureTabInModal("toggle-columns-form");
}

function updateManageStudentsColumns() {
    //saveColumns();

    alert("Update Columns");
}

//Cookies (loading and storing)
function saveColumns() {
    //$.cookie('activeColumns', JSON.stringify(activeColumns));
    //document.cookie = `active_columns=${encodeURIComponent(JSON.stringify(activeColumns))}`;
}

function loadColumns() {
    return JSON.parse($.cookie('activeColumns'));
}