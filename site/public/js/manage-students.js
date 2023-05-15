

//Data structure for active columns
let activeColumns = new Array(11).fill(true);
let checkboxes = []

//opens modal with initial settings for new student
function toggleColumnsForm() {
    activeColumns = loadColumns();
    console.log(activeColumns);

    checkboxes = document.getElementsByClassName("toggle-columns-box");
    console.log(checkboxes);

    var form = $("#toggle-columns-form");
    form.css("display", "block");
    checkProperTicks(form);
}

//checks proper tick marks in modal
function checkProperTicks(form){
    for (let i = 0; i<checkboxes.length; i++){
      if (activeColumns[i] == 1){
        checkboxes[i].checked = true;
      }
      else{
        checkboxes[i].checked = false;
      }
    }
}

function updateManageStudentsColumns() {
    getCheckboxValues();
    saveColumns();

    location.reload();
}

//Gets the values of all the checkboxes
function getCheckboxValues(){
    for (let i = 0; i<checkboxes.length; i++){
      if (checkboxes[i].checked = true){
        activeColumns[i] = 1;
      }
      else{
        activeColumns[i] = 0;
      }
    }
}

//Cookies (loading and storing)
function saveColumns() {
    console.log(activeColumns.join('-'));
    document.cookie = `active_columns=${activeColumns.join('-')}`;
}

function loadColumns() {
    let cookie = getCookie('active_columns').split('-')
    for (let i = 0; i< cookie.length; i++){
      if (cookie[i] == '1'){
        cookie[i] = 1;
      }
      else{
        cookie[i] = 0;
      }
    }
    return cookie;
}

function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = document.cookie;
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