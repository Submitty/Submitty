import { buildCourseUrl } from './utils/server';

export interface BonusLateDayRoster {
	date: string;
	users: string[];
}

interface RosterResponse {
	status: string;
	data?: { users: string[] }
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
 * Resolves with an empty list when no file exists yet
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
				resolve(response.data?.users?? [] );
			},
			error(err) {
				console.error(err);
				reject(new Error('Could not load the student list.'));
			},
		});
	});
}

/**
 * Replace the roster for a date with an explicit list of user ids
 * 
 * CSV is written atomically
 */
export function saveBonusLateDayRoster(date: string, users: string[]): Promise<string[]> {
	
}
