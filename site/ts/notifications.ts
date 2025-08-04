import { buildCourseUrl } from './utils/server';

declare global {
    interface Window {
        displaySuccessMessage: (message: string) => void;
        displayErrorMessage: (message: string) => void;
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
 * Initialize notification settings page functionality
 */
export function initializeNotificationSettings(): void {
    // TODO: Turn this into Vue?
    const syncButton = document.getElementById('sync-notifications-btn') as HTMLButtonElement;

    if (syncButton) {
        syncButton.addEventListener('click', async (event: Event) => {
            // TODO: fix invalid usage of async event handler
            event.preventDefault();

            const currentlySynced = syncButton.textContent?.trim() === 'Unsync Notifications';
            const newSyncState = !currentlySynced;

            // Disable button to prevent multiple clicks
            syncButton.disabled = true;
            syncButton.textContent = 'Updating...';

            try {
                const response = await updateNotificationSync(newSyncState);

                if (response.status === 'success') {
                    syncButton.textContent = newSyncState ? 'Unsync Notifications' : 'Sync Notifications';
                    syncButton.className = newSyncState ? 'btn btn-primary' : 'btn btn-default';

                    if (response.data) {
                        displaySuccessMessage(response.data.message);
                    }

                    const syncInfo = document.querySelector('.sync-info') as HTMLElement;
                    if (syncInfo) {
                        syncInfo.textContent = `Last sync: ${new Date().toLocaleString()}`;
                    }
                }
                else {
                    displayErrorMessage(response.data?.message || 'Failed to update sync settings.');
                }
            }
            catch (error) {
                console.error(error);
                displayErrorMessage('An error occurred while updating sync settings.');
            }
            finally {
                syncButton.disabled = false;
            }
        });
    }

    const setDefaultsButton = document.getElementById('set-defaults-btn') as HTMLButtonElement;
    if (setDefaultsButton) {
        setDefaultsButton.addEventListener('click', async (event: Event) => {
            event.preventDefault();

            setDefaultsButton.disabled = true;
            setDefaultsButton.textContent = 'Updating...';

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
                setDefaultsButton.disabled = false;
            }
        });
    }

    const clearDefaultsButton = document.getElementById('clear-defaults-btn') as HTMLButtonElement;
    if (clearDefaultsButton) {
        clearDefaultsButton.addEventListener('click', async (event: Event) => {
            event.preventDefault();

            clearDefaultsButton.disabled = true;
            clearDefaultsButton.textContent = 'Updating...';

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
                clearDefaultsButton.disabled = false;
            }
        });
    }
}

/**
 * Initialize user profile page functionality
 */
export function initializeUserProfile(): void {
    const syncDropdown = document.getElementById('notification_sync_preference') as HTMLSelectElement;
    if (syncDropdown) {
        syncDropdown.addEventListener('change', async () => {
            const synced = syncDropdown.value === 'sync';

            // Disable dropdown to prevent multiple clicks
            syncDropdown.disabled = true;
            syncDropdown.textContent = 'Updating...';

            try {
                const response = await fetch(buildCourseUrl(['notifications', 'settings', 'sync']), {
                    method: 'POST',
                    body: new URLSearchParams({
                        notifications_synced: synced.toString(),
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
                    syncDropdown.value = synced ? 'unsync' : 'sync';
                    displayErrorMessage(responseData.data?.message || 'Failed to update sync preference.');
                }
            }
            catch (error) {
                console.error(error);
                // Revert dropdown if API call failed
                syncDropdown.value = synced ? 'unsync' : 'sync';
                displayErrorMessage('An error occurred while updating sync preference.');
            }
            finally {
                syncDropdown.disabled = false;
            }
        });
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
