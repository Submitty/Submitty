$(document).ready(function() {
  let isCollapsibleDisabled = true;
  const TAB_WIDTH = 950;
  const panelHead = "#gradeables .course-section-heading";

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
});
