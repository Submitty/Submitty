import { ajaxAddNewMark, ajaxDeleteMark, ajaxSaveMark, getComponentJQuery, getGradeableId, isMarkDeleted, MarkConflicts } from './ta-grading-rubric';
import { updateVueComponent } from './utils/vue';

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

declare global {
    interface Window {
        handleConflictResolve?: (detail: ResolveConflictDetail) => void;
        handleConflictClose?: () => void;
    }
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

// Shows the MarkConflictPopup Vue component via updateVueComponent and returns a Promise
// that resolves once all conflicts are resolved or the popup is closed.

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

        let currentIndex = 0;

        function showConflict() {
            updateVueComponent('.js-mark-conflict-popup', {
                conflicts: conflictsData,
                componentTitle: componentTitle,
                currentIndex: currentIndex,
            });
        }

        function cleanup() {
            delete window.handleConflictResolve;
            delete window.handleConflictClose;
        }

        window.handleConflictResolve = (detail: ResolveConflictDetail) => {
            const { markId, resolution } = detail;
            const conflict = conflictMarks[markId];

            void (async () => {
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

                // Advance to next conflict or close if all resolved
                currentIndex++;
                if (currentIndex >= conflictsData.length) {
                    // Clear conflicts to prevent the component's watcher from re-showing the popup
                    updateVueComponent('.js-mark-conflict-popup', {
                        conflicts: [],
                        componentTitle: '',
                        currentIndex: 0,
                    });
                    cleanup();
                    resolve();
                }
                else {
                    showConflict();
                }
            })();
        };

        window.handleConflictClose = () => {
            cleanup();
            resolve();
        };

        // Show the popup with first conflict
        // The Vue component auto-shows when conflicts.length > 0
        showConflict();
    });
}
