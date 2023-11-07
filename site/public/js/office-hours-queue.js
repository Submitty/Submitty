//this helps update the frontend when the page refreshes because without this the sort icon would reset and the sort state would not 
document.addEventListener('DOMContentLoaded', function() {
    var sortIndicator = document.getElementById('sortIndicator');
    var sortState = localStorage.getItem('sortStateTimeEntered');

    if (sortState === null) {
        // Set a default value if sortState has never been created
        sortState = 'off'; // Assuming 'off' is your default state
        localStorage.setItem('sortStateTimeEntered', sortState);
    }

    if (sortState == 'up') {
        sortIndicator.innerHTML = '<i class="fas fa-sort-up"></i>';
    }
    if (sortState == 'off') {
        sortIndicator.innerHTML = '<i class="fa-solid fa-sort"></i>';
    }
    if (sortState == 'down') {
        sortIndicator.innerHTML = '<i class="fas fa-sort-down"></i>';
    }
    adjustRows();
});

function toggleSort(column) {
    if (column == 'HelpStartedBy') {
        if (localStorage.getItem('sortStateTimeEntered') == "off") {
            localStorage.setItem('sortStateTimeEntered', 'up'); // Save to localStorage
            sortIndicator.innerHTML = '<i class="fas fa-sort-up"></i>'
        } else if (localStorage.getItem('sortStateTimeEntered') == "up") {
            localStorage.setItem('sortStateTimeEntered', 'down'); // Save to localStorage
            sortIndicator.innerHTML = '<i class="fas fa-sort-down"></i>';
        } else if (localStorage.getItem('sortStateTimeEntered') == "down") {
            localStorage.setItem('sortStateTimeEntered', 'off'); // Save to localStorage
            sortIndicator.innerHTML = '<i class="fa-solid fa-sort"></i>'; // Reset to down arrow for off state
        }
    }
    adjustRows();
  }

function adjustRows(){
    let rows = $('.queue_history_row').toArray();
    rows.sort(function (a, b) {
      if (localStorage.getItem('sortStateTimeEntered') == "up"){
        if($(a).find(".helpStarted").text()>$(b).find(".helpStarted").text()){
          return -1;
        }
        else{
          return 1;
        }
      }
      else if(localStorage.getItem('sortStateTimeEntered') == "down"){
        if ($(a).find(".helpStarted").text()>$(b).find(".helpStarted").text()){
          return 1;
        }
        else{
          return -1;
        }
      }
      else {
        if (parseInt($(a).find(".numberCount").text())<parseInt($(b).find(".numberCount").text())){
            return -1;
          }
          else{
            return 1;
          }
      }
    });
    $("#queueHistoryTable").empty();
    for(let i=0; i < rows.length; i++){
        $("#queueHistoryTable").append($(rows[i]));
    }
}

