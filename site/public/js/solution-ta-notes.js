function updateSolutionTaNotes(gradeable_id, que_part_id) {
  let data = {
    solution_text: $(`#solution-${que_part_id}`).val().trim(),
    que_part_id,    // TODO update this
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
          displaySuccessMessage("Solution has been updated successfully...")
      } else {
        displayErrorMessage("Something went wrong while upating the solution...")
      }
      console.log(res);
    },
    error: function(err) {
      console.log(err);
    },
  })
}

function showSolutionTextboxCont(currentEle, solTextboxCont, noSolutionCont) {
  $(currentEle).addClass('hide');
  // Show the textbox to start writing out the solutions
  if ($(solTextboxCont).hasClass("hide")) {
    $(solTextboxCont).removeClass("hide");
    $(noSolutionCont).addClass("hide");
  }
}

function cancelEditingSolution(componentId) {
  let isFirstEdit = $(`#solution-box-${componentId}`).attr('data-first-edit');

  if (+isFirstEdit) {
    console.log("true");
    $(`#show-sol-btn-${componentId}`).removeClass('hide');
    $(`.solution-notes-text-${componentId}`).removeClass('hide');
    $(`#sol-textbox-cont-${componentId}-edit`).addClass('hide');
  }
  else {
    console.log("false");
    $(`#edit-solution-btn-${componentId}`).removeClass('hide');
    $(`#sol-textbox-cont-${componentId}-saved`).removeClass('hide');
    $(`#sol-textbox-cont-${componentId}-edit`).addClass('hide');
  }
}
