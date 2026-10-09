/* global csrfToken, buildCourseUrl */

$(document).ready(() => {
    const inputAreas = $('input,textarea,select');

    inputAreas.each(function(){createSaveIndicator(this.id);});
    createSaveIndicator("vcs-legend");
    const sharedSaveIndicators = setSharedSaveIndicators();;

    inputAreas.on('change', function () {
        // Skip elements with the no-autosave class
        if ($(this).hasClass('no-autosave')) {
            return;
        }

        let idForSaveIndicator = this.id;
        if(sharedSaveIndicators.has(idForSaveIndicator)){
            idForSaveIndicator = sharedSaveIndicators.get(idForSaveIndicator);
        }
        const saveDiv = $(`#${idForSaveIndicator}-save-div`).get(0);
        const saveText = $(`#${idForSaveIndicator}-save-text`).get(0);
        const saveIcon = $(`#${idForSaveIndicator}-save-icon`).get(0);
        const hasSaveIndicator = saveDiv && saveText && saveIcon;
        if(hasSaveIndicator){
            saveDiv.classList.remove('save-indicator-unchanged', 'save-indicator-saved', 'save-indicator-fail');
            saveDiv.classList.add('save-indicator-unsaved')
            saveText.textContent = 'Saving...';
            saveIcon.classList.add('fas', 'fa-circle-notch', 'fa-spin');
            saveIcon.classList.remove('fa-solid', 'fa-check', 'fa-x');
        }

        const elem = this;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        let entry;
        let default_section;
        if (this.type === 'checkbox') {
            entry = $(elem).is(':checked');
            if (this.id === 'all-self-registration') {
                default_section = $('#default-section-id').val();
                formData.append('default_section', default_section);
            }
        }
        else {
            entry = elem.value;
        }
        formData.append('name', elem.name);
        formData.append('entry', entry);
        $.ajax({
            url: buildCourseUrl(['config']),
            data: formData,
            type: 'POST',
            processData: false,
            contentType: false,
            success: function (response) {
                try {
                    response = JSON.parse(response);
                }
                catch (exc) {
                    response = {
                        status: 'fail',
                        message: 'invalid response received from server',
                    };
                }
                let failed = response['status'] === 'fail';
                if (failed) {
                    alert(response['message']);
                    $(elem).focus();
                    elem.value = $(elem).attr('value');

                    // Ensure auto_rainbow_grades checkbox reverts to unchecked if it failed validation
                    if ($(elem).attr('name') === 'auto_rainbow_grades') {
                        $(elem).prop('checked', false);
                    }
                }

                if(hasSaveIndicator){
                    saveDiv.classList.remove('save-indicator-unsaved');
                    saveIcon.classList.remove('fas', 'fa-circle-notch', 'fa-spin');
                    if(!failed){
                        saveDiv.classList.add('save-indicator-saved');
                        saveIcon.classList.add('fa-solid', 'fa-check');
                    }else{
                        saveDiv.classList.add('save-indicator-fail');
                        saveIcon.classList.add('fa-solid', 'fa-x');
                    }
                    saveText.textContent = !failed ? 'Saved!' : 'Failed to save.';
                }

                $(elem).attr('value', elem.value);
            },
        });
    });

    function updateForumMessage() {
        $('#forum-enabled-message').toggle();
    }

    $(document).on('change', '#forum-enabled', updateForumMessage);

    function showEmailSeatingOption() {
        $('#email-seating-assignment').show();
        $('#email-seating-assignment_label').show();
    }

    function hideEmailSeatingOption() {
        $('#email-seating-assignment').hide();
        $('#email-seating-assignment-label').hide();
    }

    function updateEmailSeatingOption() {
        if ($('#room-seating-gradeable-id').val()) {
            showEmailSeatingOption();
        }
        else {
            hideEmailSeatingOption();
        }
    }

    updateEmailSeatingOption();

    $(document).on('change', '#room-seating-gradeable-id', updateEmailSeatingOption);

    function updateRainbowCustomizationWarning() {
        const warningMessage = $('#customization-exists-warning');
        const checked = $('#auto-rainbow-grades').is(':checked');
        const customizationNotExists = warningMessage.data('value');
        warningMessage.toggle(checked && customizationNotExists);
    }

    $(document).on('change', '#auto-rainbow-grades', updateRainbowCustomizationWarning);
});

function confirmSelfRegistration(element, needs_reg_sections) {
    if (needs_reg_sections) {
        alert('You need to create at least one registration section first');
        return false;
    }
    if ($('#default-section-id').val() === '') {
        alert('You need to select a registration section first');
        return false;
    }

    return !element.checked ? true : confirm('Are you sure you want to enable self registration to this course? This allows ALL users (even those manually removed from the course) to register for this course.');
}

function createSaveIndicator(elementId){
    let title = $(`label[for='${elementId}'] .option-title`);
    if(title.length == 0 && elementId){
        title = $(`#${elementId} .option-title`);
    }
    if(title.length == 0){return;}
    title = title.get(0);

    const titleRow = document.createElement('span');
    titleRow.classList.add('title-row');
    title.parentNode.insertBefore(titleRow, title);
    titleRow.appendChild(title);

    const saveDiv = document.createElement('div');
    saveDiv.id = elementId + '-save-div';
    saveDiv.classList.add('save-indicator', 'save-indicator-saved');
    const saveIcon = document.createElement('i');
    saveIcon.id = elementId + '-save-icon';
    const saveText = document.createElement('span');
    saveText.id = elementId + '-save-text';
    saveText.classList.add('subtitle');
    saveDiv.appendChild(saveIcon);
    saveDiv.appendChild(saveText);
    titleRow.appendChild(saveDiv);
}

function setSharedSaveIndicators(){
    const sharedSaveIndicators = new Map();
    sharedSaveIndicators.set('seating-only-for-instructor', 'room-seating-gradeable-id');
    sharedSaveIndicators.set('default-section-id', 'all-self-registration');
    sharedSaveIndicators.set('vcs-type-git', 'vcs-legend');
    return sharedSaveIndicators;
}