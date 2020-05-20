$(document).ready(function() {
  $(".course-section-heading").click(function () {
    // Toggle the view (collapse/expand) of the section
    $(this).next().slideToggle({
      duration: 600
    });
  });
});
