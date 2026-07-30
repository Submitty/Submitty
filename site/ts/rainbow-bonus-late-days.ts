import { buildCourseUrl } from './utils/server';

export interface BonusLateDayRoster {
    date: string;
    users: string[];
}

interface RosterResponse {
    status: string;
    data?: { users: string[] };
    message?: string;
}

interface SaveResponse {
    status: string;
    data?: { date: string; filename: string; users: string[] };
    message?: string;
}

function bonusLateDaysUrl(action: string): string {
    return buildCourseUrl(['reports', 'rainbow_grades_customization', 'bonus_late_days', action]);
}

/**
 * Read the roster for a given date.
 *
 * Resolves with an empty list when no file exists yet, so a brand new date and an
 * existing one follow the same path in the caller.
 */
export function loadBonusLateDayRoster(date: string): Promise<string[]> {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: bonusLateDaysUrl('roster'),
            type: 'GET',
            dataType: 'json',
            data: {
                csrf_token: window.csrfToken,
                date: date,
            },
            success(response: RosterResponse) {
                if (response.status !== 'success') {
                    reject(new Error(response.message ?? 'Could not load the student list.'));
                    return;
                }
                resolve(response.data?.users ?? []);
            },
            error(err) {
                console.error(err);
                reject(new Error('Could not load the student list.'));
            },
        });
    });
}

/**
 * Replace the roster for a date with an explicit list of user ids.
 *
 * The server rewrites the csv atomically, so a nightly build running concurrently
 * never observes a partially written file.
 */
export function saveBonusLateDayRoster(date: string, users: string[]): Promise<string[]> {
    const formData = new FormData();
    formData.append('csrf_token', window.csrfToken);
    formData.append('date', date);
    users.forEach((user) => formData.append('users[]', user));

    return new Promise((resolve, reject) => {
        $.ajax({
            url: bonusLateDaysUrl('roster'),
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            success(response: SaveResponse) {
                if (response.status !== 'success') {
                    reject(new Error(response.message ?? 'Could not save the student list.'));
                    return;
                }
                resolve(response.data?.users ?? users);
            },
            error(err) {
                console.error(err);
                reject(new Error('Could not save the student list.'));
            },
        });
    });
}

/**
 * Bulk import: the uploaded csv replaces the roster wholesale rather than merging,
 * so an instructor can always get back to a known state by re-uploading.
 */
export function uploadBonusLateDayRoster(date: string, file: File): Promise<string[]> {
    const formData = new FormData();
    formData.append('csrf_token', window.csrfToken);
    formData.append('date', date);
    formData.append('bonus_late_days_upload', file);

    return new Promise((resolve, reject) => {
        $.ajax({
            url: bonusLateDaysUrl('upload'),
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            success(response: SaveResponse) {
                if (response.status !== 'success') {
                    reject(new Error(response.message ?? 'Could not upload the file.'));
                    return;
                }
                resolve(response.data?.users ?? []);
            },
            error(err) {
                console.error(err);
                reject(new Error('Could not upload the file.'));
            },
        });
    });
}

/**
 * Remove both the customization entry and the csv on disk.
 */
export function deleteBonusLateDay(date: string): Promise<void> {
    const formData = new FormData();
    formData.append('csrf_token', window.csrfToken);
    formData.append('date', date);

    return new Promise((resolve, reject) => {
        $.ajax({
            url: bonusLateDaysUrl('delete'),
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            success(response: { status: string; message?: string }) {
                if (response.status !== 'success') {
                    reject(new Error(response.message ?? 'Could not delete the bonus late day.'));
                    return;
                }
                resolve();
            },
            error(err) {
                console.error(err);
                reject(new Error('Could not delete the bonus late day.'));
            },
        });
    });
}

export function bonusLateDayFilename(date: string): string {
    return `bonus_late_days_${date}.csv`;
}

export function isIsoDate(value: string): boolean {
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
}
