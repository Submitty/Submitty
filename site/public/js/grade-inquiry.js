$( document ).ready(function () {
  // open last opened grade inquiry or open first component with grade inquiry
  var component_selector = localStorage.getItem('selected_tab');
  var first_unresolved_component = $('.component-unresolved').first();
  if (component_selector !== null) {
    $(component_selector).click();
    localStorage.removeItem('selected_tab');
  }
  else if (first_unresolved_component.length) {
    first_unresolved_component.click();
  }
  else {
    $('.component-tab').first().click();
  }


});

function onComponentTabClicked(tab) {
  var component_id = $(tab).data("component_id");

  // show posts that pertain to this component_id
  $(".grade-inquiry").each(function(){
    if ($(this).data("component_id") !== component_id) {
      $(this).hide();
    }
    else {
      $(this).show();
    }
  });

  var component_tab = $('.component-tab');
  component_tab.removeClass("btn-selected");
  $(tab).addClass("btn-selected");

  // update header
  $(".grade-inquiry-header").text("Grade Inquiry: " + $(tab).text());
}

function onReplyTextAreaKeyUp(textarea) {
  reply_text_area = $(textarea);
  var buttons = $(".gi-submit:not(#gi_ignore_disabled)");
  if (reply_text_area.val() === "") {
    buttons.prop('disabled',true);
  }
  else {
    buttons.prop('disabled',false);
  }
}

function onGradeInquirySubmitClicked(button) {
  // check double submission
  var button_clicked = $(button);
  var component_selected = $('.btn-selected');
  var component_id = component_selected.length ? component_selected.data('component_id') : 0;
  localStorage.setItem('selected_tab','.component-'+component_id);
  var form = $("#reply-text-form-"+component_id);
  if (form.data("submitted") === true) {
    return;
  }

  // if grader clicks Close Grade Inquiry button with text in text area we want to confirm that they want to close the grade inquiry
  // and ignore their response
  var text_area = $("#reply-text-area-"+component_id);
  var submit_button_id = button_clicked.attr('id');
  if (submit_button_id != null && submit_button_id.includes('grading-close')){
    if (text_area.val().trim()) {
      if (!confirm("The text you entered will not be posted. Are you sure you want to close the grade inquiry?")) {
        return;
      }
      else {
        text_area.val("");
      }
    }
  }

  // prevent double submission
  form.data("submitted",true);
  $.ajax({
    type: "POST",
    url: button_clicked.attr("formaction"),
    data: form.serialize(),
    success: function(){
      window.location.reload();
    }
  });
}
