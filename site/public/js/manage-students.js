

//Data structure for active columns
let activeColumns = new Array(11).fill(true);

//opens modal with initial settings for new student
function toggleColumnsForm() {
    activeColumns = loadColumns();

    var form = $("#toggle-columns-form");
    form.css("display", "block");
    checkProperTicks(form);
}

//checks proper tick marks in modal
function checkProperTicks(form){
    const checkboxes = document.getElementsByTagName("checkbox");
    console.log(checkboxes[0]);
}

function updateManageStudentsColumns() {
    saveColumns();
    alert("Update Columns");

    location.reload();
}

//Cookies (loading and storing)
function saveColumns() {
    document.cookie = `active_columns=${encodeURIComponent(JSON.stringify(activeColumns))}`;
}

function loadColumns() {
    return getCookie('active_columns');
}

function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for(let i = 0; i <ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
}