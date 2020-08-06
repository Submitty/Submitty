function updateSolutionTaNotes(gradeable_id) {
  let data = {
    solution_text: $("#solution-ta-notes-textbox").val().trim(),
    question_id : 1,    // TODO update this
    csrf_token: csrfToken,
  };
  console.log(csrfToken);
  $.ajax({
    url: buildCourseUrl(['gradeable', gradeable_id, 'solution_ta_notes']),
    type: "POST",
    data,
    success: function (res) {
      console.log(res);
      res = JSON.parse(res);
      if (res.status === "success") {

      } else {

      }
      console.log(res);
    },
    error: function(err) {
      console.log(err);
    },
  })
}

$(document).ready(function () {
  //DOM selectors
  let showSolBtn = $(".show-sol-btn");
  let solTextBoxCont = $("#sol-textbox-cont");
  // Show the textbox to start writing out the solutions
  showSolBtn.click(function() {
    if (solTextBoxCont.hasClass("hide")) {
      solTextBoxCont.removeClass("hide");
      showSolBtn.addClass("hide");
    }
  });

});
