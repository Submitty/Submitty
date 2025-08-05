import { buildUrl, buildCourseUrl } from './utils/server';

declare global {
    interface Window {
        displaySuccessMessage: (message: string) => void;
        displayErrorMessage: (message: string) => void;
        csrfToken: string;
        handleProfileSyncChange: () => Promise<void>;
        handleSyncClick: () => Promise<void>;
        handleSetDefaultsClick: () => Promise<void>;
        handleClearDefaultsClick: () => Promise<void>;
    }
}

interface ApiResponse {
    status: 'success' | 'fail' | 'error';
    data: { message: string } | null;
}

async function updateNotificationSync(synced: boolean): Promise<ApiResponse> {
    const formData = new FormData();
    formData.append('notifications_synced', synced.toString());
    formData.append('csrf_token', window.csrfToken);

    const url = window.location.href.includes('notifications')
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

        return response.json() as Promise<ApiResponse>;
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

async function updateNotificationDefaults(setAsDefault: boolean): Promise<ApiResponse> {
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

        return response.json() as Promise<ApiResponse>;
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

    const profileDropdown = document.getElementById('notification_sync_preference') as HTMLSelectElement;
    if (profileDropdown) {
        profileDropdown.value = synced ? 'sync' : 'unsync';
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

    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationSync(newSyncState);

        if (response.status === 'success') {
            updateSyncUI(newSyncState);
            if (response.data) {
                window.displaySuccessMessage(response.data.message);
            }
        }
        else {
            button.textContent = originalText;
            window.displayErrorMessage(response.data?.message || 'Failed to update sync settings.');
        }
    }
    catch (error) {
        button.textContent = originalText;
        console.error('Sync error:', error);
        window.displayErrorMessage('An error occurred while updating sync settings.');
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
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationDefaults(true);
        if (response.status === 'success') {
            updateDefaultsUI(true);
            if (response.data) {
                window.displaySuccessMessage(response.data.message);
            }
        }
        else {
            window.displayErrorMessage(response.data?.message || 'Failed to set default settings.');
        }
    }
    catch (error) {
        console.error('Set defaults error:', error);
        window.displayErrorMessage('An error occurred while setting default settings.');
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
            window.displayErrorMessage(response.data?.message || 'Failed to clear default settings.');
        }
    }
    catch (error) {
        console.error('Clear defaults error:', error);
        window.displayErrorMessage('An error occurred while clearing default settings.');
    }
    finally {
        button.disabled = false;
        button.textContent = originalText;
    }
};

/**
 * Handle profile sync dropdown change - can be called directly from onchange
 */
window.handleProfileSyncChange = async function handleProfileSyncChange(): Promise<void> {
    const dropdown = document.getElementById('notification_sync_preference') as HTMLSelectElement;
    if (!dropdown) {
        return;
    }

    const synced = dropdown.value === 'sync';
    const originalValue = synced ? 'unsync' : 'sync';

    dropdown.disabled = true;

    try {
        const response = await updateNotificationSync(synced);

        if (response.status === 'success') {
            updateSyncUI(synced);
            if (response.data) {
                window.displaySuccessMessage(response.data.message);
            }
        }
        else {
            dropdown.value = originalValue;
            window.displayErrorMessage(response.data?.message || 'Failed to update sync preference.');
        }
    }
    catch (error) {
        console.error('Profile sync error:', error);
        dropdown.value = originalValue;
        window.displayErrorMessage('An error occurred while updating sync preference.');
    }
    finally {
        dropdown.disabled = false;
    }
};
