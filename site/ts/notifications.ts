import { buildCourseUrl } from './utils/server';

declare global {
    interface Window {
        displaySuccessMessage: (message: string) => void;
        displayErrorMessage: (message: string) => void;
        csrfToken: string;
    }
}

/**
 * TypeScript helper functions for notification sync and default settings functionality
 */

interface ApiResponse {
    status: 'success' | 'fail';
    data: { message: string } | null;
}

/**
 * Update notification sync status via the API
 *
 * @param synced - Whether to enable or disable sync
 * @returns Promise<ApiResponse>
 */
export async function updateNotificationSync(synced: boolean): Promise<ApiResponse> {
    const formData = new FormData();
    formData.append('notifications_synced', synced.toString());
    formData.append('csrf_token', window.csrfToken);

    try {
        const response = await fetch(buildCourseUrl(['notifications', 'settings', 'sync']), {
            method: 'POST',
            body: formData,
        });

        return response.json() as Promise<ApiResponse>;
    }
    catch (error) {
        console.error(error);
        throw new Error('Network error occurred while updating sync settings.');
    }
}

/**
 * Update notification defaults via the API
 *
 * @param setAsDefault - Whether to set current course as default or clear defaults
 * @returns Promise<ApiResponse>
 */
export async function updateNotificationDefaults(setAsDefault: boolean): Promise<ApiResponse> {
    const formData = new FormData();
    formData.append('notification_defaults', setAsDefault ? 'current' : 'null');
    formData.append('csrf_token', window.csrfToken);

    try {
        const response = await fetch(buildCourseUrl(['notifications', 'settings', 'defaults']), {
            method: 'POST',
            body: formData,
        });

        return response.json() as Promise<ApiResponse>;
    }
    catch (error) {
        console.error(error);
        throw new Error('Network error occurred while updating default settings.');
    }
}

/**
 * Display success message to the user
 * @param message - Success message to display
 */
export function displaySuccessMessage(message: string): void {
    window.displaySuccessMessage(message);
}

/**
 * Display error message to user
 *
 * @param message - Error message to display
 */
export function displayErrorMessage(message: string): void {
    window.displayErrorMessage(message);
}

/**
 * Handle sync button click action
 */
async function handleSyncButtonClick(button: HTMLButtonElement): Promise<void> {
    const currentlySynced = button.textContent?.trim() === 'Unsync Notifications';
    const newSyncState = !currentlySynced;
    const originalText = button.textContent;

    // Disable button to prevent multiple clicks
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationSync(newSyncState);

        if (response.status === 'success') {
            button.textContent = newSyncState ? 'Unsync Notifications' : 'Sync Notifications';
            button.className = newSyncState ? 'btn btn-primary' : 'btn btn-default';

            if (response.data) {
                displaySuccessMessage(response.data.message);
            }

            const syncInfo = document.querySelector('.sync-info') as HTMLElement;
            if (syncInfo) {
                syncInfo.textContent = `Last sync: ${new Date().toLocaleString()}`;
            }
        }
        else {
            button.textContent = originalText;
            displayErrorMessage(response.data?.message || 'Failed to update sync settings.');
        }
    }
    catch (error) {
        button.textContent = originalText;
        console.error(error);
        displayErrorMessage('An error occurred while updating sync settings.');
    }
    finally {
        button.disabled = false;
    }
}

/**
 * Handle set defaults button click action
 */
async function handleSetDefaultsClick(button: HTMLButtonElement): Promise<void> {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationDefaults(true);
        if (response.status === 'success') {
            if (response.data) {
                displaySuccessMessage(response.data.message);
            }
            // TODO: no window reload
            window.location.reload();
        }
        else {
            displayErrorMessage(response.data?.message || 'Failed to set default settings.');
        }
    }
    catch (error) {
        console.error(error);
        displayErrorMessage('An error occurred while setting default settings.');
    }
    finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

/**
 * Handle clear defaults button click action
 */
async function handleClearDefaultsClick(button: HTMLButtonElement): Promise<void> {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await updateNotificationDefaults(false);

        if (response.status === 'success') {
            if (response.data) {
                displaySuccessMessage(response.data.message);
            }
            // TODO: no window reload
            window.location.reload();
        }
        else {
            displayErrorMessage(response.data?.message || 'Failed to clear default settings.');
        }
    }
    catch (error) {
        console.error(error);
        displayErrorMessage('An error occurred while clearing default settings.');
    }
    finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

/**
 * Create a safe async event handler wrapper
 */
function createAsyncEventHandler<T extends HTMLElement>(
    handler: (element: T) => Promise<void>,
): (event: Event) => void {
    return (event: Event) => {
        event.preventDefault();
        const target = event.currentTarget as T;
        handler(target).catch((error) => {
            console.error('Async event handler error:', error);
            displayErrorMessage('An unexpected error occurred.');
        });
    };
}

/**
 * Initialize notification settings page functionality
 */
export function initializeNotificationSettings(): void {
    const syncButton = document.getElementById('sync-notifications-btn') as HTMLButtonElement;
    if (syncButton) {
        syncButton.addEventListener('click', createAsyncEventHandler(handleSyncButtonClick));
    }

    const setDefaultsButton = document.getElementById('set-defaults-btn') as HTMLButtonElement;
    if (setDefaultsButton) {
        setDefaultsButton.addEventListener('click', createAsyncEventHandler(handleSetDefaultsClick));
    }

    const clearDefaultsButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;
    if (clearDefaultsButton) {
        clearDefaultsButton.addEventListener('click', createAsyncEventHandler(handleClearDefaultsClick));
    }
}

/**
 * Handle profile sync dropdown change
 */
async function handleProfileSyncChange(dropdown: HTMLSelectElement): Promise<void> {
    const synced = dropdown.value === 'sync';
    const originalValue = synced ? 'unsync' : 'sync'; // Store the opposite value for reverting

    // Disable dropdown to prevent multiple changes during processing
    dropdown.disabled = true;

    try {
        const response = await fetch(buildCourseUrl(['notifications', 'settings', 'sync']), {
            method: 'POST',
            body: new URLSearchParams({
                notifications_synced: synced.toString(),
                csrf_token: window.csrfToken,
            }),
        });

        const responseData = await response.json() as ApiResponse;

        if (responseData.status === 'success') {
            if (responseData.data) {
                displaySuccessMessage(responseData.data.message);
            }

            const lastUpdated = document.querySelector('.option-alt') as HTMLElement;
            if (lastUpdated && lastUpdated.textContent?.includes('Last updated:')) {
                lastUpdated.textContent = `Last updated: ${new Date().toLocaleString()}`;
            }
        }
        else {
            dropdown.value = originalValue;
            displayErrorMessage(responseData.data?.message || 'Failed to update sync preference.');
        }
    }
    catch (error) {
        console.error(error);
        // Revert dropdown if API call failed
        dropdown.value = originalValue;
        displayErrorMessage('An error occurred while updating sync preference.');
    }
    finally {
        dropdown.disabled = false;
    }
}

/**
 * Create a safe async change handler wrapper for select elements
 */
function createAsyncChangeHandler<T extends HTMLSelectElement>(
    handler: (element: T) => Promise<void>,
): (event: Event) => void {
    return (event: Event) => {
        const target = event.currentTarget as T;
        handler(target).catch((error) => {
            console.error('Async change handler error:', error);
            displayErrorMessage('An unexpected error occurred.');
        });
    };
}

/**
 * Initialize user profile page functionality
 */
export function initializeUserProfile(): void {
    const syncDropdown = document.getElementById('notification_sync_preference') as HTMLSelectElement;
    if (syncDropdown) {
        syncDropdown.addEventListener('change', createAsyncChangeHandler(handleProfileSyncChange));
    }
}

// Auto-initialize based on page context
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on notification settings page
    if (document.getElementById('sync-notifications-btn')) {
        initializeNotificationSettings();
    }

    // Check if we're on user profile page
    if (document.getElementById('notification_sync_preference')) {
        initializeUserProfile();
    }
});
