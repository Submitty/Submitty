import { buildUrl, buildCourseUrl } from './utils/server';

// Global cache for notification settings to avoid redundant API calls
const notificationSettingsCache: Record<string, NotificationSettingsResponse> = {};

declare global {
    interface Window {
        csrfToken: string;
        defaults: Record<string, string>;
        displaySuccessMessage: (message: string) => void;
        displayErrorMessage: (message: string) => void;
        handleProfileSyncChange: () => Promise<void>;
        handleSyncClick: () => Promise<void>;
        handleSetDefaultsClick: () => Promise<void>;
        handleClearDefaultsClick: () => Promise<void>;
        handleDefaultsDropdownChange: () => Promise<void>;
        showNotificationDefaults: (autoApply?: boolean) => Promise<void>;
        applyNotificationDefaults: () => Promise<void>;
        clearNotificationDefaults: () => Promise<void>;
        captureTabInModal: (modalId: string) => void;
        checkAll: (button: HTMLButtonElement) => void;
        unCheckAll: (button: HTMLButtonElement) => void;
        resetNotification: (button: HTMLButtonElement) => void;
        handleNotificationSettingsChange: (form: JQuery<HTMLFormElement>) => Promise<void>;
    }
}

type NotificationSettingsResponse = {
    course: string;
    missing_settings: boolean;
    settings: Record<string, string>;
    message?: string;
};

interface ApiResponse<T> {
    status: 'success' | 'error' | 'fail';
    data?: T; /* Successful responses */
    message?: string; /* Non-successful responses */
}

async function submitRequest(url: string, formData: FormData): Promise<ApiResponse<string>> {
    formData.append('csrf_token', window.csrfToken);

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP request failed with status: ${response.status}`);
        }

        return response.json() as Promise<ApiResponse<string>>;
    }
    catch (error) {
        console.error('Submit request error:', error);
        return {
            status: 'error',
            data: 'An error occurred while submitting the request.',
        };
    }
}

async function updateNotificationSync(synced: boolean): Promise<ApiResponse<string>> {
    const formData = new FormData();
    formData.append('notifications_synced', synced.toString());

    const url = window.location.pathname.includes('/courses/')
        ? buildCourseUrl(['notifications', 'settings', 'sync']) // Course-specific sync routing
        : buildUrl(['notifications', 'settings', 'sync']); // Global sync routing

    return submitRequest(url, formData);
}

async function updateNotificationDefaults(setAsDefault: boolean): Promise<ApiResponse<string>> {
    const formData = new FormData();
    formData.append('notification_defaults', setAsDefault ? 'current' : 'null');

    const url = buildCourseUrl(['notifications', 'settings', 'defaults']);

    return submitRequest(url, formData);
}

function updateDefaultButtons(clearing = false): void {
    // TODO: handle the hide set as default button after clicking it to say "clear default"
    const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
    const applyButton = document.getElementById('apply-defaults-btn') as HTMLButtonElement;
    const clearButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;

    if (dropdown) {
        const selectedDefault = dropdown.value;
        const currentDefault = dropdown.dataset.currentDefault || '';
        applyButton.style.display = selectedDefault !== currentDefault ? 'block' : 'none';
        clearButton.style.display = selectedDefault === currentDefault && selectedDefault !== '' ? 'block' : 'none';
    }
    else {
        // set-defaults-btn
        const setDefaultsButton = document.getElementById('set-defaults-btn') as HTMLButtonElement;
        const clearDefaultsButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;
        setDefaultsButton.style.display = clearing ? 'block' : 'none';
        clearDefaultsButton.style.display = clearing ? 'none' : 'block';
    }

}

window.handleSyncClick = async function handleSyncClick(): Promise<void> {
    const button = document.getElementById('sync-notifications-btn') as HTMLButtonElement;
    if (!button) {
        return;
    }

    const currentlySynced = button.textContent?.trim() === 'Unsync Notifications';
    const newSyncState = !currentlySynced;
    const originalText = button.textContent;

    // Display updating text in the case the sync operation is taking a while
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationSync(newSyncState);

        if (response.status === 'success') {
            button.textContent = newSyncState ? 'Unsync Notifications' : 'Sync Notifications';
            window.displaySuccessMessage(response.data || 'Notification sync settings updated successfully.');
        }
        else {
            button.textContent = originalText;
            window.displayErrorMessage(response.message || 'Failed to update sync settings.');
        }
    }
    catch (error) {
        button.textContent = originalText;
        console.error('Sync error:', error);
        window.displayErrorMessage('Failed to update sync settings.');
    }
    finally {
        button.disabled = false;
    }
};

window.handleSetDefaultsClick = async function handleSetDefaultsClick(): Promise<void> {
    // TODO: click set defaults in twig template
    const button = document.getElementById('set-defaults-btn') as HTMLButtonElement;
    if (!button) {
        return;
    }

    const originalText = button.textContent;

    // Display updating text in the case the sync operation is taking a while
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationDefaults(true);
        if (response.status === 'success') {
            updateDefaultButtons();
            window.displaySuccessMessage(response.data || 'Default notification settings set successfully.');
        }
        else {
            window.displayErrorMessage(response.message || 'Failed to set default settings.');
        }
    }
    catch (error) {
        console.error('Set defaults error:', error);
        window.displayErrorMessage('Failed to set default settings.');
    }
    finally {
        button.disabled = false;
        button.textContent = originalText;
    }
};

window.handleClearDefaultsClick = async function handleClearDefaultsClick(): Promise<void> {
    const button = document.getElementById('clear-defaults-btn') as HTMLButtonElement;
    if (!button) {
        return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationDefaults(false);

        if (response.status === 'success') {
            updateDefaultButtons();
            window.displaySuccessMessage(response.data || 'Default notification settings cleared successfully.');
        }
        else {
            window.displayErrorMessage(response.message || 'Failed to clear default settings.');
        }
    }
    catch (error) {
        console.error('Clear defaults error:', error);
        window.displayErrorMessage('Failed to clear default settings.');
    }
    finally {
        button.disabled = false;
        button.textContent = originalText;
    }
};

window.handleProfileSyncChange = async function handleProfileSyncChange(): Promise<void> {
    const dropdown = document.getElementById('notification_sync_select') as HTMLSelectElement;
    if (!dropdown) {
        return;
    }

    const synced = dropdown.value === 'true';
    const originalValue = dropdown.getAttribute('data-original-value') || dropdown.value;

    dropdown.disabled = true;
    dropdown.setAttribute('data-original-value', dropdown.value);

    try {
        const response = await updateNotificationSync(synced);

        if (response.status === 'success') {
            window.displaySuccessMessage(response.data || 'Notification sync settings updated successfully.');
        }
        else {
            dropdown.value = originalValue;
            window.displayErrorMessage(response.message || 'Failed to update sync preference.');
        }
    }
    catch (error) {
        console.error('Profile sync error:', error);
        dropdown.value = originalValue;
        window.displayErrorMessage('Failed to update sync preference.');
    }
    finally {
        dropdown.disabled = false;
    }
};

window.handleDefaultsDropdownChange = async function handleDefaultsDropdownChange(): Promise<void> {
    const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
    if (!dropdown) {
        return;
    }

    const value = dropdown.value;
    const currentDefault = dropdown.dataset.currentDefault || '';
    const buttonsContainer = document.getElementById('notification-defaults-buttons') as HTMLDivElement;
    const applyButton = document.getElementById('apply-defaults-btn') as HTMLButtonElement;
    const clearButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;

    if (buttonsContainer) {
        applyButton.style.display = (value !== '' && value === currentDefault) ? 'none' : 'block';
        clearButton.style.display = (value !== '' && value === currentDefault) ? 'block' : 'none';
        buttonsContainer.style.display = value === '' && currentDefault === '' ? 'none' : 'block';
        await window.showNotificationDefaults(true); // Display this new selected course's settings
    }
};

window.showNotificationDefaults = async function showNotificationDefaults(autoApply = false): Promise<void> {
    try {
        const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
        if (!dropdown) {
            return;
        }

        const courseKey = dropdown.value;
        const currentDefault = dropdown.dataset.currentDefault || '';
        const isCurrentDefault = courseKey === currentDefault;

        // Potentially add a small delay to allow the spinner to be visible for a moment or don't show the spinner at all
        const timeout = currentDefault === '' || notificationSettingsCache[courseKey] !== undefined ? 0 : 750;

        if (!autoApply) { // Called from outside of the dropdown event listener
            // Show the modal with spinner first
            $('#popup-notification-defaults').show();
            $('body').addClass('popup-active');
            window.captureTabInModal('popup-notification-defaults');
        }

        // Show spinner, hide existing content, if any
        if (timeout > 0) {
            $('#notification-defaults-spinner').show();
            $('#notification-defaults-content').hide().empty();
        }

        // If no course selected, just show the empty state
        if (dropdown.value === '') {
            $('#notification-defaults-spinner').hide();
            $('#notification-defaults-content').show();
            return;
        }

        // Check cache first
        let result: ApiResponse<NotificationSettingsResponse>;
        if (notificationSettingsCache[courseKey]) {
            result = {
                status: 'success',
                data: notificationSettingsCache[courseKey],
            };
        }
        else {
            // Fetch from server and cache the result
            const formData = new FormData();
            formData.append('course_key', courseKey);
            formData.append('csrf_token', window.csrfToken);

            const response = await fetch(buildUrl(['notifications', 'settings', 'defaults', 'view']), {
                method: 'POST',
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP request failed with status: ${response.status}`);
            }

            result = await response.json() as ApiResponse<NotificationSettingsResponse>;

            // Cache successful results
            if (result.status === 'success') {
                notificationSettingsCache[courseKey] = result.data as NotificationSettingsResponse;
            }
        }

        if (result.status === 'success') {
            setTimeout(() => {
                // Hide spinner
                $('#notification-defaults-spinner').hide();
                const contentDiv = $('#notification-defaults-content');
                contentDiv.empty();

                const settings = result.data?.settings || {};
                const [term, course] = result.data?.course.split('-') || [];

                contentDiv.append(`
                <h3 style="margin-bottom: 2px; text-decoration: underline;">Course Information</h3>
                <strong>Term</strong> - ${term}
                <br />
                <strong>Course</strong> - ${course}
            `);

                if (result.data?.missing_settings) {
                    contentDiv.append('<p><strong>NOTE: No notification settings found for this course; default settings will be applied.</strong></p>');
                }

                const settingsList = $('<ul>');
                for (const [key, value] of Object.entries(settings)) {
                    if (key === 'all_announcements') {
                        settingsList.append('<h3 style="margin-top: 10px; margin-bottom: 2px; text-decoration: underline;">Mandatory Notifications</h3>');
                    }
                    else if (key === 'reply_in_post_thread') {
                        settingsList.append('<h3 style="margin-top: 10px; margin-bottom: 2px; text-decoration: underline;">Optional Notifications</h3>');
                    }
                    const displayName = key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
                    const statusIcon = value === 'true'
                        ? '<i class="fas fa-check-circle" style="color: #4CAF50; margin-right: 5px;"></i>'
                        : '<i class="fas fa-times-circle" style="color: #F44336; margin-right: 5px;"></i>';
                    settingsList.append(`<li style="margin-left: 2px;">${statusIcon}<strong>${displayName}</strong></li>`);
                }
                contentDiv.append(settingsList);

                // Update action buttons
                const applyButton = document.getElementById('apply-defaults-btn') as HTMLButtonElement;
                const clearButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;

                // If the current course is not the default, show the apply button
                if (!isCurrentDefault && currentDefault !== '') {
                    applyButton.style.display = 'block';
                    clearButton.style.display = 'none';
                }

                // If the current course is the default, show the clear button
                if (isCurrentDefault && currentDefault !== '') {
                    applyButton.style.display = 'none';
                    clearButton.style.display = 'block';
                }

                updateDefaultButtons();
                contentDiv.show();

                $('#popup-notification-defaults .close-button').off('click').on('click', () => {
                    $('#popup-notification-defaults').hide();
                    $('body').removeClass('popup-active');
                });
            }, timeout);
        }
        else {
            // Hide spinner, show error
            $('#notification-defaults-spinner').hide();
            $('#notification-defaults-content').show().append(`
                <div class="alert alert-danger">
                    ${result.message || 'Failed to retrieve notification defaults.'}
                </div>
            `);
            window.displayErrorMessage(result.message || 'Failed to retrieve notification defaults.');
        }
    }
    catch (error) {
        console.error('Show defaults error:', error);
        // Hide spinner, show error
        $('#notification-defaults-spinner').hide();
        $('#notification-defaults-content').show().append(`
            <div class="alert alert-danger">
                Failed to load notification settings. Please try again.
            </div>
        `);
        window.displayErrorMessage('Failed to load notification defaults.');
    }
};

window.applyNotificationDefaults = async function applyNotificationDefaults(): Promise<void> {
    const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;

    if (!dropdown) {
        return;
    }

    // Get the button from either the modal or the main page
    const modalButton = document.getElementById('modal-apply-btn') as HTMLButtonElement;

    // Disable button and show loading state
    if (modalButton) {
        modalButton.disabled = true;
        modalButton.textContent = 'Applying...';
    }

    try {
        const formData = new FormData();
        formData.append('course_key', dropdown.value);
        formData.append('csrf_token', window.csrfToken);

        const response = await fetch(buildUrl(['notifications', 'settings', 'defaults']), {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP request failed with status: ${response.status}`);
        }

        const result = await response.json() as ApiResponse<string>;

        if (result.status === 'success') {
            window.displaySuccessMessage(result.data || 'Default notification settings applied successfully.');
            // Update the current default in the dataset
            const courseKey = dropdown.value;
            dropdown.dataset.currentDefault = courseKey;
            updateDefaultButtons();

            // Close the modal
            $('#popup-notification-defaults').hide();
            $('body').removeClass('popup-active');
        }
        else {
            window.displayErrorMessage(result.message || 'Failed to apply default settings.');
        }
    }
    catch (error) {
        console.error('Apply defaults error:', error);
        window.displayErrorMessage('Failed to apply default settings.');
    }
    finally {
        if (modalButton) {
            modalButton.disabled = false;
            modalButton.textContent = 'Set as Default';
        }
    }
};

window.clearNotificationDefaults = async function clearNotificationDefaults(): Promise<void> {
    // Get the button from the modal
    const modalButton = document.getElementById('modal-clear-btn') as HTMLButtonElement;

    // Disable button and show loading state
    if (modalButton) {
        modalButton.disabled = true;
        modalButton.textContent = 'Clearing...';
    }

    try {
        const formData = new FormData();
        formData.append('notification_defaults', 'null');
        formData.append('csrf_token', window.csrfToken);

        const response = await fetch(buildUrl(['notifications', 'settings', 'defaults']), {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP request failed with status: ${response.status}`);
        }

        const result = await response.json() as ApiResponse<string>;

        if (result.status === 'success') {
            window.displaySuccessMessage(result.data || 'Default notification settings cleared successfully.');

            // Close the modal
            $('#popup-notification-defaults').hide();
            $('body').removeClass('popup-active');

            // Update the dropdown and clear the current default in the dataset
            const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
            if (dropdown) {
                dropdown.value = '';
                dropdown.dataset.currentDefault = '';
                updateDefaultButtons(true);
            }
        }
        else {
            window.displayErrorMessage(result.message || 'Failed to clear notification defaults.');
        }
    }
    catch (error) {
        console.error('Clear defaults error:', error);
        window.displayErrorMessage('Failed to clear notification defaults.');
    }
    finally {
        if (modalButton) {
            modalButton.disabled = false;
            modalButton.textContent = 'Clear Default';
        }
    }
};

// Functions moved from NotificationSettings.twig
window.checkAll = function checkAll(button: HTMLButtonElement): void {
    const selector: JQuery<HTMLElement> = $(button).data('selector') as JQuery<HTMLElement>;
    $(selector).children().prop('checked', true);
    $('#form_notification_settings').trigger('change');
};

window.unCheckAll = function unCheckAll(button: HTMLButtonElement): void {
    const selector: JQuery<HTMLElement> = $(button).data('selector') as JQuery<HTMLElement>;
    $(selector).children().filter(':not(:disabled)').prop('checked', false);
    $('#form_notification_settings').trigger('change');
};

window.resetNotification = function resetNotification(button: HTMLButtonElement): void {
    // Note: defaults is expected to be defined in the template
    // This will be handled by the template-injected defaults variable
    const selector: string = $(button).data('selector') as string;

    if (selector === '.notification-setting-input') {
        const defaults = window.defaults;
        for (const d in defaults) {
            if (Object.prototype.hasOwnProperty.call(defaults, d) && !d.includes('_email')) {
                $(`input[name='${d}']`).prop('checked', defaults[d]);
            }
        }
    }
    else if (selector === '.email-setting-input') {
        const defaults = window.defaults;
        for (const d in defaults) {
            if (Object.prototype.hasOwnProperty.call(defaults, d) && d.includes('_email')) {
                $(`input[name='${d}']`).prop('checked', defaults[d]);
            }
        }
    }

    $('#form_notification_settings').trigger('change');
};

// Set up form change handler when document is ready
$(document).ready(() => {
    $('#form_notification_settings').on('change', function (e) {
        const form = $(this);
        const url = form.attr('action') as string;
        e.preventDefault();

        $.ajax({
            type: 'POST',
            data: form.serialize(),
            url: url,
            success: function (data: string) {
                try {
                    const json = JSON.parse(data) as ApiResponse<string>;
                    if (json.status === 'fail') {
                        window.displayErrorMessage(json.message || 'Failed to update notification settings.');
                    }
                    else {
                        window.displaySuccessMessage(json.data || 'Notification settings updated successfully.');
                    }
                }
                catch (err) {
                    console.error('Error parsing data:', err);
                    window.displayErrorMessage('Error parsing data. Please try again.');
                }
                $('#notification-settings').css('display', 'none');
            },
        });
    });
});
