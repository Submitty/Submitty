/* exported setCalendarMenuValues */
const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];



function setCalendarMenuValues(div_class, tag, number) {

    function updateCalendarMenuVisibility(container) {
        if (!container) return;
    
        let dateInput = container.querySelector('#cal-date-div');
        let gradeableSelect = container.querySelector('#cal-gradeable-div');
        let showOnCalendar = container.querySelector('#show-menu');
    
        if (!dateInput || !gradeableSelect || !showOnCalendar) return;
    
        if (showOnCalendar.value === "gradeable") {
            dateInput.style.display = "none";
            gradeableSelect.style.display = "block";
        } else if (showOnCalendar.value === "date") {
            dateInput.style.display = "block";
            gradeableSelect.style.display = "none";
        } else {
            dateInput.style.display = "none";
            gradeableSelect.style.display = "none";
        }
    }


    const associated_date = $(tag).data('associated-date') ?? "none";
    const on_calendar = $(tag).data('is-on-calendar') ?? false;
    const gradeable = $(tag).data('gradeable') ?? "none";



    if (associated_date !== null && on_calendar !== null && gradeable !== null) {
        console.log("Is On Calendar:", "date");
        console.log("Associated Date:", associated_date);
        console.log("Gradeable:", gradeable);
    }

    let container = document.getElementById(number);
    if (container) {
        let dateInput = container.querySelector('#associated-date');
        if (dateInput) {
            dateInput.value = associated_date;
        }

        let gradeableSelect = container.querySelector('#gradeable-select');
        if (gradeableSelect) {
            gradeableSelect.value = gradeable;
        }


        let showOnCalendar = container.querySelector('#show-menu');
        if (showOnCalendar) {

            if (on_calendar && gradeable != "none") {
                console.log("on gradeable set");
                showOnCalendar.value = "gradeable";
                
                dateInput.display = "none";
            }
            else if (on_calendar && associated_date != "none") {
                showOnCalendar.value = "date";
                gradeableSelect.display="none";

            }
            else {
                showOnCalendar.value = "none"; 
                gradeableSelect.display="none";
                dateInput.display = "none";

            }
        }
        showOnCalendar.addEventListener("change", function () {
            updateCalendarMenuVisibility(container);
        });
    

    }


    updateCalendarMenuVisibility(container);
}
