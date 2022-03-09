import { getCsrfToken } from '../utils/server';

export function regrade(single_regrade: number, highest_version: number, gradeable_id: string, user_id: string) {
    //if only regrading active version, late day fields left as 0 because they are irrelevant for regrading
    if (single_regrade) {
        window.handleRegrade(highest_version, getCsrfToken(), gradeable_id, user_id, true);
    }
    //regrading all versions
    else {
        window.handleRegrade(highest_version, getCsrfToken(), gradeable_id, user_id, false, true);
    }
}
