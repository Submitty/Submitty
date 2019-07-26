$( document ).ready(function () {
  // select first tab with a regrade request
  $('.component-0').click();
});

function onComponentTabClicked(tab) {
  var component_id = $(tab).data("component_id");
  // deselect previous selected tab and select clicked tab
  if ($(tab).attr('id') === "component-tab-selected") {
    $(tab).removeAttr('id');
    component_id = null;
  } else {
    $("#component-tab-selected").removeAttr('id');
    $(tab).attr("id","component-tab-selected");
  }


  // show posts that pertain to this component_id
  $(".grade-inquiry").each(function(){
    if ($(this).data("component_id") !== component_id) {
      $(this).hide();
    } else {
      $(this).show();
    }
  });
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
  var component_id = $('#component-tab-selected').data('component_id');
  var form = $("#reply-text-form-"+component_id);
  if (form.data("submitted") === true) {
    return;
  }

  // if grader clicks Close Grade Inquiry button with text in text area we want to confirm that they want to close the grade inquiry
  // and ignore their response
  var text_area = $("#reply-text-area-"+component_id);
  var submit_button_id = button_clicked.id;
  if (submit_button_id != null && submit_button_id.includes('grading-close-inquiry')){
    if ($.trim(text_area.val())) {
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
