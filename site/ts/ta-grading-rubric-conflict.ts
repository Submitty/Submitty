import { ajaxAddNewMark, ajaxDeleteMark, ajaxSaveMark, getComponentJQuery, getGradeableId, isMarkDeleted, MarkConflicts } from './ta-grading-rubric';

interface MarkInfo {
    id: number;
    points: number;
    title: string | null;
    publish: boolean;
}

interface ResolveConflictDetail {
    markId: number;
    resolution: 'dom' | 'server' | 'old-server';
}

function prepConflictMarks(conflictMarks: MarkConflicts) {
    for (const id in conflictMarks) {
        if (Object.prototype.hasOwnProperty.call(conflictMarks, id)) {
            conflictMarks[id].localDeleted = isMarkDeleted(parseInt(id));
        }
    }
}

function buildMarkInfo(mark: { id: number; points: number; title: string | undefined; publish: boolean }): MarkInfo {
    return { id: mark.id, points: mark.points, title: mark.title ?? null, publish: mark.publish };
}

/**
 * Prompts the user with an array of conflict marks so they can individually resolve them.
 * Dispatches a CustomEvent to the Vue MarkConflictPopup component and returns a Promise
 * that resolves once all conflicts are resolved or the popup is closed.
 */
export function openMarkConflictPopup(component_id: number, conflictMarks: MarkConflicts): Promise<void> {
    return new Promise((resolve) => {
        const gradeable_id = getGradeableId();
        const componentTitle = getComponentJQuery(component_id).attr('data-title')!;

        prepConflictMarks(conflictMarks);

        // Build serializable data for the Vue component
        const conflictsData = Object.values(conflictMarks).map((c) => ({
            domMark: buildMarkInfo(c.domMark),
            serverMark: c.serverMark ? buildMarkInfo(c.serverMark) : null,
            oldServerMark: c.oldServerMark ? buildMarkInfo(c.oldServerMark) : null,
            localDeleted: c.localDeleted,
        }));

        // Signal the Vue component to show the popup
        window.dispatchEvent(new CustomEvent('show-conflict-popup', {
            detail: { conflicts: conflictsData, componentTitle },
        }));

        let cleanedUp = false;
        function cleanup() {
            if (cleanedUp) {
                return;
            }
            cleanedUp = true;
            window.removeEventListener('resolve-conflict', resolveConflictHandler);
            window.removeEventListener('all-conflicts-resolved', resolveAllHandler);
            window.removeEventListener('close-conflict-popup', closeHandler);
            resolve();
        }

        // Handle each resolution choice from the component
        const resolveConflictHandler = (e: Event) => {
            void (async () => {
                const detail = (e as CustomEvent).detail as ResolveConflictDetail;
                const { markId, resolution } = detail;
                const conflict = conflictMarks[markId];

                try {
                    if (resolution === 'dom') {
                        if (conflict.localDeleted) {
                            await ajaxDeleteMark(gradeable_id, component_id, markId);
                        }
                        else {
                            const isServerDeleted = conflict.serverMark === null;
                            if (isServerDeleted) {
                                const data = await ajaxAddNewMark(gradeable_id, component_id, conflict.domMark.title!, conflict.domMark.points, conflict.domMark.publish);
                                conflict.domMark.id = data.mark_id;
                            }
                            else {
                                await ajaxSaveMark(gradeable_id, component_id, markId, conflict.domMark.title!, conflict.domMark.points, conflict.domMark.publish);
                            }
                        }
                    }
                    else if (resolution === 'old-server') {
                        const mark = conflict.oldServerMark!;
                        await ajaxSaveMark(gradeable_id, component_id, markId, mark.title!, mark.points, mark.publish);
                    }
                    // resolution === 'server': accept server state, no AJAX needed
                }
                catch (err) {
                    console.error(`Failed to resolve conflict for mark ${markId}:`, err);
                }

                // Tell the component to advance to the next conflict
                window.dispatchEvent(new CustomEvent('conflict-resolved'));
            })();
        };

        const resolveAllHandler = () => cleanup();
        const closeHandler = () => cleanup();

        window.addEventListener('resolve-conflict', resolveConflictHandler);
        window.addEventListener('all-conflicts-resolved', resolveAllHandler);
        window.addEventListener('close-conflict-popup', closeHandler);
    });
}
