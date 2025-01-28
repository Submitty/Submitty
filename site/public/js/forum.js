/* global displaySuccessMessage, hljs, luxon, buildCourseUrl, csrfToken,
    displayErrorMessage, escapeSpecialChars */
/* exported markForDeletion */
/* exported unMarkForDeletion */
/* exported  displayHistoryAttachment */
/* exported toggleUpduck */
/* exported toggleLike */
// eslint-disable-next-line no-unused-vars
function categoriesFormEvents() {
    $('#ui-category-list').sortable({
        items: '.category-sortable',
        handle: '.handle',
        // eslint-disable-next-line no-unused-vars
        update: function (event, ui) {
            reorderCategories();
        },
    });
    $('#ui-category-list').find('.fa-edit').click(function () {
        const item = $(this).parent().parent().parent();
        const category_desc = item.find('.categorylistitem-desc span').text().trim();
        item.find('.categorylistitem-editdesc input').val(category_desc);
        item.find('.categorylistitem-desc').hide();
        item.find('.categorylistitem-editdesc').show();
    });
    $('#ui-category-list').find('.fa-times').click(function () {
        const item = $(this).parent().parent().parent();
        item.find('.categorylistitem-editdesc').hide();
        item.find('.categorylistitem-desc').show();
    });

    const refresh_color_select = function (element) {
        $(element).css('background-color', $(element).val());
    };

    $('.category-color-picker').each(function () {
        refresh_color_select($(this));
    });
}

// eslint-disable-next-line no-unused-vars
function openFileForum(directory, file, path) {
    const url = `${buildCourseUrl(['display_file'])}?dir=${directory}&file=${file}&path=${path}`;
    window.open(url, '_blank', 'toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600');
}

function checkForumFileExtensions(post_box_id, files) {
    const count = files.length;
    for (let i = 0; i < files.length; i++) {
        // eslint-disable-next-line no-undef
        const extension = getFileExtension(files[i].name);
        if (!['gif', 'png', 'jpg', 'jpeg', 'bmp'].includes(extension)) {
            // eslint-disable-next-line no-undef
            deleteSingleFile(files[i].name, post_box_id, false);
            // eslint-disable-next-line no-undef
            removeLabel(files[i].name, post_box_id);
            files.splice(i, 1);
            i--;
        }
    }
    return count === files.length;
}

function resetForumFileUploadAfterError(displayPostId) {
    $(`#file_name${displayPostId}`).html('');
    document.getElementById(`file_input_label${displayPostId}`).style.border = '2px solid red';
    document.getElementById(`file_input${displayPostId}`).value = null;
}

// eslint-disable-next-line no-unused-vars
function checkNumFilesForumUpload(input, post_id) {
    const displayPostId = (typeof post_id !== 'undefined') ? `_${escapeSpecialChars(post_id)}` : '';
    if (input.files.length > 5) {
        displayErrorMessage('Max file upload size is 5. Please try again.');
        resetForumFileUploadAfterError(displayPostId);
    }
    else {
        if (!checkForumFileExtensions(input.files)) {
            displayErrorMessage('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
            resetForumFileUploadAfterError(displayPostId);
            return;
        }
        $(`#file_name${displayPostId}`).html(`<p style="display:inline-block;">${input.files.length} files selected.</p>`);
        $('#messages').fadeOut();
        document.getElementById(`file_input_label${displayPostId}`).style.border = '';
    }
}

function uploadImageAttachments(attachment_box) {
    const observer = new MutationObserver((e) => {
        if (e[0].addedNodes.length === 0 || e[0].addedNodes[0].className === 'thumbnail') {
            return;
        }
        // eslint-disable-next-line no-undef
        const part = get_part_number(e[0]);
        if (isNaN(parseInt(part))) {
            return;
        }
        const target = $(e[0].target).find('tr')[$(e[0].target).find('tr').length - 1];
        let file_object = null;
        const filename = $(target).attr('fname');
        // eslint-disable-next-line no-undef
        for (let j = 0; j < file_array[part - 1].length; j++) {
            // eslint-disable-next-line no-undef
            if (file_array[part - 1][j].name === filename) {
                // eslint-disable-next-line no-undef
                file_object = file_array[part - 1][j];
                break;
            }
        }
        const image = document.createElement('div');
        $(image).addClass('thumbnail');
        $(image).css('background-image', `url(${window.URL.createObjectURL(file_object)})`);
        target.prepend(image);
    });
    $(attachment_box).each(function () {
        observer.observe($(this)[0], {
            childList: true,
            subtree: true,
        });
    });
}

function testAndGetAttachments(post_box_id, dynamic_check) {
    const index = post_box_id - 1;
    const files = [];
    // eslint-disable-next-line no-undef
    for (let j = 0; j < file_array[index].length; j++) {
        // eslint-disable-next-line no-undef
        if (file_array[index][j].name.indexOf("'") !== -1
            // eslint-disable-next-line no-undef
            || file_array[index][j].name.indexOf('"') !== -1) {
            // eslint-disable-next-line no-undef
            alert(`ERROR! You may not use quotes in your filename: ${file_array[index][j].name}`);
            return false;
        }
        // eslint-disable-next-line no-undef
        else if (file_array[index][j].name.indexOf('\\\\') !== -1
            // eslint-disable-next-line no-undef
            || file_array[index][j].name.indexOf('/') !== -1) {
            // eslint-disable-next-line no-undef
            alert(`ERROR! You may not use a slash in your filename: ${file_array[index][j].name}`);
            return false;
        }
        // eslint-disable-next-line no-undef
        else if (file_array[index][j].name.indexOf('<') !== -1
            // eslint-disable-next-line no-undef
            || file_array[index][j].name.indexOf('>') !== -1) {
            // eslint-disable-next-line no-undef
            alert(`ERROR! You may not use angle brackets in your filename: ${file_array[index][j].name}`);
            return false;
        }
        // eslint-disable-next-line no-undef
        files.push(file_array[index][j]);
    }

    let valid = true;
    if (!checkForumFileExtensions(post_box_id, files)) {
        displayErrorMessage('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
        valid = false;
    }

    if (files.length > 5) {
        if (dynamic_check) {
            displayErrorMessage('Max file upload size is 5. Please remove attachments accordingly.');
        }
        else {
            displayErrorMessage('Max file upload size is 5. Please try again.');
        }
        valid = false;
    }

    if (!valid) {
        return false;
    }
    else {
        const submitButtons = document.querySelectorAll(`[data-post_box_id="${post_box_id}"] input[type="submit"]`);
        submitButtons.forEach((button) => {
            button.disabled = false;
        });
        return files;
    }
}

function publishFormWithAttachments(form, test_category, error_message, is_thread) {
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return false;
    }
    if (test_category) {
        if ((!form.prop('ignore-cat')) && form.find('.btn-selected').length === 0 && ($('.cat-buttons input').is(':checked') === false)) {
            alert('At least one category must be selected.');
            return false;
        }
    }
    const post_box_id = form.find('.thread-post-form').data('post_box_id');
    const formData = new FormData(form[0]);

    const files = testAndGetAttachments(post_box_id, false);
    if (files === false) {
        return false;
    }
    for (let i = 0; i < files.length; i++) {
        formData.append('file_input[]', files[i], files[i].name);
    }
    const submit_url = form.attr('action');

    form.find('[type=submit]').prop('disabled', true);

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);

                if (json['status'] === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            // Now that we've successfully submitted the form, clear autosave data
            // eslint-disable-next-line no-undef
            cancelDeferredSave(autosaveKeyFor(form));
            clearReplyBoxAutosave(form);

            window.location.href = json['data']['next_page'];
        },
        error: function () {
            displayErrorMessage(error_message);
        },
    });
    return false;
}

// eslint-disable-next-line no-unused-vars
function createThread(e) {
    e.preventDefault();
    try {
        return publishFormWithAttachments($(this), true, 'Something went wrong while creating thread. Please try again.', true);
    }
    catch (err) {
        console.error(err);
        alert('Something went wrong. Please try again.');
        return false;
    }
}

function publishPost(e) {
    e.preventDefault();
    try {
        return publishFormWithAttachments($(this), false, 'Something went wrong while publishing post. Please try again.', false);
    }
    catch (err) {
        console.error(err);
        alert('Something went wrong. Please try again.');
        return false;
    }
}

function socketNewOrEditPostHandler(post_id, reply_level, post_box_id = null, edit = false) {
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['forum', 'posts', 'single']),
        data: { post_id: post_id, reply_level: reply_level, post_box_id: post_box_id, edit: edit, csrf_token: window.csrfToken },
        success: function (response) {
            try {
                const new_post = JSON.parse(response).data;
                const forum_display_setting = Cookies.get('forum_display_option');
                if (!edit) {
                    const parent_id = $($(new_post)[0]).attr('data-parent_id');
                    const parent_post = $(`#${parent_id}`);
                    if (forum_display_setting === 'reverse-time') {
                        $(new_post).insertAfter('#currents-thread').hide().fadeIn();
                    }
                    else if (forum_display_setting === 'time') {
                        $(new_post).insertBefore('#post-hr').hide().fadeIn();
                    }
                    else if (parent_post.hasClass('first_post')) {
                        if (forum_display_setting === 'reverse-tree') {
                            $(new_post).insertAfter('#current-thread').hide().fadeIn();
                        }
                        else if (forum_display_setting === 'alpha' || forum_display_setting === 'alpha_by_registration' || forum_display_setting === 'alpha_by_rotating') {
                            $(new_post).insertBefore('#post-hr').hide().fadeIn();
                            displaySuccessMessage('Refresh for correct ordering');
                        }
                        else {
                            $(new_post).insertBefore('#post-hr').hide().fadeIn();
                        }
                    }
                    else {
                        const sibling_posts = $(`[data-parent_id="${parent_id}"]`);
                        if (sibling_posts.length !== 0) {
                            const parent_sibling_posts = $(`#${parent_id} ~ .post_box`).map(function () {
                                return $(this).attr('data-reply_level') <= $(`#${parent_id}`).attr('data-reply_level') ? this : null;
                            });
                            if (parent_sibling_posts.length !== 0) {
                                $(new_post).insertBefore(parent_sibling_posts.first()).hide().fadeIn();
                            }
                            else {
                                $(new_post).insertAfter(parent_sibling_posts.prevObject.last()).hide().fadeIn();
                            }
                        }
                        else {
                            $(new_post).insertAfter(parent_post).hide().fadeIn();
                        }
                    }
                }
                else {
                    const original_post = $(`#${post_id}`);
                    $(new_post).insertBefore(original_post);
                    original_post.next().remove();
                    original_post.remove();
                }

                $(`#${post_id}`).addClass('new_post');
                $(`#${post_id}-reply`).css('display', 'none');
                $(`#${post_id}-reply`).submit(publishPost);
                // eslint-disable-next-line no-undef
                previous_files[post_box_id] = [];
                // eslint-disable-next-line no-undef
                label_array[post_box_id] = [];
                // eslint-disable-next-line no-undef
                file_array[post_box_id] = [];
                uploadImageAttachments(`#${post_id}-reply .upload_attachment_box`);
                hljs.highlightAll();
            }
            catch (error) {
                displayErrorMessage('Error parsing new post. Please refresh the page.');
            }
        },
    });
}

function socketDeletePostHandler(post_id) {
    const main_post = $(`#${post_id}`);
    const sibling_posts = $(`#${post_id} ~ .post_box`).map(function () {
        return $(this).attr('data-reply_level') <= $(`#${post_id}`).attr('data-reply_level') ? this : null;
    });
    if (sibling_posts.length !== 0) {
        // eslint-disable-next-line no-var
        var posts_to_delete = main_post.nextUntil(sibling_posts.first());
    }
    else {
        // eslint-disable-next-line no-var, no-redeclare
        var posts_to_delete = main_post.nextUntil('#post-hr');
    }

    posts_to_delete.filter('.reply-box').remove();
    main_post.add(posts_to_delete).fadeOut(400, () => {
        main_post.add(posts_to_delete).remove();
    });
}

function socketNewOrEditThreadHandler(thread_id, edit = false) {
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['forum', 'threads', 'single']),
        data: { thread_id: thread_id, csrf_token: window.csrfToken },
        success: function (response) {
            try {
                const new_thread = JSON.parse(response).data;
                if (!edit) {
                    if ($(new_thread).find('.thread-announcement').length !== 0) {
                        const last_bookmarked_announcement = $('.thread-announcement').siblings('.thread-favorite').last().parent().parent();
                        if (last_bookmarked_announcement.length !== 0) {
                            $(new_thread).insertAfter(last_bookmarked_announcement.next()).hide().fadeIn('slow');
                        }
                        else {
                            $(new_thread).insertBefore($('.thread_box_link').first()).hide().fadeIn('slow');
                        }
                    }
                    else {
                        let spot_after_announcements = $('.thread_box_link').first();
                        if ($(new_thread).find('.thread-announcement-expiring').length === 1) {
                            $(new_thread).insertBefore($('.thread_box_link').first()).hide().fadeIn('slow');
                        }
                        else {
                            while (spot_after_announcements.find('.thread-announcement-expiring').length !== 0) {
                                spot_after_announcements = spot_after_announcements.next();
                            }
                            while (spot_after_announcements.find('.thread-favorite').length !== 0) {
                                spot_after_announcements = spot_after_announcements.next();
                            }
                            $(new_thread).insertBefore(spot_after_announcements).hide().fadeIn('slow');
                        }
                    }
                }
                else {
                    const original_thread = $(`[data-thread_id="${thread_id}"]`);
                    $(new_thread).insertBefore(original_thread);
                    original_thread.remove();
                }
                // eslint-disable-next-line eqeqeq
                if ($('data#current-thread').val() != thread_id) {
                    $(`[data-thread_id="${thread_id}"] .thread_box`).removeClass('active');
                }
            }
            catch (err) {
                displayErrorMessage('Error parsing new thread. Please refresh the page.');
                return;
            }
        },
        // eslint-disable-next-line no-unused-vars
        error: function (a, b) {
            window.alert('Something went wrong when adding new thread. Please refresh the page.');
        },
    });
}

function socketDeleteOrMergeThreadHandler(thread_id, merge = false, merge_thread_id = null) {
    const thread_to_delete = `[data-thread_id='${thread_id}']`;
    $(thread_to_delete).fadeOut('slow', () => {
        $(thread_to_delete).next().remove();
        $(thread_to_delete).remove();
    });

    // eslint-disable-next-line eqeqeq
    if ($('#current-thread').val() == thread_id) {
        if (merge) {
            // eslint-disable-next-line no-var
            var new_url = buildCourseUrl(['forum', 'threads', merge_thread_id]);
        }
        else {
            // eslint-disable-next-line no-var, no-redeclare
            var new_url = buildCourseUrl(['forum']);
        }
        window.location.replace(new_url);
    }
    // eslint-disable-next-line eqeqeq
    else if (merge && $('#current-thread').val() == merge_thread_id) {
        // will be changed when posts work with sockets
        window.location.reload();
    }
}

function socketResolveThreadHandler(thread_id) {
    const icon_to_update = $(`[data-thread_id='${thread_id}']`).find('i.fa-question');
    $(icon_to_update).fadeOut(400, () => {
        $(icon_to_update).removeClass('fa-question thread-unresolved').addClass('fa-check thread-resolved').fadeIn(400);
    });
    $(icon_to_update).attr('title', 'Thread Resolved');
    $(icon_to_update).attr('aria-label', 'Thread Resolved');

    // eslint-disable-next-line eqeqeq
    if ($('#current-thread').val() == thread_id) {
        $("[title='Mark thread as resolved']").remove();
    }
}

function socketAnnounceThreadHandler(thread_id) {
    /*
  * 1. get announced thread with thread_id
  * 2. find correct new place according to the following order:
  *     announcements & pins --> announcements only --> pins only --> other
  *     each group should be sorted chronologically
  * 3. if thread is "active" thread update related elements
  * */
    const thread_to_announce = `[data-thread_id='${thread_id}']`;
    const hr = $(thread_to_announce).next(); // saving the <hr> for inserting later below the thread div
    hr.remove(); // removing this sibling <hr>
    // if there exists other announcements
    if ($('.thread-announcement').length !== 0) {
    // if thread to announce is already bookmarked
        if ($(thread_to_announce).find('.thread-favorite').length !== 0) {
            // if there exists other bookmarked announcements
            if ($('.thread-announcement').siblings('.thread-favorite').length !== 0) {
                // notice that ids in desc order are also in a chronological order (newest : oldest)
                // get announcement threads ids as an array -> [7, 6, 4, 3]
                const announced_pinned_threads_ids = $('.thread-announcement').siblings('.thread-favorite').parent().parent().map(function () {
                    return Number($(this).attr('data-thread_id'));
                }).get();
                // look for thread to insert before -> thread_id 4 if inserting thread_id = 5
                for (let i = 0; i < announced_pinned_threads_ids.length; i++) {
                    if (announced_pinned_threads_ids[i] < thread_id) {
                        // eslint-disable-next-line no-var
                        var thread_to_insert_before = `[data-thread_id='${announced_pinned_threads_ids[i]}']`;
                        $(thread_to_announce).insertBefore($(thread_to_insert_before)).hide().fadeIn('slow');
                        break;
                    }

                    // if last thread then insert after -> if inserting thread_id = 2
                    if (i === announced_pinned_threads_ids.length - 1) {
                        // eslint-disable-next-line no-var
                        var thread_to_insert_after = `[data-thread_id='${announced_pinned_threads_ids[i]}']`;
                        $(thread_to_announce).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
                    }
                }
            }
            // no bookmarked announcements -> insert already-bookmarked new announcement at the beginning
            else {
                $(thread_to_announce).insertBefore($('.thread_box_link').first()).hide().fadeIn('slow');
            }
        }
        // thread to announce is not bookmarked
        else {
            // find announcements that are not bookmarked
            const announced_pinned_threads = $('.thread-announcement').siblings('.thread-favorite').parent().parent();
            const announced_only_threads = $('.thread-announcement').parent().parent().not(announced_pinned_threads);
            if (announced_only_threads.length !== 0) {
                const announced_only_threads_ids = $(announced_only_threads).map(function () {
                    return Number($(this).attr('data-thread_id'));
                }).get();
                for (let i = 0; i < announced_only_threads_ids.length; i++) {
                    if (announced_only_threads_ids[i] < thread_id) {
                        // eslint-disable-next-line no-var, no-redeclare
                        var thread_to_insert_before = `[data-thread_id='${announced_only_threads_ids[i]}']`;
                        $(thread_to_announce).insertBefore($(thread_to_insert_before)).hide().fadeIn('slow');
                        break;
                    }

                    if (i === announced_only_threads_ids.length - 1) {
                        // eslint-disable-next-line no-var, no-redeclare
                        var thread_to_insert_after = `[data-thread_id='${announced_only_threads_ids[i]}']`;
                        $(thread_to_announce).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
                    }
                }
            }
            // if all announcements are bookmarked -> insert new announcement after the last one
            else {
                // eslint-disable-next-line no-var, no-redeclare
                var thread_to_insert_after = announced_pinned_threads.last();
                $(thread_to_announce).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
            }
        }
    }
    // no annoucements at all -> insert new announcement at the beginning
    else {
        $(thread_to_announce).insertBefore($('.thread_box_link').first()).hide().fadeIn('slow');
    }

    let announcement_icon = '<i class="fas fa-thumbtack thread-announcement" title = "Pinned to the top" aria-label="Pinned to the top"></i>';
    $(thread_to_announce).children().prepend(announcement_icon);
    $(hr).insertAfter($(thread_to_announce)); // insert <hr> right after thread div
    // if user's current thread is the one modified -> update
    // eslint-disable-next-line eqeqeq
    if ($('#current-thread').val() == thread_id) {
    // if is instructor
        const instructor_pin = $('.not-active-thread-announcement');
        if (instructor_pin.length) {
            instructor_pin.removeClass('.not-active-thread-announcement').addClass('active-thread-remove-announcement');
            instructor_pin.attr('onClick', instructor_pin.attr('onClick').replace('1,', '0,').replace('pin this thread to the top?', 'unpin this thread?'));
            instructor_pin.attr('title', 'Unpin Thread');
            instructor_pin.attr('aria-label', 'Unpin Thread');
            instructor_pin.children().removeClass('golden_hover').addClass('reverse_golden_hover');
        }
        else {
            announcement_icon = '<i class="fas fa-thumbtack active-thread-announcement" title = "Pinned Thread" aria-label="Pinned Thread"></i>';
            $('#posts_list').find('h2').prepend(announcement_icon);
        }
    }
}

function socketUnpinThreadHandler(thread_id) {
    const thread_to_unpin = `[data-thread_id='${thread_id}']`;

    const hr = $(thread_to_unpin).next(); // saving the <hr> for inserting later below the thread div
    hr.remove(); // removing this sibling <hr>

    const not_pinned_threads = $('.thread_box').not($('.thread-announcement').parent()).parent();
    // if there exists other threads that are not pinned
    if (not_pinned_threads.length) {
        // if thread is bookmarked
        if ($(thread_to_unpin).find('.thread-favorite').length !== 0) {
            // if there exists other threads that are bookmarked
            if (not_pinned_threads.find('.thread-favorite').length !== 0) {
                const bookmarked_threads_ids = not_pinned_threads.find('.thread-favorite').parent().parent().map(function () {
                    return Number($(this).attr('data-thread_id'));
                }).get();

                for (let i = 0; i < bookmarked_threads_ids.length; i++) {
                    if (bookmarked_threads_ids[i] < thread_id) {
                        // eslint-disable-next-line no-var
                        var thread_to_insert_before = `[data-thread_id='${bookmarked_threads_ids[i]}']`;
                        $(thread_to_unpin).insertBefore($(thread_to_insert_before)).hide().fadeIn('slow');
                        break;
                    }

                    if (i === bookmarked_threads_ids.length - 1) {
                        // eslint-disable-next-line no-var
                        var thread_to_insert_after = `[data-thread_id='${bookmarked_threads_ids[i]}']`;
                        $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
                    }
                }
            }
            // no other bookmarked threads -> insert thread at the beginning of not announced threads
            else {
                $(thread_to_unpin).insertBefore(not_pinned_threads.first()).hide().fadeIn('slow');
            }
        }
        // thread is not bookmarked
        else {
            // if there exists other threads that are neither bookmarked nor pinned
            const not_bookmarked_threads = not_pinned_threads.not($('.thread-favorite').parent().parent());
            if (not_bookmarked_threads.length) {
                const not_bookmarked_threads_ids = not_bookmarked_threads.map(function () {
                    return Number($(this).attr('data-thread_id'));
                }).get();

                for (let i = 0; i < not_bookmarked_threads_ids.length; i++) {
                    if (not_bookmarked_threads_ids[i] < thread_id) {
                        // eslint-disable-next-line no-var, no-redeclare
                        var thread_to_insert_before = `[data-thread_id='${not_bookmarked_threads_ids[i]}']`;
                        $(thread_to_unpin).insertBefore($(thread_to_insert_before)).hide().fadeIn('slow');
                        break;
                    }

                    if (i === not_bookmarked_threads_ids.length - 1) {
                        // eslint-disable-next-line no-var, no-redeclare
                        var thread_to_insert_after = `[data-thread_id='${not_bookmarked_threads_ids[i]}']`;
                        $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
                    }
                }
            }
            // no other threads -> insert thread at the end
            else {
                // eslint-disable-next-line no-var, no-redeclare
                var thread_to_insert_after = $('.thread_box').last().parent();
                $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
            }
        }
    }
    // no unpinned threads -> insert thread at the end
    else {
        // eslint-disable-next-line no-var, no-redeclare
        var thread_to_insert_after = $('.thread_box').last().parent();
        $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn('slow');
    }

    $(hr).insertAfter($(thread_to_unpin)); // insert <hr> right after thread div
    $(thread_to_unpin).find('.thread-announcement').remove();

    // if user's current thread is the one modified -> update
    // eslint-disable-next-line eqeqeq
    if ($('#current-thread').val() == thread_id) {
    // if is instructor
        const instructor_pin = $('.active-thread-remove-announcement');
        if (instructor_pin.length) {
            instructor_pin.removeClass('active-thread-remove-announcement').addClass('not-active-thread-announcement');
            instructor_pin.attr('onClick', instructor_pin.attr('onClick').replace('0,', '1,').replace('unpin this thread?', 'pin this thread to the top?'));
            instructor_pin.attr('title', 'Make thread an announcement');
            instructor_pin.attr('aria-label', 'Pin Thread');
            instructor_pin.children().removeClass('reverse_golden_hover').addClass('golden_hover');
        }
        else {
            $('.active-thread-announcement').remove();
        }
    }
}

// eslint-disable-next-line no-unused-vars
function initSocketClient() {
    // eslint-disable-next-line no-undef
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        switch (msg.type) {
            case 'new_thread':
                socketNewOrEditThreadHandler(msg.thread_id);
                break;
            case 'delete_thread':
                socketDeleteOrMergeThreadHandler(msg.thread_id);
                break;
            case 'resolve_thread':
                socketResolveThreadHandler(msg.thread_id);
                break;
            case 'announce_thread':
                socketAnnounceThreadHandler(msg.thread_id);
                break;
            case 'unpin_thread':
                socketUnpinThreadHandler(msg.thread_id);
                break;
            case 'merge_thread':
                socketDeleteOrMergeThreadHandler(msg.thread_id, true, msg.merge_thread_id);
                break;
            case 'new_post':
                // eslint-disable-next-line eqeqeq
                if ($('data#current-thread').val() == msg.thread_id) {
                    socketNewOrEditPostHandler(msg.post_id, msg.reply_level, msg.post_box_id);
                }
                break;
            case 'delete_post':
                // eslint-disable-next-line eqeqeq
                if ($('data#current-thread').val() == msg.thread_id) {
                    socketDeletePostHandler(msg.post_id);
                }
                break;
            case 'edit_post':
                // eslint-disable-next-line eqeqeq
                if ($('data#current-thread').val() == msg.thread_id) {
                    socketNewOrEditPostHandler(msg.post_id, msg.reply_level, msg.post_box_id, true);
                }
                break;
            case 'edit_thread':
                // eslint-disable-next-line eqeqeq
                if ($('data#current-thread').val() == msg.thread_id) {
                    socketNewOrEditPostHandler(msg.post_id, msg.reply_level, msg.post_box_id, true);
                }
                socketNewOrEditThreadHandler(msg.thread_id, true);
                break;
            case 'split_post':
                // eslint-disable-next-line eqeqeq
                if ($('data#current-thread').val() == msg.thread_id) {
                    socketDeletePostHandler(msg.post_id);
                }
                socketNewOrEditThreadHandler(msg.new_thread_id, false);
                break;
            case 'edit_likes':
                updateLikesDisplay(msg.post_id, {
                    likesCount: msg.likesCount,
                    likesFromStaff: msg.likesFromStaff,
                    status: msg.status,
                });
                break;
            default:
                console.log('Undefined message received.');
        }
        thread_post_handler();
        loadThreadHandler();
    };
    window.socketClient.open('discussion_forum');
}

// eslint-disable-next-line no-unused-vars
function changeThreadStatus(thread_id) {
    const url = `${buildCourseUrl(['forum', 'threads', 'status'])}?status=1`;
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            thread_id: thread_id,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }

            window.location.reload();
            displaySuccessMessage('Thread marked as resolved.');
        },
        error: function () {
            window.alert('Something went wrong when trying to mark this thread as resolved. Please try again.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function modifyOrSplitPost(e) {
    e.preventDefault();
    // eslint-disable-next-line no-var
    const form = $(this);
    const formData = new FormData(form[0]);
    formData.append('deleted_attachments', JSON.stringify(getDeletedAttachments()));
    const files = testAndGetAttachments(1, false);
    if (files === false) {
        return false;
    }
    for (let i = 0; i < files.length; i++) {
        formData.append('file_input[]', files[i], files[i].name);
    }
    const submit_url = form.attr('action');

    $.ajax({
        url: submit_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(response);
            }
            catch (e) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }

            // modify
            if (form.attr('id') === 'thread_form') {
                window.location.reload();
            }
            // split
            else {
                window.location.replace(json['data']['next']);
            }
        },
    });
}

// eslint-disable-next-line no-unused-vars
function showEditPostForm(post_id, thread_id, shouldEditThread, render_markdown, csrf_token) {
    const DateTime = luxon.DateTime;
    if (!checkAreYouSureForm()) {
        return;
    }
    const form = $('#thread_form');
    const url = buildCourseUrl(['forum', 'posts', 'get']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            post_id: post_id,
            thread_id: thread_id,
            render_markdown: render_markdown,
            csrf_token: csrf_token,
        },
        success: function (data) {
            $('body').css('overflow', 'hidden');
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            json = json['data'];
            const post_content = json.post;
            const lines = post_content.split(/\r|\r\n|\n/).length;
            const anon = json.anon;
            const change_anon = json.change_anon;
            const user_id = escapeSpecialChars(json.user);
            const validIsoString = json.post_time.replace(' ', 'T');
            let time = DateTime.fromISO(json.validIsoString, { zone: 'local' });
            if (!time.isValid) {
                // Timezone suffix ":00" might be missing
                time = DateTime.fromISO(`${validIsoString}:00`, { zone: 'local' });
            }
            const categories_ids = json.categories_ids;
            const date = time.toLocaleString(DateTime.DATE_SHORT);
            const timeString = time.toLocaleString(DateTime.TIME_SIMPLE);
            const contentBox = form.find('[name=thread_post_content]')[0];
            contentBox.style.height = lines * 14;
            const editUserPrompt = document.getElementById('edit_user_prompt');
            editUserPrompt.innerHTML = `Editing a post by: ${user_id} on ${date} at ${timeString}`;
            contentBox.value = post_content;
            document.getElementById('edit_post_id').value = post_id;
            document.getElementById('edit_thread_id').value = thread_id;
            if (change_anon) {
                $('#thread_post_anon_edit').prop('checked', anon);
            }
            else {
                $('label[for=Anon]').remove();
                $('#thread_post_anon_edit').remove();
            }
            $('#edit-user-post').css('display', 'block');
            // eslint-disable-next-line no-undef
            captureTabInModal('edit-user-post');
            $('.cat-buttons input').prop('checked', false);
            if (json.markdown === true) {
                $('#markdown_input_').val('1');
                $('#markdown_toggle_').addClass('markdown-active');
                $('#markdown_buttons_').show();
            }
            else {
                $('#markdown_input_').val('0');
                $('#markdown_toggle_').removeClass('markdown-active');
                $('#markdown_buttons_').hide();
            }
            $('#img-table-loc').append(json.img_table);
            $('.display-attachment-name').each(function () {
                $(this).text(decodeURIComponent($(this).text()));
            });

            // If first post of thread
            if (shouldEditThread) {
                const thread_title = json.title;
                const thread_lock_date = json.lock_thread_date;
                const thread_status = json.thread_status;
                let expiration = json.expiration.replace('-', '/');
                expiration = expiration.replace('-', '/');
                $('#title').prop('disabled', false);
                $('.edit_thread').show();
                $('#label_lock_thread').show();
                $('#title').val(thread_title);
                $('#thread_status').val(thread_status);
                $('#lock_thread_date').val(thread_lock_date);
                if (Date.parse(expiration) > new Date()) {
                    $('.expiration').show();
                }
                $('#expirationDate').val(json.expiration);
                // Categories
                $('.cat-buttons').removeClass('btn-selected');
                $.each(categories_ids, (index, category_id) => {
                    const cat_input = $(`.cat-buttons input[value=${category_id}]`);
                    cat_input.prop('checked', true);
                    cat_input.parent().addClass('btn-selected');
                });
                $('.cat-buttons').trigger('eventChangeCatClass');
                $('#thread_form').prop('ignore-cat', false);
                $('#category-selection-container').show();
                $('#thread_status').show();
            }
            else {
                $('#title').prop('disabled', true);
                $('.edit_thread').hide();
                $('.expiration').hide();
                $('#label_lock_thread').hide();
                $('#thread_form').prop('ignore-cat', true);
                $('#category-selection-container').hide();
                $('#thread_status').hide();
            }
        },
        error: function () {
            window.alert('Something went wrong while trying to edit the post. Please try again.');
        },
    });
}

function markForDeletion(ele) {
    $(ele).attr('class', 'btn btn-danger');
    $(ele).attr('onclick', 'unMarkForDeletion(this)');
    $(ele).text('Keep');
}

function unMarkForDeletion(ele) {
    $(ele).attr('class', 'btn btn-default');
    $(ele).attr('onclick', 'markForDeletion(this)');
    $(ele).text('Delete');
}

// eslint-disable-next-line no-unused-vars
function cancelEditPostForum() {
    if (!checkAreYouSureForm()) {
        return;
    }
    const markdown_header = $('#markdown_header_0');
    const edit_button = markdown_header.find('.markdown-write-mode');
    if (markdown_header.attr('data-mode') === 'preview') {
        edit_button.trigger('click');
    }
    $('#edit-user-post').css('display', 'none');
    $(this).closest('.thread-post-form').find('[name=thread_post_content]').val('');
    $('#title').val('');
    $('body').css('overflow', 'auto');
    $('#display-existing-attachments').remove();
}

// eslint-disable-next-line no-unused-vars
function changeDisplayOptions(option) {
    // eslint-disable-next-line no-undef
    thread_id = $('#current-thread').val();
    Cookies.set('forum_display_option', option);
    // eslint-disable-next-line no-undef
    window.location.replace(`${buildCourseUrl(['forum', 'threads', thread_id])}?option=${option}`);
}

function readCategoryValues() {
    const categories_value = [];
    $('#thread_category button').each(function () {
        if ($(this).data('btn-selected') === 'true') {
            categories_value.push($(this).data('cat_id'));
        }
    });
    return categories_value;
}

function readThreadStatusValues() {
    const thread_status_value = [];
    $('#thread_status_select button').each(function () {
        if ($(this).data('btn-selected') === 'true') {
            thread_status_value.push($(this).data('sel_id'));
        }
    });
    return thread_status_value;
}

function dynamicScrollLoadPage(element, atEnd) {
    const load_page = $(element).data(atEnd ? 'next_page' : 'prev_page');
    if (load_page === -1) {
        return false;
    }
    if ($(element).data('dynamic_lock_load')) {
        return null;
    }
    let load_page_callback;
    let load_page_fail_callback;
    const arrow_up = $(element).find('.fa-caret-up');
    const arrow_down = $(element).find('.fa-caret-down');
    const spinner_up = arrow_up.prev();
    const spinner_down = arrow_down.next();
    $(element).data('dynamic_lock_load', true);
    if (atEnd) {
        arrow_down.hide();
        spinner_down.show();
        load_page_callback = function (content, count) {
            spinner_down.hide();
            arrow_down.before(content);
            // eslint-disable-next-line eqeqeq
            if (count == 0) {
                // Stop further loads
                $(element).data('next_page', -1);
            }
            else {
                $(element).data('next_page', parseInt(load_page) + 1);
                arrow_down.show();
            }
            dynamicScrollLoadIfScrollVisible($(element));
        };
        // eslint-disable-next-line no-unused-vars
        load_page_fail_callback = function (content, count) {
            spinner_down.hide();
        };
    }
    else {
        arrow_up.hide();
        spinner_up.show();
        load_page_callback = function (content, count) {
            spinner_up.hide();
            arrow_up.after(content);
            if (count === 0) {
                // Stop further loads
                $(element).data('prev_page', -1);
            }
            else {
                const prev_page = parseInt(load_page) - 1;
                $(element).data('prev_page', prev_page);
                if (prev_page >= 0) {
                    arrow_up.show();
                }
            }
            dynamicScrollLoadIfScrollVisible($(element));
        };
        // eslint-disable-next-line no-unused-vars
        load_page_fail_callback = function (content, count) {
            spinner_up.hide();
        };
    }

    const urlPattern = $(element).data('urlPattern');
    const currentThreadId = $(element).data('currentThreadId');
    const currentCategoriesId = $(element).data('currentCategoriesId');
    // eslint-disable-next-line no-unused-vars
    const course = $(element).data('course');

    const next_url = urlPattern.replace('{{#}}', load_page);

    let categories_value = readCategoryValues();
    let thread_status_value = readThreadStatusValues();

    // var thread_status_value = $("#thread_status_select").val();
    const unread_select_value = $('#unread').is(':checked');
    // eslint-disable-next-line eqeqeq
    categories_value = (categories_value == null) ? '' : categories_value.join('|');
    // eslint-disable-next-line eqeqeq
    thread_status_value = (thread_status_value == null) ? '' : thread_status_value.join('|');
    $.ajax({
        url: next_url,
        type: 'POST',
        data: {
            thread_categories: categories_value,
            thread_status: thread_status_value,
            unread_select: unread_select_value,
            scroll_down: atEnd,
            currentThreadId: currentThreadId,
            currentCategoriesId: currentCategoriesId,
            csrf_token: window.csrfToken,
        },
        success: function (r) {
            const x = JSON.parse(r)['data'];
            let content = x.html;
            const count = x.count;
            content = `${content}`;
            $(element).data('dynamic_lock_load', false);
            load_page_callback(content, count);
        },
        error: function () {
            $(element).data('dynamic_lock_load', false);
            load_page_fail_callback();
            window.alert('Something went wrong while trying to load more threads. Please try again.');
        },
    });
    return true;
}

function dynamicScrollLoadIfScrollVisible(jElement) {
    if (jElement[0].scrollHeight <= jElement[0].clientHeight) {
        if (dynamicScrollLoadPage(jElement[0], true) === false) {
            dynamicScrollLoadPage(jElement[0], false);
        }
    }
}

// eslint-disable-next-line no-unused-vars
function dynamicScrollContentOnDemand(jElement, urlPattern, currentThreadId, currentCategoriesId, course) {
    jElement.data('urlPattern', urlPattern);
    jElement.data('currentThreadId', currentThreadId);
    jElement.data('currentCategoriesId', currentCategoriesId);
    jElement.data('course', course);

    dynamicScrollLoadIfScrollVisible(jElement);
    $(jElement).scroll(function () {
        const element = $(this)[0];
        const sensitivity = 2;
        const isTop = element.scrollTop < sensitivity;
        const isBottom = (element.scrollHeight - element.offsetHeight - element.scrollTop) < sensitivity;
        if (isTop) {
            if ($(element).data('prev_page') !== -1) {
                element.scrollTop = sensitivity;
            }
            dynamicScrollLoadPage(element, false);
        }
        else if (isBottom) {
            dynamicScrollLoadPage(element, true);
        }
    });
}

// eslint-disable-next-line no-unused-vars
function resetScrollPosition(id) {
    // eslint-disable-next-line eqeqeq
    if (sessionStorage.getItem(`${id}_scrollTop`) != 0) {
        sessionStorage.setItem(`${id}_scrollTop`, 0);
    }
}

function saveScrollLocationOnRefresh(id) {
    const element = document.getElementById(id);
    $(element).scroll(() => {
        sessionStorage.setItem(`${id}_scrollTop`, $(element).scrollTop());
    });
    $(document).ready(() => {
        if (sessionStorage.getItem(`${id}_scrollTop`) !== null) {
            $(element).scrollTop(sessionStorage.getItem(`${id}_scrollTop`));
        }
    });
}

function checkAreYouSureForm() {
    const elements = $('form');
    if (elements.hasClass('dirty')) {
        if (confirm('You have unsaved changes! Do you want to continue?')) {
            elements.trigger('reinitialize.areYouSure');
            return true;
        }
        else {
            return false;
        }
    }
    return true;
}

// eslint-disable-next-line no-unused-vars
function alterShowDeletedStatus(newStatus) {
    if (!checkAreYouSureForm()) {
        return;
    }
    Cookies.set('show_deleted', newStatus, { path: '/' });
    location.reload();
}

// eslint-disable-next-line no-unused-vars
function alterShowMergeThreadStatus(newStatus, course) {
    if (!checkAreYouSureForm()) {
        return;
    }
    Cookies.set(`${course}_show_merged_thread`, newStatus, { path: '/' });
    location.reload();
}

// eslint-disable-next-line no-unused-vars
function modifyThreadList(currentThreadId, currentCategoriesId, course, loadFirstPage, success_callback) {
    let categories_value = readCategoryValues();
    let thread_status_value = readThreadStatusValues();

    const unread_select_value = $('#unread').is(':checked');
    // eslint-disable-next-line eqeqeq
    categories_value = (categories_value == null) ? '' : categories_value.join('|');
    // eslint-disable-next-line eqeqeq
    thread_status_value = (thread_status_value == null) ? '' : thread_status_value.join('|');
    Cookies.set(`${course}_forum_categories`, categories_value, { path: '/' });
    Cookies.set('forum_thread_status', thread_status_value, { path: '/' });
    Cookies.set('unread_select_value', unread_select_value, { path: '/' });
    const url = `${buildCourseUrl(['forum', 'threads'])}?page_number=${(loadFirstPage ? '0' : '-1')}`;
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            thread_categories: categories_value,
            thread_status: thread_status_value,
            unread_select: unread_select_value,
            currentThreadId: currentThreadId,
            currentCategoriesId: currentCategoriesId,
            csrf_token: csrfToken,
        },
        success: function (r) {
            let x = JSON.parse(r)['data'];
            const page_number = parseInt(x.page_number);
            const threadCount = parseInt(x.count);
            x = x.html;
            x = `${x}`;
            const jElement = $('#thread_list');
            jElement.children(':not(.fas)').remove();
            $('#thread_list .fa-caret-up').after(x);
            jElement.data('prev_page', page_number - 1);
            jElement.data('next_page', page_number + 1);
            jElement.data('dynamic_lock_load', false);
            $('#thread_list .fa-spinner').hide();
            if (loadFirstPage) {
                $('#thread_list .fa-caret-up').hide();
                $('#thread_list .fa-caret-down').show();
            }
            else {
                $('#thread_list .fa-caret-up').show();
                $('#thread_list .fa-caret-down').hide();
            }

            $('#num_filtered').text(threadCount);

            dynamicScrollLoadIfScrollVisible(jElement);
            loadThreadHandler();
            // eslint-disable-next-line eqeqeq
            if (success_callback != null) {
                success_callback();
            }
        },
        error: function () {
            window.alert('Something went wrong when trying to filter. Please try again.');
            Cookies.remove(`${course}_forum_categories`, { path: '/' });
            Cookies.remove('forum_thread_status', { path: '/' });
        },
    });
}

function toggleLike(post_id, current_user) {
    // eslint-disable-next-line no-undef
    const url = buildCourseUrl(['posts', 'likes']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            post_id: post_id,
            current_user: current_user,
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: function (data) {
            let json;
            try {
                json = JSON.parse(data);
            }
            catch (err) {
                // eslint-disable-next-line no-undef
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                // eslint-disable-next-line no-undef
                displayErrorMessage(json['message']);
                return;
            }
            else {
                updateLikesDisplay(post_id, json.data);
            }
        },
        error: function (err) {
            console.log(err);
        },
    });
}

function updateLikesDisplay(post_id, data) {
    const likes = data['likesCount'];
    const liked = data['status'];
    const staffLiked = data['likesFromStaff'];

    const likeCounterElement = document.getElementById(`likeCounter_${post_id}`);
    let likeCounter = parseInt(likeCounterElement.innerText);

    // eslint-disable-next-line no-useless-concat
    const likeIconSrc = document.getElementById(`likeIcon_${post_id}`);
    let likeIconSrcElement = likeIconSrc.src;

    if (liked === 'unlike') {
        likeIconSrcElement = likeIconSrcElement.replace('on-duck-button.svg', 'light-mode-off-duck.svg');
    }
    else if (liked === 'like') {
        likeIconSrcElement = likeIconSrcElement.replace('light-mode-off-duck.svg', 'on-duck-button.svg');
    }

    if (staffLiked > 0) {
        $(`#likedByInstructor_${post_id}`).show();
    }
    else {
        $(`#likedByInstructor_${post_id}`).hide();
    }

    likeCounter = likes;
    likeIconSrc.src = likeIconSrcElement; // Update the state
    likeCounterElement.innerText = likeCounter;
}

function displayHistoryAttachment(edit_id) {
    $(`#history-table-${edit_id}`).toggle();
    $(`#history-table-${edit_id}`).find('.attachment-name-history').each(function () {
        $(this).text(decodeURIComponent($(this).text()));
    });
}

// eslint-disable-next-line no-unused-vars
function replyPost(post_id) {
    if ($(`#${post_id}-reply`).css('display') === 'block') {
        $(`#${post_id}-reply`).css('display', 'none');
    }
    else {
        hideReplies();
        $(`#${post_id}-reply`).css('display', 'block');
    }
}

function generateCodeMirrorBlocks(container_element) {
    const codeSegments = container_element.querySelectorAll('.code');
    for (const element of codeSegments) {
        // eslint-disable-next-line no-undef
        const editor0 = CodeMirror.fromTextArea(element, {
            lineNumbers: true,
            readOnly: true,
            cursorHeight: 0.0,
            lineWrapping: true,
            autoRefresh: true,
        });

        const lineCount = editor0.lineCount();
        if (lineCount === 1) {
            editor0.setSize('100%', `${editor0.defaultTextHeight() * 2}px`);
        }
        else {
            // Default height for CodeMirror is 300px... 500px looks good
            const h = (editor0.defaultTextHeight()) * lineCount + 15;
            editor0.setSize('100%', `${h > 500 ? 500 : h}px`);
        }

        editor0.setOption('theme', 'eclipse');
        editor0.refresh();
    }
}

// eslint-disable-next-line no-unused-vars
function showSplit(post_id) {
    //  If a thread was merged in the database earlier, we want to reuse the thread id and information
    //  so we don't have any loose ends
    const url = buildCourseUrl(['forum', 'posts', 'splitinfo']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            post_id: post_id,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            json = json['data'];
            if (json['merged_thread_id'] === -1) {
                document.getElementById('split_post_previously_merged').style.display = 'none';
                document.getElementById('split_post_submit').disabled = true;
            }
            else {
                document.getElementById('split_post_previously_merged').style.display = 'block';
                document.getElementById('split_post_submit').disabled = false;
                // eslint-disable-next-line no-undef
                captureTabInModal('popup-post-split', false);
            }
            document.getElementById('split_post_input').value = json['title'];
            document.getElementById('split_post_id').value = post_id;
            let i;
            for (i = 0; i < json['all_categories_list'].length; i++) {
                const id = json['all_categories_list'][i]['category_id'];
                const target = `#split_post_category_${id}`;
                if (json['categories_list'].includes(id)) {
                    if (!($(target).hasClass('btn-selected'))) {
                        $(target).addClass('btn-selected').trigger('eventChangeCatClass');
                        $(target).find("input[type='checkbox']").prop('checked', true);
                    }
                }
                else {
                    if ($(target).hasClass('btn-selected')) {
                        $(target).removeClass('btn-selected').trigger('eventChangeCatClass');
                        $(target).find("input[type='checkbox']").prop('checked', false);
                    }
                }
            }
            $('#popup-post-split').show();
            // eslint-disable-next-line no-undef
            captureTabInModal('popup-post-split');
        },
        error: function () {
            window.alert('Something went wrong while trying to get post information for splitting. Try again later.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function showHistory(post_id) {
    const url = buildCourseUrl(['forum', 'posts', 'history']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            post_id: post_id,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            $('#popup-post-history').show();
            // eslint-disable-next-line no-undef
            captureTabInModal('popup-post-history');
            $('#popup-post-history .post_box.history_box').remove();
            $('#popup-post-history .form-body').css('padding', '5px');
            const dummy_box = $($('#popup-post-history .post_box')[0]);
            json = json['data'];
            for (let i = json.length - 1; i >= 0; i -= 1) {
                const post = json[i];
                // eslint-disable-next-line no-undef
                box = dummy_box.clone();
                // eslint-disable-next-line no-undef
                box.show();
                // eslint-disable-next-line no-undef
                box.addClass('history_box');
                // eslint-disable-next-line no-undef
                box.find('.post_content').html(post['content']);
                if (post.is_staff_post) {
                    // eslint-disable-next-line no-undef
                    if (box.hasClass('new_post')) {
                        // eslint-disable-next-line no-undef
                        box.addClass('important-new');
                    }
                    else {
                        // eslint-disable-next-line no-undef
                        box.addClass('important');
                    }
                }

                const given_name = post['user_info']['given_name'].trim();
                const family_name = post['user_info']['family_name'].trim();
                const author_user_id = post['user'];
                const visible_username = `${given_name} ${(family_name.length === 0) ? '' : (`${family_name.substr(0, 1)}.`)}`;
                let info_name = `${given_name} ${family_name} (${author_user_id})`;
                const visible_user_json = JSON.stringify(visible_username);
                info_name = JSON.stringify(info_name);
                let user_button_code = `<a style='margin-right:2px;display:inline-block; color:black;' onClick='changeName(this.parentNode, ${info_name}, ${visible_user_json}, false)' title='Show full user information'><i class='fas fa-eye' aria-hidden='true'></i></a>&nbsp;`;
                if (!author_user_id) {
                    user_button_code = '';
                }
                // eslint-disable-next-line no-undef
                box.find('span.edit_author').html(`<strong>${visible_username}</strong> ${post['post_time']}`);
                // eslint-disable-next-line no-undef
                box.find('span.edit_author').before(user_button_code);
                // eslint-disable-next-line no-undef
                $('#popup-post-history .form-body').prepend(box);
            }
            $('.history-attachment-table').hide();
            generateCodeMirrorBlocks($('#popup-post-history')[0]);
        },
        error: function () {
            window.alert('Something went wrong while trying to display post history. Please try again.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function addNewCategory(csrf_token) {
    const newCategory = $('#new_category_text').val();
    const visibleDate = $('#category_visible_date').val();
    const url = buildCourseUrl(['forum', 'categories', 'new']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            newCategory: newCategory,
            visibleDate: visibleDate,
            rank: $('[id^="categorylistitem-').length,
            csrf_token: csrf_token,
        },
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            // eslint-disable-next-line no-undef
            displaySuccessMessage(`Successfully created category ${escapeSpecialChars(newCategory)}.`);
            $('#new_category_text').val('');
            // Create new item in #ui-category-list using dummy category
            const category_id = json['data']['new_id'];
            const category_color_code = '#000080';
            // eslint-disable-next-line no-undef
            const category_desc = escapeSpecialChars(newCategory);
            // eslint-disable-next-line no-undef
            newelement = $($('#ui-category-template li')[0]).clone(true);
            // eslint-disable-next-line no-undef
            newelement.attr('id', `categorylistitem-${category_id}`);
            // eslint-disable-next-line no-undef
            newelement.css('color', category_color_code);
            // eslint-disable-next-line no-undef
            newelement.find('.categorylistitem-desc span').text(category_desc);
            // eslint-disable-next-line no-undef
            newelement.find('.category-color-picker').val(category_color_code);
            // eslint-disable-next-line no-undef
            newelement.show();
            // eslint-disable-next-line no-undef
            newelement.addClass('category-sortable');
            // eslint-disable-next-line no-undef
            newcatcolorpicker = newelement.find('.category-color-picker');
            // eslint-disable-next-line no-undef
            newcatcolorpicker.css('background-color', newcatcolorpicker.val());
            // eslint-disable-next-line no-undef
            $('#ui-category-list').append(newelement);
            $('.category-list-no-element').hide();
            refreshCategories();
            window.location.reload();
        },
        error: function () {
            window.alert('Something went wrong while trying to add a new category. Please try again.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function deleteCategory(category_id, category_desc, csrf_token) {
    const url = buildCourseUrl(['forum', 'categories', 'delete']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            deleteCategory: category_id,
            csrf_token: csrf_token,
        },
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            displaySuccessMessage(`Successfully deleted category ${escapeSpecialChars(category_desc)}.`);
            $(`#categorylistitem-${category_id}`).remove();
            refreshCategories();
        },
        error: function () {
            window.alert('Something went wrong while trying to add a new category. Please try again.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function editCategory(category_id, category_desc, category_color, category_date, changed, csrf_token) {
    if (category_desc === null && category_color === null && category_date === null) {
        return;
    }
    const data = { category_id: category_id, csrf_token: csrf_token };
    if (category_desc !== null && changed === 'desc') {
        data['category_desc'] = category_desc;
    }
    if (category_color !== null && changed === 'color') {
        data['category_color'] = category_color;
    }
    if (category_date !== null && changed === 'date') {
        if (category_date.trim() === '') {
            category_date = '    ';
        }

        data['visibleDate'] = category_date;
    }
    const url = buildCourseUrl(['forum', 'categories', 'edit']);
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again.');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            displaySuccessMessage(`Successfully updated category "${category_desc}"!`);
            setTimeout(() => {
                // eslint-disable-next-line no-undef
                removeMessagePopup('theid');
            }, 1000);
            if (category_color !== null) {
                $(`#categorylistitem-${category_id}`).css('color', category_color);
            }
            if (category_desc !== null) {
                $(`#categorylistitem-${category_id}`).find('.categorylistitem-desc span').text(category_desc);
            }
            if (category_date !== null) {
                $(`#categorylistitem-${category_id}`).find('.categorylistitemdate-desc span').text(category_date);
            }

            refreshCategories();
        },
        error: function () {
            window.alert('Something went wrong while trying to add a new category. Please try again.');
        },
    });
}

function refreshCategories() {
    if ($('#ui-category-list').length) {
        // Refresh cat-buttons from #ui-category-list

        let data = $('#ui-category-list').sortable('serialize');
        if (!data.trim()) {
            return;
        }
        data = data.split('&');
        const order = [];
        // eslint-disable-next-line no-var
        for (var i = 0; i < data.length; i += 1) {
            // eslint-disable-next-line no-var
            var category_id = parseInt(data[i].split('=')[1]);
            const category_desc = $(`#categorylistitem-${category_id} .categorylistitem-desc span`).text().trim();
            const category_color = $(`#categorylistitem-${category_id} select`).val();
            const category_diff = parseFloat($(`#categorylistitem-${category_id}`).data('diff'));
            const category_visible_date = $(`#categorylistitem-${category_id}`).data('visible_date');
            order.push([category_id, category_desc, category_color, category_diff, category_visible_date]);
        }

        // Obtain current selected category
        const selected_button = new Set();
        const category_pick_buttons = $('.cat-buttons');
        // eslint-disable-next-line no-var, no-redeclare
        for (var i = 0; i < category_pick_buttons.length; i += 1) {
            const cat_button_checkbox = $(category_pick_buttons[i]).find('input');
            // eslint-disable-next-line no-var, no-redeclare
            var category_id = parseInt(cat_button_checkbox.val());
            if (cat_button_checkbox.prop('checked')) {
                selected_button.add(category_id);
            }
        }

        // Refresh selected categories
        $('#categories-pick-list').empty();
        order.forEach((category) => {
            const category_visible_date = category[4];
            const category_diff = category[3];
            if (category_visible_date === '' || category_diff > 0) {
                const category_id = category[0];
                const category_desc = category[1];
                const category_color = category[2];
                let selection_class = '';
                if (selected_button.has(category_id)) {
                    selection_class = 'btn-selected';
                }
                const element = `<div tabindex="0" class="btn cat-buttons ${selection_class}" data-color="${category_color}">${category_desc}\
                                    <input aria-label="Category: ${category_desc}" type="checkbox" name="cat[]" value="${category_id}">\
                                </div>`;
                $('#categories-pick-list').append(element);
            }
        });

        $(".cat-buttons input[type='checkbox']").each(function () {
            if ($(this).parent().hasClass('btn-selected')) {
                $(this).prop('checked', true);
            }
        });
    }

    // Selectors for categories pick up
    // If JS enabled hide checkbox
    $('div.cat-buttons input').hide();

    $('.cat-buttons').click(function () {
        if ($(this).hasClass('btn-selected')) {
            $(this).removeClass('btn-selected');
            $(this).find("input[type='checkbox']").prop('checked', false);
        }
        else {
            $(this).addClass('btn-selected');
            $(this).find("input[type='checkbox']").prop('checked', true);
        }
        $(this).trigger('eventChangeCatClass');
    });

    $('.cat-buttons').bind('eventChangeCatClass', changeColorClass);
    $('.cat-buttons').trigger('eventChangeCatClass');
}

function changeColorClass() {
    const color = $(this).data('color');
    $(this).css('border-color', color);
    if ($(this).hasClass('btn-selected')) {
        $(this).css('background-color', color);
        $(this).css('color', 'white');
    }
    else {
        $(this).css('background-color', 'white');
        $(this).css('color', color);
    }
}

function reorderCategories(csrf_token) {
    let data = $('#ui-category-list').sortable('serialize');
    data += `&csrf_token=${csrf_token}`;
    const url = buildCourseUrl(['forum', 'categories', 'reorder']);
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again');
                return;
            }
            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }
            displaySuccessMessage('Successfully reordered categories.');
            setTimeout(() => {
                // eslint-disable-next-line no-undef
                removeMessagePopup('theid');
            }, 1000);
            refreshCategories();
        },
        error: function () {
            window.alert('Something went wrong while trying to reordering categories. Please try again.');
        },
    });
}

/* This function ensures that only one reply box is open at a time */
function hideReplies() {
    const hide_replies = document.getElementsByClassName('reply-box');
    for (let i = 0; i < hide_replies.length; i++) {
        hide_replies[i].style.display = 'none';
    }
}

// eslint-disable-next-line no-unused-vars
function deletePostToggle(isDeletion, thread_id, post_id, author, time, csrf_token) {
    if (!checkAreYouSureForm()) {
        return;
    }
    const type = (isDeletion ? '0' : '2');
    const message = (isDeletion ? 'delete' : 'restore');

    const confirm = window.confirm(`Are you sure you would like to ${message} this post?: \n\nWritten by:  ${author}  @  ${time}\n\nPlease note: The replies to this comment will also be ${message}d. \n\nIf you ${message} the first post in a thread this will ${message} the entire thread.`);
    if (confirm) {
        const url = `${buildCourseUrl(['forum', 'posts', 'modify'])}?modify_type=${type}`;
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                post_id: post_id,
                thread_id: thread_id,
                csrf_token: csrf_token,
            },
            success: function (data) {
                try {
                    // eslint-disable-next-line no-var
                    var json = JSON.parse(data);
                }
                catch (err) {
                    displayErrorMessage('Error parsing data. Please try again');
                    return;
                }
                if (json['status'] === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }
                let new_url = '';
                switch (json['data']['type']) {
                    case 'thread':
                        new_url = buildCourseUrl(['forum']);
                        break;
                    case 'post':
                        new_url = buildCourseUrl(['forum', 'threads', thread_id]);
                        break;
                    default:
                        new_url = buildCourseUrl(['forum']);
                        break;
                }
                window.location.replace(new_url);
            },
            error: function () {
                window.alert('Something went wrong while trying to delete/restore a post. Please try again.');
            },
        });
    }
}

// eslint-disable-next-line no-unused-vars
function alterAnnouncement(thread_id, confirmString, type, csrf_token) {
    const confirm = window.confirm(confirmString);
    if (confirm) {
        const url = `${buildCourseUrl(['forum', 'announcements'])}?type=${type}`;
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                thread_id: thread_id,
                csrf_token: csrf_token,

            },
            // eslint-disable-next-line no-unused-vars
            success: function (data) {
                window.location.reload();
            },
            error: function () {
                window.alert('Something went wrong while trying to remove announcement. Please try again.');
            },
        });
    }
}

// eslint-disable-next-line no-unused-vars
function bookmarkThread(thread_id, type) {
    const url = `${buildCourseUrl(['forum', 'threads', 'bookmark'])}?type=${type}`;
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            thread_id: thread_id,
            csrf_token: csrfToken,
        },
        // eslint-disable-next-line no-unused-vars
        success: function (data) {
            window.location.replace(buildCourseUrl(['forum', 'threads', thread_id]));
        },
        error: function () {
            window.alert('Something went wrong while trying to update the bookmark. Please try again.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function markThreadUnread(thread_id) {
    const url = `${buildCourseUrl(['forum', 'threads', 'unread'])}`;
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            thread_id: thread_id,
            csrf_token: csrfToken,
        },
        success: function () {
            $(`#thread_box_link_${thread_id}`).children().addClass('new_thread');
            $('.post_box').removeClass('viewed_post').addClass('new_post');
        },
        error: function () {
            window.alert('Something went wrong while trying to mark the thread as unread. Please try again.');
        },
    });
}

function getPostTimestamp(postId) {
    if (!postId) {
        return;
    }
    const postElement = document.getElementById(postId);

    const timestampElement = postElement.querySelector('.last-edit');
    return new Date(timestampElement.textContent.trim()).getTime();
}

function updateLaterPostsToViewed(unreadPostId) {
    const unreadPostTimestamp = getPostTimestamp(unreadPostId);

    const allPosts = document.querySelectorAll('.post_box');
    allPosts.forEach((post) => {
        const postId = post.id;
        const postTimestamp = getPostTimestamp(postId);

        if (postTimestamp >= unreadPostTimestamp) {
            post.classList.remove('viewed_post');
            post.classList.add('new_post');
        }
    });
}

function markPostUnread(thread_id, post_id, last_viewed_timestamp) {
    const url = `${buildCourseUrl(['forum', 'posts', 'unread'])}`;
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            thread_id: thread_id,
            last_viewed_timestamp: last_viewed_timestamp,
            csrf_token: csrfToken,
        },
        success: function () {
            $(`#thread_box_link_${thread_id}`).children().addClass('new_thread');
            $(`#${post_id}`).removeClass('viewed_post').addClass('new_post');
            updateLaterPostsToViewed(post_id);
        },
        error: function () {
            window.alert('Something went wrong while trying to mark the post as unread. Please try again.');
        },
    });
}

// eslint-disable-next-line no-unused-vars
function toggleMarkdown(post_box_id, triggered) {
    if (post_box_id === undefined) {
        post_box_id = '';
    }
    // display/hide the markdown header
    $(`#markdown_header_${post_box_id}`).toggle();
    $(this).toggleClass('markdown-active markdown-inactive');
    // if markdown has just been turned off, make sure we exit preview mode if it is active
    if ($(this).hasClass('markdown-inactive')) {
        const markdown_header = $(`#markdown_header_${post_box_id}`);
        const edit_button = markdown_header.find('.markdown-write-mode');
        if (markdown_header.attr('data-mode') === 'preview') {
            edit_button.trigger('click');
        }
    }
    // trigger this event for all other markdown toggle buttons (since the setting should be persistent)
    if (!triggered) {
        $('.markdown-toggle').not(this).each(function () {
            toggleMarkdown.call(this, this.id.split('_')[2], true);
        });
    }
    // set various settings related to new markdown state
    // eslint-disable-next-line eqeqeq
    $(`#markdown_input_${post_box_id}`).val($(`#markdown_input_${post_box_id}`).val() == 0 ? '1' : '0');
    $(`#markdown-info-${post_box_id}`).toggleClass('disabled');
    Cookies.set('markdown_enabled', $(`#markdown_input_${post_box_id}`).val(), { path: '/', expires: 365 });
}

// eslint-disable-next-line no-unused-vars
function checkInputMaxLength(obj) {
    // eslint-disable-next-line eqeqeq
    if ($(obj).val().length == $(obj).attr('maxLength')) {
        alert('Maximum input length reached!');
        $(obj).val($(obj).val().substr(0, $(obj).val().length));
    }
}

// eslint-disable-next-line no-unused-vars
function sortTable(sort_element_index, reverse = false) {
    const table = document.getElementById('forum_stats_table');
    let switching = true;
    while (switching) {
        switching = false;
        const rows = table.getElementsByTagName('TBODY');
        // eslint-disable-next-line no-var
        for (var i = 1; i < rows.length - 1; i++) {
            const a = rows[i].getElementsByTagName('TR')[0].getElementsByTagName('TD')[sort_element_index];
            const b = rows[i + 1].getElementsByTagName('TR')[0].getElementsByTagName('TD')[sort_element_index];
            if (reverse) {
                // eslint-disable-next-line eqeqeq
                if (sort_element_index == 0 ? a.innerHTML < b.innerHTML : parseInt(a.innerHTML) > parseInt(b.innerHTML)) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                }
            }
            else {
                // eslint-disable-next-line eqeqeq
                if (sort_element_index == 0 ? a.innerHTML > b.innerHTML : parseInt(a.innerHTML) < parseInt(b.innerHTML)) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                }
            }
        }
    }

    const row0 = table.getElementsByTagName('TBODY')[0].getElementsByTagName('TR')[0];
    const headers = row0.getElementsByTagName('TH');

    // eslint-disable-next-line no-var, no-redeclare
    for (var i = 0; i < headers.length; i++) {
        const index = headers[i].innerHTML.indexOf(' ↓');
        const reverse_index = headers[i].innerHTML.indexOf(' ↑');

        if (index > -1 || reverse_index > -1) {
            headers[i].innerHTML = headers[i].innerHTML.slice(0, -2);
        }
    }
    if (reverse) {
        headers[sort_element_index].innerHTML = `${headers[sort_element_index].innerHTML} ↑`;
    }
    else {
        headers[sort_element_index].innerHTML = `${headers[sort_element_index].innerHTML} ↓`;
    }
}

function loadThreadHandler() {
    $('a.thread_box_link').click(function (event) {
        // if a thread is clicked on the full-forum-page just follow normal GET request else continue with ajax request
        if (window.location.origin + window.location.pathname === buildCourseUrl(['forum'])) {
            return;
        }
        event.preventDefault();
        const obj = this;
        const thread_id = $(obj).data('thread_id');
        const thread_title = $(obj).data('thread_title');

        const url = buildCourseUrl(['forum', 'threads', thread_id]);
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                thread_id: thread_id,
                ajax: 'true',
                csrf_token: csrfToken,
            },
            success: function (data) {
                try {
                    // eslint-disable-next-line no-var
                    var json = JSON.parse(data);
                }
                catch (err) {
                    displayErrorMessage('Error parsing data. Please try again');
                    return;
                }
                if (json['status'] === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }
                if (typeof json.data.merged !== 'undefined') {
                    window.location.replace(json.data.destination);
                    return;
                }
                $(obj).find('.thread_box').removeClass('new_thread');
                $(obj).find('.thread_box').removeClass('deleted-unviewed');

                $('.thread_box').removeClass('active');

                $(obj).children('div.thread_box').addClass('active');

                $('#posts_list').empty().html(JSON.parse(json.data.html));

                window.history.pushState({ pageTitle: document.title }, '', url);
                // Updates the title and breadcrumb
                $(document).attr('title', thread_title);
                if (thread_title.length > 25) {
                    $('h1.breadcrumb-heading').text(`${thread_title.slice(0, 25)}...`);
                }
                else {
                    $('h1.breadcrumb-heading').text(thread_title);
                }

                setupForumAutosave();
                saveScrollLocationOnRefresh('posts_list');

                $('.post_reply_form').submit(publishPost);
                hljs.highlightAll();
            },
            error: function () {
                window.alert('Something went wrong while trying to display thread details. Please try again.');
            },
        });
    });
}

// eslint-disable-next-line no-unused-vars
function loadAllInlineImages(open_override = false) {
    const toggleButton = $('#toggle-attachments-button');

    const allShown = $('.attachment-well').filter(function () {
        return $(this).is(':visible');
    }).length === $('.attachment-well').length;
    // if the button were to show them all but they have all been individually shown,
    // we should hide them all
    if (allShown && toggleButton.hasClass('show-all')) {
        toggleButton.removeClass('show-all');
    }

    const allHidden = $('.attachment-well').filter(function () {
        return !($(this).is(':visible'));
    }).length === $('.attachment-well').length;
    // if the button were to hide them all but they have all been individually hidden,
    // we should show them all
    if (allHidden && !(toggleButton.hasClass('show-all'))) {
        toggleButton.addClass('show-all');
    }

    $('.attachment-btn').each(function (i) {
        $(this).click();

        // overwrite individual button click behavior to decide if it should be shown/hidden
        if (toggleButton.hasClass('show-all') || open_override) {
            $('.attachment-well').eq(i).show();
        }
        else {
            $('.attachment-well').eq(i).hide();
        }
    });

    toggleButton.toggleClass('show-all');
}

// eslint-disable-next-line no-unused-vars
function loadInlineImages(encoded_data) {
    const data = JSON.parse(encoded_data);
    const attachment_well = $(`#${data[data.length - 1]}`);

    if (attachment_well.is(':visible')) {
        attachment_well.hide();
    }
    else {
        attachment_well.show();
    }

    // if they're no images loaded for this well
    if (attachment_well.children().length === 0) {
    // add image tags
        for (let i = 0; i < data.length - 1; i++) {
            const attachment = data[i];
            const url = attachment[0];
            const img = $(`<img src="${url}" alt="Click to view attachment in popup" title="Click to view attachment in popup" class="attachment-img">`);
            const title = $(`<p>${escapeSpecialChars(decodeURI(attachment[2]))}</p>`);
            img.click(function () {
                const url = $(this).attr('src');
                window.open(url, '_blank', 'toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600');
            });
            attachment_well.append(img);
            attachment_well.append(title);
        }
    }
}

// eslint-disable-next-line no-unused-vars
function openInWindow(img) {
    const url = $(img).attr('src');
    window.open(url, '_blank', 'toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600');
}

// eslint-disable-next-line no-unused-vars, no-var
var filters_applied = [];

// Taken from https://stackoverflow.com/a/1988361/2650341

if (!Array.prototype.inArray) {
    Object.defineProperty(Array.prototype, 'inArray', {
        value: function (comparer) {
            for (let i = 0; i < this.length; i++) {
                if (comparer(this[i])) {
                    return i;
                }
            }
            return false;
        },
    });
}

// adds an element to the array if it does not already exist using a comparer
// function
if (!Array.prototype.toggleElement) {
    Object.defineProperty(Array.prototype, 'toggleElement', {
        value: function (element, comparer) {
            const index = this.inArray(comparer);
            // eslint-disable-next-line valid-typeof
            if ((typeof (index) === 'boolean' && !index) || (typeof (index) === 'int' && index === 0)) {
                this.push(element);
            }
            else {
                this.splice(index, 1);
            }
        },
    });
}

function clearForumFilter() {
    if (checkUnread()) {
        $('#filter_unread_btn').click();
    }
    window.filters_applied = [];
    $('#thread_category button, #thread_status_select button').data('btn-selected', 'false').removeClass('filter-active').addClass('filter-inactive');
    $('#filter_unread_btn').removeClass('filter-active').addClass('filter-inactive');
    $('#clear_filter_button').hide();

    // eslint-disable-next-line no-undef
    updateThreads(true, null);
    return false;
}

// eslint-disable-next-line no-unused-vars
function loadFilterHandlers() {
    // eslint-disable-next-line no-unused-vars
    $('#filter_unread_btn').mousedown(function (e) {
        $(this).toggleClass('filter-inactive filter-active');
    });

    $('#thread_category button, #thread_status_select button').mousedown(function (e) {
        e.preventDefault();
        const current_selection = $(this).data('btn-selected');

        if (current_selection === 'true') {
            $(this).data('btn-selected', 'false').removeClass('filter-active').addClass('filter-inactive');
        }
        else {
            $(this).data('btn-selected', 'true').removeClass('filter-inactive').addClass('filter-active');
        }

        const filter_text = $(this).text();

        window.filters_applied.toggleElement(filter_text, (e) => {
            return e === filter_text;
        });

        if (window.filters_applied.length === 0) {
            clearForumFilter();
        }
        else {
            $('#clear_filter_button').css('display', 'inline-block');
        }
        // eslint-disable-next-line no-undef
        updateThreads(true, null);
        return true;
    });

    $('#unread').change((e) => {
        e.preventDefault();
        // eslint-disable-next-line no-undef
        updateThreads(true, null);
        checkUnread();
        return true;
    });
}

function thread_post_handler() {
    // eslint-disable-next-line no-unused-vars
    $('.submit_unresolve').click(function (event) {
        const post_box_id = $(this).data('post_box_id');
        $(`#thread_status_input_${post_box_id}`).val(-1);
        return true;
    });
}

// eslint-disable-next-line no-unused-vars
function forumFilterBar() {
    $('#forum_filter_bar').toggle();
}

function getDeletedAttachments() {
    const deleted_attachments = [];
    $('#display-existing-attachments').find('a.btn.btn-danger').each(function () {
        deleted_attachments.push(decodeURIComponent($(this).attr('id').substring(7)));
    });
    return deleted_attachments;
}

function updateThread(e) {
    // Only proceed if its full forum page
    if (buildCourseUrl(['forum']) !== window.location.origin + window.location.pathname) {
        return;
    }

    e.preventDefault();
    const cat = [];
    $('input[name="cat[]"]:checked').each((item) => cat.push($('input[name="cat[]"]:checked')[item].value));

    const form = $(this);
    const formData = new FormData(form[0]);
    formData.append('deleted_attachments', JSON.stringify(getDeletedAttachments()));

    const files = testAndGetAttachments(1, false);
    if (files === false) {
        return false;
    }

    for (let i = 0; i < files.length; i++) {
        formData.append('file_input[]', files[i], files[i].name);
    }

    $.ajax({
        url: `${buildCourseUrl(['forum', 'posts', 'modify'])}?modify_type=1`,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            try {
                response = JSON.parse(response);
                if (response.status === 'success') {
                    displaySuccessMessage('Thread post updated successfully!');
                }
                else {
                    displayErrorMessage('Failed to update thread post');
                }
            }
            catch (e) {
                console.log(e);
                displayErrorMessage('Something went wrong while updating thread post');
            }
            window.location.reload();
        },
        error: function (err) {
            console.log(err);
            displayErrorMessage('Something went wrong while updating thread post');
            window.location.reload();
        },
    });
}

function checkUnread() {
    if ($('#unread').prop('checked')) {
        // eslint-disable-next-line no-undef
        unread_marked = true;
        $('#filter_unread_btn').removeClass('filter-inactive').addClass('filter-active');
        $('#clear_filter_button').css('display', 'inline-block');
        return true;
    }
    else {
        return false;
    }
}

// Used to update thread content in the "Merge Thread"
// modal.
// eslint-disable-next-line no-unused-vars
function updateSelectedThreadContent(selected_thread_first_post_id) {
    const url = buildCourseUrl(['forum', 'posts', 'get']);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            post_id: selected_thread_first_post_id,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                // eslint-disable-next-line no-var
                var json = JSON.parse(data);
            }
            catch (err) {
                displayErrorMessage(`Error parsing data. Please try again. Error is ${err}`);
                return;
            }

            if (json['status'] === 'fail') {
                displayErrorMessage(json['message']);
                return;
            }

            json = json['data'];
            $('#thread-content').html(json['post']);
            if (json.markdown === true) {
                $('#thread-content').addClass('markdown-active');
            }
            else {
                $('#thread-content').removeClass('markdown-active');
            }
        },
        error: function () {
            window.alert('Something went wrong while trying to fetch content. Please try again.');
        },
    });
}

function autosaveKeyFor(replyBox) {
    const parent = $(replyBox).children('[name=parent_id]').val();
    // Having `reply-to-undefined` in the key is sorta gross and might cause
    // false positive bug reports. Let's avoid that.
    if (parent !== undefined) {
        return `${window.location.pathname}-reply-to-${parent}-forum-autosave`;
    }
    else {
        return `${window.location.pathname}-create-thread-forum-autosave`;
    }
}

function saveReplyBoxToLocal(replyBox) {
    const inputBox = $(replyBox).find('textarea.thread_post_content');
    // eslint-disable-next-line no-undef
    if (autosaveEnabled) {
        if (inputBox.val()) {
            const anonCheckbox = $(replyBox).find('input.thread-anon-checkbox');
            const post = inputBox.val();
            const isAnonymous = anonCheckbox.prop('checked');
            localStorage.setItem(autosaveKeyFor(replyBox), JSON.stringify({
                timestamp: Date.now(),
                post,
                isAnonymous,
            }));
        }
        else {
            localStorage.removeItem(autosaveKeyFor(replyBox));
        }
    }
}

function restoreReplyBoxFromLocal(replyBox) {
    // eslint-disable-next-line no-undef
    if (autosaveEnabled) {
        const json = localStorage.getItem(autosaveKeyFor(replyBox));
        if (json) {
            const { post, isAnonymous } = JSON.parse(json);
            $(replyBox).find('textarea.thread_post_content').val(post);
            $(replyBox).find('input.thread-anon-checkbox').prop('checked', isAnonymous);
        }
    }
}

function clearReplyBoxAutosave(replyBox) {
    // eslint-disable-next-line no-undef
    if (autosaveEnabled) {
        localStorage.removeItem(autosaveKeyFor(replyBox));
    }
}

function setupDisableReplyThreadForm() {
    const threadPostForms = document.querySelectorAll('.thread-post-form');

    threadPostForms.forEach((form) => {
        // For all thread forms either reply's or posts, ensure that when text area is empty, the submit button appears to be disabled
        const textArea = form.querySelector('textarea');

        const submitButtons = form.querySelectorAll('input[type="submit"]');

        if (textArea.id === 'reply_box_1' || textArea.id === 'reply_box_2') {
            // Should not apply for first two reply_box's as they imply the post itself which should be handled by another controller due to extensive inputs
            return;
        }

        const inputTest = () => {
            const imageAttachments = form.querySelectorAll('.file-upload-table .file-label');

            submitButtons.forEach((button) => {
                button.disabled = textArea.value.trim() === '' && imageAttachments.length === 0;
            });
        };

        textArea.addEventListener('input', () => {
            // On any text area input, check if disabling the corresponding reply submit button is appropriate
            inputTest();
        });

        inputTest();
    });
}

function setupForumAutosave() {
    // Include both regular reply boxes on the forum as well as the "reply" box
    // on the create thread page.
    $('form.reply-box, form.post_reply_form, #thread_form').each((_index, replyBox) => {
        restoreReplyBoxFromLocal(replyBox);
        $(replyBox).find('textarea.thread_post_content').on('input',
            // eslint-disable-next-line no-undef
            () => deferredSave(autosaveKeyFor(replyBox), () => saveReplyBoxToLocal(replyBox), 1),
        );
        $(replyBox).find('input.thread-anon-checkbox').change(() => saveReplyBoxToLocal(replyBox));
    });

    setupDisableReplyThreadForm();
}

// eslint-disable-next-line no-unused-vars
const CREATE_THREAD_DEFER_KEY = 'create-thread';
const CREATE_THREAD_AUTOSAVE_KEY = `${window.location.pathname}-create-autosave`;

// eslint-disable-next-line no-unused-vars
function saveCreateThreadToLocal() {
    // eslint-disable-next-line no-undef
    if (autosaveEnabled) {
        const title = $('#title').val();
        const categories = $('div.cat-buttons.btn-selected').get().map((e) => e.innerText);
        const status = $('#thread_status').val();
        const data = {
            timestamp: Date.now(),
            title,
            categories,
            status,
        };

        // These fields don't always show up
        const lockDate = $('#lock_thread_date').val();
        if (lockDate !== undefined) {
            data.lockDate = lockDate;
        }
        const isAnnouncement = $('#Announcement').prop('checked');
        if (isAnnouncement !== undefined) {
            data.isAnnouncement = isAnnouncement;
        }
        const pinThread = $('#pinThread').prop('checked');
        if (pinThread !== undefined) {
            data.pinThread = pinThread;
        }
        const expiration = $('#expirationDate').val();
        if (expiration !== undefined) {
            data.expiration = expiration;
        }

        localStorage.setItem(CREATE_THREAD_AUTOSAVE_KEY, JSON.stringify(data));
    }
}

// eslint-disable-next-line no-unused-vars
function restoreCreateThreadFromLocal() {
    // eslint-disable-next-line no-undef
    if (autosaveEnabled) {
        const json = localStorage.getItem(CREATE_THREAD_AUTOSAVE_KEY);
        if (!json) {
            return;
        }

        const data = JSON.parse(json);
        const { title, categories, status } = data;
        $('#title').val(title);
        $('#thread_status').val(status);
        $('div.cat-buttons').each((_i, e) => {
            if (categories.includes(e.innerText)) {
                e.classList.add('btn-selected');
                $(e).find("input[type='checkbox']").prop('checked', true);
            }
            else {
                e.classList.remove('btn-selected');
                $(e).find("input[type='checkbox']").prop('checked', false);
            }
            $(e).trigger('eventChangeCatClass');
        });

        // Optional fields
        $('.expiration').hide();
        if (Object.prototype.hasOwnProperty.call(data, 'lockDate')) {
            $('#lock_thread_date').val(data.lockDate);
        }
        if (data.isAnnouncement) {
            $('#Announcement').prop('checked', data.isAnnouncement);
            $('.expiration').show();
        }
        if (data.pinThread) {
            $('#pinThread').prop('checked', data.pinThread);
            $('.expiration').show();
        }
        if (Object.prototype.hasOwnProperty.call(data, 'expiration')) {
            $('#expirationDate').val(data.expiration);
        }
    }
}

// eslint-disable-next-line no-unused-vars
function clearCreateThreadAutosave() {
    localStorage.removeItem(CREATE_THREAD_AUTOSAVE_KEY);
}

$(() => {
    if (typeof cleanupAutosaveHistory === 'function') {
        // eslint-disable-next-line no-undef
        cleanupAutosaveHistory('-forum-autosave');
        setupForumAutosave();
    }
    $('form#thread_form').submit(updateThread);
});

// When the user uses tab navigation on the thread list, this function
// helps to make sure the current thread is always visible on the page
// eslint-disable-next-line no-unused-vars
function scrollThreadListTo(element) {
    $(element).get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Only used by the posters and only on recent posts (60 minutes since posted)
// eslint-disable-next-line no-unused-vars
function sendAnnouncement(id) {
    $('.pin-and-email-message').attr('disabled', 'disabled');
    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['forum', 'make_announcement']),
        data: { id: id, csrf_token: window.csrfToken },
        success: function (data) {
            try {
                if (JSON.parse(data).status === 'success') {
                    pinAnnouncement(id, 1, window.csrfToken);
                    window.location.reload();
                }
                else {
                    window.alert('Something went wrong while trying to queue the announcement. Please try again.');
                }
            }
            catch (error) {
                console.error(error);
            }
        },
        error: function () {
            window.alert('Something went wrong while trying to queue the announcement. Please try again.');
        },
    });
}

function pinAnnouncement(thread_id, type, csrf_token) {
    if (confirm) {
        const url = `${buildCourseUrl(['forum', 'announcements'])}?type=${type}`;
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                thread_id: thread_id,
                csrf_token: csrf_token,

            },
            // eslint-disable-next-line no-unused-vars
            success: function (data) {
            },
            error: function () {
                window.alert('Something went wrong while trying to remove announcement. Please try again.');
            },
        });
    }
}

function showUpduckUsers(post_id, csrf_token) {
    const url = buildCourseUrl(['forum', 'posts', 'likes', 'details']);
    $.ajax({
        type: 'POST',
        url: url,
        data: { post_id: post_id, csrf_token: csrfToken },
        dataType: 'json',
        success: function (data) {
            if (data.status === 'success') {
                $('#popup-post-likes').show();
                $('body').addClass('popup-active');
                // eslint-disable-next-line no-undef
                captureTabInModal('popup-post-likes');

                $('#popup-post-likes .form-body').empty();

                const users = data.data.users;
                if (users.length === 0) {
                    $('#popup-post-likes .form-body').append('<p>No one has liked this post yet.</p>');
                }
                else {
                    const userList = $('<ul>');
                    for (const user of users) {
                        userList.append(`<li>${user}</li>`);
                    }
                    $('#popup-post-likes .form-body').append(userList);
                }
                $('#popup-post-likes .close-button').off('click').on('click', function () {
                    $('#popup-post-likes').hide();
                    $('body').removeClass('popup-active');
                });
            }
            else {
                displayErrorMessage('Failed to retrieve users who liked this post.');
            }
        },
    });
}
