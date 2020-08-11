function updateSolutionTaNotes(gradeable_id, component_id) {
  let data = {
    solution_text: $(`#textbox-solution-${component_id}`).val().trim(),
    component_id,
    csrf_token: csrfToken,
  };
  $.ajax({
    url: buildCourseUrl(['gradeable', gradeable_id, 'solution_ta_notes']),
    type: "POST",
    data,
    success: function (res) {
      console.log(res);
      res = JSON.parse(res);
      if (res.status === "success") {
        displaySuccessMessage("Solution has been updated successfully...");
        // Dom manipulation after the Updating/adding the solution note
        $(`#solution-box-${component_id}`).attr('data-first-edit', 0);
        $(`#edit-solution-btn-${component_id}`).removeClass('hide');
        $(`#sol-textbox-cont-${component_id}-saved`).removeClass('hide');
        $(`#sol-textbox-cont-${component_id}-edit`).addClass('hide');

        // Updating the last edit info
        $(`#solution-box-${component_id} .last-edit`).removeClass('hide');
        $(`#solution-box-${component_id} .last-edit i.last-edit-time`).text(res.data.edited_at);
        $(`#solution-box-${component_id} .last-edit i.last-edit-author`).text(res.data.author);
        // Updating the saved notes with the latest solution
        $(`#sol-textbox-cont-${component_id}-saved .solution-notes-text`).text(res.data.solution_text);
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
    $(`#show-sol-btn-${componentId}`).removeClass('hide');
    $(`.solution-notes-text-${componentId}`).removeClass('hide');
    $(`#sol-textbox-cont-${componentId}-edit`).addClass('hide');
  }
  else {
    $(`#edit-solution-btn-${componentId}`).removeClass('hide');
    $(`#sol-textbox-cont-${componentId}-saved`).removeClass('hide');
    $(`#sol-textbox-cont-${componentId}-edit`).addClass('hide');
  }
}
