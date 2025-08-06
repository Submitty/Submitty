import { buildUrl, buildCourseUrl } from './utils/server';

declare global {
    interface Window {
        csrfToken: string;
        displaySuccessMessage: (message: string) => void;
        displayErrorMessage: (message: string) => void;
        handleProfileSyncChange: () => Promise<void>;
        handleSyncClick: () => Promise<void>;
        handleSetDefaultsClick: () => Promise<void>;
        handleClearDefaultsClick: () => Promise<void>;
        handleDefaultsDropdownChange: () => Promise<void>;
        showNotificationDefaults: () => Promise<void>;
        applyNotificationDefaults: () => Promise<void>;
        clearNotificationDefaults: () => Promise<void>;
        captureTabInModal: (modalId: string) => void;
    }
}

type NotificationUpdateResponse = { message: string };

type NotificationSettingsResponse = {
    course: string;
    missing_settings: boolean;
    settings: Record<string, string>;
    message?: string;
};

interface ApiResponse<T> {
    status: 'success' | 'error' | 'fail';
    data: T;
    message?: string;
}

async function updateNotificationSync(synced: boolean): Promise<ApiResponse<NotificationUpdateResponse>> {
    const formData = new FormData();
    formData.append('notifications_synced', synced.toString());
    formData.append('csrf_token', window.csrfToken);

    const url = window.location.pathname.includes('/courses/')
        ? buildCourseUrl(['notifications', 'settings', 'sync'])
        : buildUrl(['notifications', 'settings', 'sync']);

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP request failed with status: ${response.status}`);
        }

        return response.json() as Promise<ApiResponse<NotificationUpdateResponse>>;
    }
    catch (error) {
        console.error('Sync error:', error);
        return {
            status: 'error',
            data: {
                message: 'An error occurred while updating sync settings.',
            },
        };
    }
}

async function updateNotificationDefaults(setAsDefault: boolean): Promise<ApiResponse<NotificationUpdateResponse>> {
    const formData = new FormData();
    formData.append('notification_defaults', setAsDefault ? 'current' : 'null');
    formData.append('csrf_token', window.csrfToken);

    try {
        const response = await fetch(buildCourseUrl(['notifications', 'settings', 'defaults']), {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP request failed with status: ${response.status}`);
        }

        return response.json() as Promise<ApiResponse<NotificationUpdateResponse>>;
    }
    catch (error) {
        console.error('Defaults error:', error);
        return {
            status: 'error',
            data: { message: 'An error occurred while updating default settings.' },
        };
    }
}

function updateSyncUI(synced: boolean): void {
    const syncButton = document.getElementById('sync-notifications-btn') as HTMLButtonElement;
    if (syncButton) {
        syncButton.textContent = synced ? 'Unsync Notifications' : 'Sync Notifications';
    }
}

function updateDefaultsUI(hasDefaults: boolean): void {
    const clearButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;
    clearButton.style.display = hasDefaults ? 'block' : 'none';
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
            updateSyncUI(newSyncState);

            if (response.status === 'success') {
                window.displaySuccessMessage(response.data.message);
            }
        }
        else {
            button.textContent = originalText;
            window.displayErrorMessage(response.data.message);
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
            updateDefaultsUI(true);
            window.displaySuccessMessage(response.data.message);
        }
        else {
            window.displayErrorMessage(response.data.message);
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
            updateDefaultsUI(false);
            if (response.data) {
                window.displaySuccessMessage(response.data.message);
            }
        }
        else {
            window.displayErrorMessage(response.data.message);
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

window.handleDefaultsDropdownChange = async function handleDefaultsDropdownChange(): Promise<void> {
    const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
    if (!dropdown) {
        return;
    }

    const value = dropdown.value;
    const buttonsContainer = document.getElementById('notification-defaults-buttons') as HTMLDivElement;
    if (buttonsContainer) {
        buttonsContainer.style.display = value === '' ? 'none' : 'flex';
    }
}

window.showNotificationDefaults = async function showNotificationDefaults(): Promise<void> {
    try {
        const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
        if (!dropdown || dropdown.value === '') {
            return;
        }

        const formData = new FormData();
        formData.append('course_key', dropdown.value);
        formData.append('csrf_token', window.csrfToken);

        const response = await fetch(buildUrl(['notifications', 'settings', 'defaults', 'view']), {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP request failed with status: ${response.status}`);
        }

        const result = await response.json() as ApiResponse<NotificationSettingsResponse>;

        if (result.status === 'success') {
            $('#popup-notification-defaults').show();
            $('body').addClass('popup-active');

            window.captureTabInModal('popup-notification-defaults');

            $('#popup-notification-defaults .form-body').empty();

            const settings = result.data.settings;

            const [term, course] = result.data.course.split('-');
            $('#popup-notification-defaults .form-body').append(`
                <h3 style="margin-bottom: 2px; text-decoration: underline;">Course Information</h3>
                <strong>Term</strong> - ${term}
                <br />
                <strong>Course</strong> - ${course}
            `);

            if (result.data.missing_settings) {
                $('#popup-notification-defaults .form-body').append('<p><strong>NOTE: No notification settings found for this course; default settings will be applied.</strong></p>');
            }

            const settingsList = $('<ul>');
            for (const [key, value] of Object.entries(settings)) {
                if (key === 'all_announcements') {
                    settingsList.append(`<h3 style="margin-top: 10px; margin-bottom: 2px; text-decoration: underline;">Mandatory Notifications</h3>`);
                }
                else if (key === 'reply_in_post_thread') {
                    settingsList.append(`<h3 style="margin-top: 10px; margin-bottom: 2px; text-decoration: underline;">Optional Notifications</h3>`);
                }
                const displayName = key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
                const status = value === 'true' ? 'Enabled' : 'Disabled';
                settingsList.append(`<li><strong>${displayName}</strong> - ${status}</li>`);
            }
            $('#popup-notification-defaults .form-body').append(settingsList);


            $('#popup-notification-defaults .close-button').off('click').on('click', () => {
                $('#popup-notification-defaults').hide();
                $('body').removeClass('popup-active');
            });
        }
        else {
            window.displayErrorMessage(result?.message || 'Failed to retrieve notification defaults.');
        }
    }
    catch (error) {
        console.error('Show defaults error:', error);
        window.displayErrorMessage('Failed to load notification defaults.');
    }
};

window.applyNotificationDefaults = async function applyNotificationDefaults(): Promise<void> {
    const button = document.getElementById('apply-defaults-btn') as HTMLButtonElement;
    const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
    if (!button || !dropdown) {
        return;
    }

    console.log('applyNotificationDefaults', dropdown.value);

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Applying...';

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

        const result = await response.json() as ApiResponse<NotificationUpdateResponse>;

        if (result.status === 'success') {
            window.displaySuccessMessage(result.data.message);
        }
        else {
            window.displayErrorMessage(result?.message || 'Failed to apply default settings.');
        }
    }
    catch (error) {
        console.error('Apply defaults error:', error);
        window.displayErrorMessage('Failed to apply default settings.');
    }
    finally {
        button.disabled = false;
        button.textContent = originalText;
    }
};

window.clearNotificationDefaults = async function clearNotificationDefaults(): Promise<void> {
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

        const result = await response.json() as ApiResponse<NotificationUpdateResponse>;

        if (result.status === 'success') {
            window.displaySuccessMessage(result.data.message);
            // Close the modal
            $('#popup-notification-defaults').hide();
            $('body').removeClass('popup-active');
            // Update the dropdown
            const dropdown = document.getElementById('notification_defaults_select') as HTMLSelectElement;
            if (dropdown) {
                dropdown.value = '';
            }

            // Hide the buttons container
            const buttonsContainer = document.getElementById('notification-defaults-buttons') as HTMLDivElement;
            if (buttonsContainer) {
                buttonsContainer.style.display = 'none';
            }
        }
        else {
            window.displayErrorMessage(result?.message || 'Failed to clear notification defaults.');
        }
    }
    catch (error) {
        console.error('Clear defaults error:', error);
        window.displayErrorMessage('Failed to clear notification defaults.');
    }
};
