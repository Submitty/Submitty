$(document).ready(function () {
  let isCollapsibleDisabled = true;
  const TAB_WIDTH = 951;
  const panelHead = "#details-table .details-info-header";

  function handleCollapsiblePanel () {
    if (window.innerWidth < TAB_WIDTH && isCollapsibleDisabled) {
      //Add a listener on a Gradeable heading
      $(panelHead).click(function () {
        $(this).toggleClass("panel-head-active");
        $(this).next().slideToggle({
          duration: 600
        });
      });
      isCollapsibleDisabled = false;
    } else if (window.innerWidth > TAB_WIDTH && !isCollapsibleDisabled) {
      // clear the listener from the header
      $(panelHead).off('click');
      // Make all the panels visible
      $('#gradeables .course-section-heading').each(function() {
        if ($(this).next().is( ":hidden" )) {
          $(this).next().slideDown("slow");
          $(this).toggleClass("panel-head-active");
        }
      });
      isCollapsibleDisabled = true;
    }
  }
  // Check for the panels status initially
  handleCollapsiblePanel();
  window.addEventListener("resize", handleCollapsiblePanel);

  // Creating and adding style for the psuedo selector in the details-table
  let style = document.createElement('style');
  let content = "";
  // loop over the head row of `details-table`
  $("#details-table thead tr th").each(function (idx) {
    if (idx) {
      // the content to be added is inside this data attr
      content = $(this).data('col-title');
      style.innerHTML += `
        #details-table td:nth-of-type(${idx + 1}):before { 
            content: "${content}";
        }
      `;
    }
  });
  document.head.appendChild(style);

});

