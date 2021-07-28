/* global buildCourseUrl, WebSocketClient previewMarkdown */
/* exported initGradingInquirySocketClient, onComponentTabClicked, onGradeInquirySubmitClicked, onReady, onReplyTextAreaKeyUp previewInquiryMarkdown */

function onReady(){
    // open last opened grade inquiry or open first component with grade inquiry
    const component_selector = localStorage.getItem('selected_tab');
    const first_unresolved_component = $('.component-unresolved').first();
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

    //prevent spam submission
    $('#grade-inquiry-actions').on('click', function() {
        $(this).find('.gi-submit .gi-submit-empty').prop('disabled', true);
    });

    

    const reply_text_area_has_text = $('.reply-text-form').find('[name="replyTextArea"]').val() !== '';
    $('.gi-show-not-empty').toggle(reply_text_area_has_text);
    $('.gi-show-empty').toggle(!reply_text_area_has_text);
    $('.gi-submit').prop('disabled', !reply_text_area_has_text);
    $('.gi-submit-empty').prop('disabled', reply_text_area_has_text);
    
}

function onComponentTabClicked(tab) {
    const component_id = $(tab).data('component_id');

    // show posts that pertain to this component_id
    $('.grade-inquiry').each(function(){
        if ($(this).data('component_id') !== component_id) {
            $(this).hide();
        }
        else {
            $(this).show();
        }
    });

    const component_tab = $('.component-tab');
    component_tab.removeClass('btn-selected');
    $(tab).addClass('btn-selected');

    // update header
    $('.grade-inquiry-header').text(`Grade Inquiry: ${$(tab).text()}`);
}

function onReplyTextAreaKeyUp(textarea) {
    const reply_text_area = $(textarea);
    const must_have_text_buttons = $('.gi-submit:not(.gi-ignore-disabled)');
    must_have_text_buttons.prop('disabled', reply_text_area.val() === '');
    const must_be_empty_buttons = $('.gi-submit-empty:not(.gi-ignore-disabled)');
    must_be_empty_buttons.prop('disabled', reply_text_area.val() !== '');

    if (reply_text_area.val() === '') {
        $('.gi-show-empty').show();
        $('.gi-show-not-empty').hide();
    }
    else {
        $('.gi-show-not-empty').show();
        $('.gi-show-empty').hide()
    }
}

function onGradeInquirySubmitClicked(button) {
    // check double submission
    const button_clicked = $(button);
    const component_selected = $('.btn-selected');
    const component_id = component_selected.length ? component_selected.data('component_id') : 0;
    localStorage.setItem('selected_tab',`.component-${component_id}`);
    const form = $(`#reply-text-form-${component_id}`);
    if (form.data('submitted') === true) {
        return;
    }

    // if grader clicks Close Grade Inquiry button with text in text area we want to confirm that they want to close the grade inquiry
    // and ignore their response
    const text_area = $(`#reply-text-area-${component_id}`);
    const submit_button_id = button_clicked.attr('id');
    if (submit_button_id && submit_button_id === 'grading-close'){
        if (text_area.val().trim()) {
            if (!confirm('The text you entered will not be posted. Are you sure you want to close the grade inquiry?')) {
                return;
            }
            else {
                text_area.val('');
            }
        }
    }

    // prevent double submission
    form.data('submitted',true);
    const gc_id = form.children('#gc_id').val();
    $.ajax({
        type: 'POST',
        url: button_clicked.attr('formaction'),
        data: form.serialize(),
        success: function(response){
            try {
                const json = JSON.parse(response);
                if (json['status'] === 'success') {
                    const data = json['data'];

                    // inform other open websocket clients
                    const submitter_id = form.children('#submitter_id').val();
                    if (data.type === 'new_post') {

                        newPostRender(gc_id, data.post_id, data.new_post);
                        text_area.val('');
                        window.socketClient.send({
                            'type': data.type,
                            'post_id': data.post_id,
                            'submitter_id': submitter_id,
                            'gc_id': gc_id,
                        });
                    }
                    else if (data.type === 'open_grade_inquiry'){
                        window.socketClient.send({'type' : 'toggle_status', 'submitter_id' : submitter_id});
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
        },
    });
    // allow form resubmission
    form.data('submitted',false);
    $('.gi-submit').prop('disabled', true);
    $('.gi-submit-empty').prop('disabled', false);
}

function initGradingInquirySocketClient() {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        switch (msg.type) {
            case 'new_post':
                gradeInquiryNewPostHandler(msg.submitter_id, msg.post_id, msg.gc_id);
                break;
            case 'toggle_status':
                gradeInquiryDiscussionHandler(msg.submitter_id);
                break;
            default:
                console.log('Undefined message recieved.');
        }
    };
    const page = `${window.location.pathname.split('gradeable/')[1].split('/')[0]}_${$('#submitter_id').val()}`;
    window.socketClient.open(page);
}

function gradeInquiryNewPostHandler(submitter_id, post_id, gc_id) {
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['gradeable', window.location.pathname.split('gradeable/')[1].split('/')[0], 'grade_inquiry', 'single']),
        data: {
            submitter_id: submitter_id,
            post_id: post_id,
            csrf_token: window.csrfToken,
            gc_id : gc_id,
        },
        success: function(new_post){
            newPostRender(gc_id, post_id, new_post);
        },
    });
}

function newPostRender(gc_id, post_id, new_post) {
    // if grading inquiry per component is allowed
    if (gc_id != 0){
    // add new post to all tab
        const all_inquiries = $('.grade-inquiries').children("[data-component_id='0']");
        let last_post = all_inquiries.children('.post_box').last();
        $(new_post).insertAfter(last_post).hide().fadeIn('slow');

        // add to grading component
        const component_grade_inquiry = $('.grade-inquiries').children(`[data-component_id='${gc_id}']`);
        last_post = component_grade_inquiry.children('.post_box').last();
        if (last_post.length == 0) {
            // if no posts
            last_post = component_grade_inquiry.children('.grade-inquiry-header-div').last();
        }
        $(new_post).insertAfter(last_post).hide().fadeIn('slow');
    }
    else {
        const last_post = $('.grade-inquiry').children('.post_box').last();
        $(new_post).insertAfter(last_post).hide().fadeIn('slow');
    }
}

function gradeInquiryDiscussionHandler(submitter_id) {
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['gradeable', window.location.pathname.split('gradeable/')[1].split('/')[0], 'grade_inquiry', 'discussion']),
        data: {submitter_id: submitter_id, csrf_token: window.csrfToken},
        success: function(discussion){
            newDiscussionRender(discussion);
        },
    });
}

function newDiscussionRender(discussion) {
    // save the selected component before updating regrade discussion
    const component_selected = $('.btn-selected');
    const component_id = component_selected.length ? component_selected.data('component_id') : 0;
    localStorage.setItem('selected_tab',`.component-${component_id}`);

    // TA (access grading)
    if ($('#regradeBoxSection').length == 0){
        $('#regrade_inner_info').children().html(discussion).hide().fadeIn('slow');
    }
    // student
    else {
        $('#regradeBoxSection').html(discussion).hide().fadeIn('slow');
    }
}

function previewInquiryMarkdown() {
    const markdown_textarea = $(this).closest('.markdown-area').find('[name="replyTextArea"]');
    const preview_element = $('#inquiry_preview');
    const preview_button = $(this);
    const inquiry_content = markdown_textarea.val();

    previewMarkdown(markdown_textarea, preview_element, preview_button, { content: inquiry_content });
}
