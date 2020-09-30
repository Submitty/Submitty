function onReady(){
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
}

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
    success: function(response){
      try {
        let json = JSON.parse(response);
        if (json['status'] === 'success') {
          let data = json['data'];

          // inform other open websocket clients
          let submitter_id = form.children('#submitter_id').val();
          if (data.type === 'new_post') {
            let gc_id = form.children('#gc_id').val();
            newPostRender(gc_id, data.post_id, data.new_post);
            text_area.val("");
            window.socketClient.send({
              'type': data.type,
              'post_id': data.post_id,
              'submitter_id': submitter_id,
              'gc_id': gc_id
            });
          }
          else if(data.type === "open_grade_inquiry"){
            window.socketClient.send({'type' : "toggle_status", 'submitter_id' : submitter_id});
            window.location.reload();
          }
          else if (data.type === 'toggle_status') {
            newDiscussionRender(data.new_discussion);
            window.socketClient.send({'type': data.type, 'submitter_id': submitter_id});
          }
        }
      }
      catch (e) {
        console.log(e);
      }
    }
  });
  // allow form resubmission
  form.data("submitted",false);
}

function initGradingInquirySocketClient() {
  window.socketClient = new WebSocketClient();
  window.socketClient.onmessage = (msg) => {
    switch (msg.type) {
      case "new_post":
        gradeInquiryNewPostHandler(msg.submitter_id, msg.post_id, msg.gc_id);
        break;
      case "toggle_status":
        gradeInquiryDiscussionHandler(msg.submitter_id);
        break;
      default:
        console.log("Undefined message recieved.");
    }
  };
  let page = window.location.pathname.split("gradeable/")[1].split('/')[0] + '_' + $('#submitter_id').val();
  window.socketClient.open(page);
}

function gradeInquiryNewPostHandler(submitter_id, post_id, gc_id) {
  $.ajax({
    type: "POST",
    url: buildCourseUrl(['gradeable', window.location.pathname.split("gradeable/")[1].split('/')[0], 'grade_inquiry', 'single']),
    data: {submitter_id: submitter_id, post_id: post_id, csrf_token: window.csrfToken},
    success: function(new_post){
      newPostRender(gc_id, post_id, new_post);
    }
  });
}

function newPostRender(gc_id, post_id, new_post) {
  // if grading inquiry per component is allowed
  if (gc_id != 0){
    // add new post to all tab
    let all_inquiries = $(".grade-inquiries").children("[data-component_id='0']");
    let last_post = all_inquiries.children('.post_box').last();
    $(new_post).insertAfter(last_post).hide().fadeIn('slow');

    // add to grading component
    let component_grade_inquiry = $(".grade-inquiries").children("[data-component_id='" + gc_id + "']");
    last_post = component_grade_inquiry.children('.post_box').last();
    if (last_post.length == 0) {
      // if no posts
      last_post = component_grade_inquiry.children('.grade-inquiry-header-div').last();
    }
    $(new_post).insertAfter(last_post).hide().fadeIn('slow');
    component_grade_inquiry.find("[data-post_id=" + post_id + "]").children('div').first().remove();
  }
  else {
    let last_post = $('.grade-inquiry').children('.post_box').last();
    $(new_post).insertAfter(last_post).hide().fadeIn('slow');
  }
}

function gradeInquiryDiscussionHandler(submitter_id) {
  $.ajax({
    type: "POST",
    url: buildCourseUrl(['gradeable', window.location.pathname.split("gradeable/")[1].split('/')[0], 'grade_inquiry', 'discussion']),
    data: {submitter_id: submitter_id, csrf_token: window.csrfToken},
    success: function(discussion){
      newDiscussionRender(discussion);
    }
  });
}

function newDiscussionRender(discussion) {
  // save the selected component before updating regrade discussion
  let component_selected = $('.btn-selected');
  let component_id = component_selected.length ? component_selected.data('component_id') : 0;
  localStorage.setItem('selected_tab','.component-'+component_id);

  // TA (access grading)
  if ($('#regradeBoxSection').length == 0){
    $('#regrade_inner_info').children().html(discussion).hide().fadeIn('slow');
  }
  // student
  else $('#regradeBoxSection').html(discussion).hide().fadeIn("slow");
}
