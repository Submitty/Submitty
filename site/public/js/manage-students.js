

//Data structure for active columns
let activeColumns = new Array(10).fill(true);

//opens modal with initial settings for new student
function toggleColumnsForm() {
    activeColumns = loadColumns();
    alert(activeColumns);

    var form = $("#toggle-columns-form");
    form.css("display", "block");
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