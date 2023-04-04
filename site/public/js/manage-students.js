

//Data structure for active columns

//opens modal with initial settings for new student
function toggleColumnsForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#toggle-columns-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    captureTabInModal("toggle-columns-form");
}

function updateManageStudentsColumns() {
    alert("Update Columns");
}