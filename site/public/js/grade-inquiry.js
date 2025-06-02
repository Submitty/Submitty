/* global buildCourseUrl, WebSocketClient */
/* exported loadDraft, initGradingInquirySocketClient, onComponentTabClicked, onGradeInquirySubmitClicked, onReady, onReplyTextAreaKeyUp */

function getLocalStorageKey(key) {
    const { course, term, gradeable_id } = window;

    return `${course}-${term}-${gradeable_id}-${key}`;
}

function loadDraft() {
    const draftContentRaw = localStorage.getItem(getLocalStorageKey('draftContent'));
    const draftContent = draftContentRaw ? JSON.parse(draftContentRaw) : {};

    const elements = $('.markdown-textarea.fill-available');
    elements.each(function () {
        const elementId = $(this).attr('id');
        const componentId = $(this).closest('.reply-text-form').find('#gc_id').val();
        const uniqueKey = `reply-text-area-${componentId}`;

        if (Object.prototype.hasOwnProperty.call(draftContent, uniqueKey)) {
            $(this).val(draftContent[uniqueKey]);
        }
    });
}

function onReady() {
    // open last opened grade inquiry or open first component with grade inquiry
    const selectedTabKey = getLocalStorageKey('selectedTab');
    const component_selector = localStorage.getItem(selectedTabKey);
    const first_unresolved_component = $('.component-unresolved').first();
    if (component_selector !== null) {
        $(component_selector).click();
        localStorage.removeItem(selectedTabKey);
    }
    else if (first_unresolved_component.length) {
        first_unresolved_component.click();
    }
    else {
        $('.component-tab').first().click();
    }

    // prevent spam submission
    $('#grade-inquiry-actions').on('click', function () {
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
    $('.grade-inquiry').each(function () {
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
    const componentId = reply_text_area.closest('.reply-text-form').find('#gc_id').val();
    const uniqueKey = `reply-text-area-${componentId}`;

    const must_have_text_buttons = $('.gi-submit:not(.gi-ignore-disabled)');
    must_have_text_buttons.prop('disabled', reply_text_area.val() === '');
    const must_be_empty_buttons = $('.gi-submit-empty:not(.gi-ignore-disabled)');
    must_be_empty_buttons.prop('disabled', reply_text_area.val() !== '');

    const draftContentKey = getLocalStorageKey('draftContent');
    const draftContentRaw = localStorage.getItem(draftContentKey);
    const draftContent = draftContentRaw ? JSON.parse(draftContentRaw) : {};

    draftContent[uniqueKey] = reply_text_area.val();
    localStorage.setItem(draftContentKey, JSON.stringify(draftContent));

    if (reply_text_area.val() === '') {
        $('.gi-show-empty').show();
        $('.gi-show-not-empty').hide();
    }
    else {
        $('.gi-show-not-empty').show();
        $('.gi-show-empty').hide();
    }
    resizeTextarea(textarea);
}

function onGradeInquirySubmitClicked(button) {
    // check double submission
    const button_clicked = $(button);
    const component_selected = $('.btn-selected');
    const component_id = component_selected.length ? component_selected.data('component_id') : 0;
    localStorage.setItem(getLocalStorageKey('selectedTab'), `.component-${component_id}`);
    const form = $(`#reply-text-form-${component_id}`);
    if (form.data('submitted') === true) {
        return;
    }

    // if grader clicks Close Grade Inquiry button with text in text area we want to confirm that they want to close the grade inquiry
    // and ignore their response
    const draftContentKey = getLocalStorageKey('draftContent');
    const text_area = $(`#reply-text-area-${component_id}`);
    const submit_button_id = button_clicked.attr('id');
    if (submit_button_id && submit_button_id === 'grading-close') {
        if (text_area.val().trim()) {
            if (!confirm('The text you entered will not be posted. Are you sure you want to close the grade inquiry?')) {
                return;
            }
            else {
                text_area.val('');
                localStorage.removeItem(draftContentKey);
            }
        }
    }

    // switch off of preview mode after submission
    const markdown_area = text_area.closest('.markdown-area');
    const markdown_header = markdown_area.find('.markdown-area-header');
    if (markdown_header.attr('data-mode') === 'preview') {
        markdown_header.find('.markdown-write-mode').trigger('click');
    }

    // prevent double submission
    form.data('submitted', true);
    $.ajax({
        type: 'POST',
        url: button_clicked.attr('formaction'),
        data: form.serialize(),
        success: function (response) {
            try {
                const json = JSON.parse(response);

                if (json['status'] === 'success') {
                    text_area.val('');
                    localStorage.removeItem(draftContentKey);
                }
                else {
                    alert(json['message']);
                }
            }
            catch (e) {
                console.log(e);
            }
        },
    });
    // allow form resubmission
    form.data('submitted', false);
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
            case 'open_grade_inquiry':
                window.location.reload();
                break;
            case 'toggle_status':
                gradeInquiryDiscussionHandler(msg.submitter_id);
                break;
            default:
                console.log('Undefined message received.');
        }
    };
    const gradeable_id = window.gradeable_id;
    const submitter_id = $('#submitter_id').val();
    window.socketClient.open('grade_inquiry', {
        gradeable_id: gradeable_id,
        submitter_id: submitter_id,
    });
}

function gradeInquiryNewPostHandler(submitter_id, post_id, gc_id) {
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['gradeable', window.location.pathname.split('gradeable/')[1].split('/')[0], 'grade_inquiry', 'single']),
        data: {
            submitter_id: submitter_id,
            post_id: post_id,
            csrf_token: window.csrfToken,
            gc_id: gc_id,
        },
        success: function (new_post) {
            newPostRender(gc_id, post_id, new_post);
        },
    });
}

function newPostRender(gc_id, post_id, new_post) {
    // if grading inquiry per component is allowed
    // eslint-disable-next-line eqeqeq
    if (gc_id != 0) {
    // add new post to all tab
        const all_inquiries = $('.grade-inquiries').children("[data-component_id='0']");
        let last_post = all_inquiries.children('.post_box').last();
        $(new_post).insertAfter(last_post).hide().fadeIn('slow');

        // add to grading component
        const component_grade_inquiry = $('.grade-inquiries').children(`[data-component_id='${gc_id}']`);
        last_post = component_grade_inquiry.children('.post_box').last();
        if (last_post.length === 0) {
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
        data: { submitter_id: submitter_id, csrf_token: window.csrfToken },
        success: function (discussion) {
            newDiscussionRender(discussion);
        },
    });
}

function newDiscussionRender(discussion) {
    // save the selected component before updating regrade discussion
    const component_selected = $('.btn-selected');
    const component_id = component_selected.length ? component_selected.data('component_id') : 0;
    localStorage.setItem(getLocalStorageKey('selectedTab'), `.component-${component_id}`);

    // TA (access grading)
    if ($('#gradeInquiryBoxSection').length === 0) {
        $('#grade_inquiry_inner_info').children().html(discussion).hide().fadeIn('slow');
    }
    // student
    else {
        $('#gradeInquiryBoxSection').html(discussion).hide().fadeIn('slow');
    }
}

function resizeTextarea(textarea) {
    if (!(textarea instanceof Element)) {
        return;
    }
    textarea.style.height = '100px';
    const currentScrollHeight = textarea.scrollHeight;
    const clientHeight = textarea.clientHeight;
    const scrollTop = textarea.scrollTop;
    if (scrollTop > 0 || currentScrollHeight > clientHeight) {
        textarea.style.height = `${currentScrollHeight}px`;
    }
    const parentBody = textarea.closest('.markdown-area-body');
    if (parentBody) {
        parentBody.style.height = `${textarea.scrollHeight + 32}px`;
    }
}
$(document).ready(() => {
    document.querySelectorAll('.markdown-area textarea').forEach((textarea) => {
        resizeTextarea(textarea);
    });
    loadDraft();
    onReady();
});
