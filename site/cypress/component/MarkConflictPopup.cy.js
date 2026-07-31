import MarkConflictPopup from '../../vue/src/components/ta_grading/MarkConflictPopup.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const makeMark = (overrides = {}) => ({
    id: 1, points: 2, title: 'Read me', publish: false, ...overrides,
});

const makeConflict = (overrides = {}) => ({
    domMark: makeMark({ id: 1, title: 'Read me (edited)' }),
    serverMark: makeMark({ id: 1, title: 'Read me (server)' }),
    oldServerMark: makeMark({ id: 1, title: 'Read me' }),
    localDeleted: false,
    ...overrides,
});

const makeConflicts = (...conflicts) => {
    const record = {};
    conflicts.forEach((c) => {
        record[c.domMark.id] = c;
    });
    return record;
};

const defaultProps = {
    conflicts: {},
    componentTitle: 'Test Component',
    componentId: 1,
    gradeableId: 'test-gradeable',
};

describe('MarkConflictPopup', () => {
    describe('display states', () => {
        it('is hidden when there are no conflicts', () => {
            cy.mount(MarkConflictPopup, { props: defaultProps });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('auto-opens and renders all three mark versions', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({
                        domMark: makeMark({ id: 1, points: 2, title: 'Edited', publish: false }),
                        serverMark: makeMark({ id: 1, points: 1, title: 'Server', publish: false }),
                        oldServerMark: makeMark({ id: 1, points: 0, title: 'Original', publish: false }),
                    })),
                },
            });

            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.contains('h1', 'Mark Conflicts: Test Component');
            cy.get('[data-testid="mark-conflict-old-server-info"]').should('contain.text', '(0) Original');
            cy.get('[data-testid="mark-conflict-server-info"]').should('contain.text', '(1) Server');
            cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', '(2) Edited');
        });

        it('omits the old-server row when the old server mark is missing', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({ oldServerMark: null })),
                },
            });
            cy.get('[data-testid="mark-conflict-old-server"]').should('not.exist');
        });

        it('shows deleted message when the server mark is null', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({ serverMark: null })),
                },
            });
            cy.get('[data-testid="mark-conflict-server-deleted"]').should('be.visible');
            cy.get('[data-testid="mark-conflict-server-btn"]').should('have.value', 'Delete Mark');
        });

        it('shows deleted message when the local mark is deleted', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({ localDeleted: true })),
                },
            });
            cy.get('[data-testid="mark-conflict-dom-deleted"]').should('be.visible');
            cy.get('[data-testid="mark-conflict-dom-btn"]').should('have.value', 'Delete Mark');
        });

        it('shows the publish indicator for publishable marks and hides it for unpublishable ones', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({
                        domMark: makeMark({ id: 1, title: 'Pub', publish: true }),
                    })),
                },
            });
            cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Show mark to all students');

            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({
                        domMark: makeMark({ id: 1, title: 'Unpub', publish: false }),
                    })),
                },
            });
            cy.get('[data-testid="mark-conflict-dom-info"]').should('not.contain.text', 'Show mark to all students');
        });

        it('shows the progress indicator only when there are multiple conflicts', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(
                        makeConflict(),
                        makeConflict({ domMark: makeMark({ id: 2 }) }),
                    ),
                },
            });
            cy.get('[data-testid="mark-conflict-progress"]').should('be.visible').and('contain.text', '1 out of 2');

            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict()),
                },
            });
            cy.get('[data-testid="mark-conflict-progress"]').should('not.exist');
        });
    });

    describe('conflict resolution (success path)', () => {
        beforeEach(() => {
            cy.stub(window, 'fetch').as('fetch').resolves({
                ok: true,
                json: cy.stub().resolves({ status: 'success', data: { mark_id: 99 } }),
            });
        });

        it('calls the correct endpoint for each resolution type', () => {
            // dom -> save
            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict()) } });
            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('@fetch').should('have.been.calledWithMatch', /components\/marks\/save$/, { method: 'POST' });

            // old-server -> save
            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict()) } });
            cy.get('[data-testid="mark-conflict-old-server-btn"]').click();
            cy.get('@fetch').should('have.been.calledWithMatch', /components\/marks\/save$/, { method: 'POST' });

            // server (deleted) -> add
            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict({ serverMark: null })) } });
            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('@fetch').should('have.been.calledWithMatch', /components\/marks\/add$/, { method: 'POST' });

            // localDeleted -> delete
            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict({ localDeleted: true })) } });
            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('@fetch').should('have.been.calledWithMatch', /components\/marks\/delete$/, { method: 'POST' });
        });

        it('does not call fetch when Ignore My Edits is clicked', () => {
            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict()) } });
            cy.get('[data-testid="mark-conflict-server-btn"]').click();
            cy.get('@fetch').should('not.have.been.called');
        });

        it('mutates the shared domMark.id when the server mark was deleted', () => {
            const conflicts = makeConflicts(makeConflict({ serverMark: null }));
            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts } });
            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.wrap(conflicts).its('1.domMark.id').should('eq', 99);
        });

        it('advances to the next conflict and emits all-resolved on the last one', () => {
            mountWithEmitSpy(MarkConflictPopup, 'all-resolved', {
                ...defaultProps,
                conflicts: makeConflicts(
                    makeConflict({ domMark: makeMark({ id: 1, title: 'First' }) }),
                    makeConflict({ domMark: makeMark({ id: 2, title: 'Second' }) }),
                ),
            }, 'onAllResolved');

            cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'First');
            cy.get('[data-testid="mark-conflict-progress"]').should('contain.text', '1 out of 2');

            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Second');
            cy.get('[data-testid="mark-conflict-progress"]').should('contain.text', '2 out of 2');

            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('@onAllResolved').should('have.callCount', 1);
        });
    });

    describe('conflict resolution (error path)', () => {
        it('keeps the popup open when fetch fails', () => {
            cy.stub(window, 'fetch').as('fetch').rejects(new Error('Network error'));

            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict()) } });
            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="mark-conflict-progress"]').should('not.exist');
        });

        it('stays on the same conflict when the server returns a non-success status', () => {
            cy.stub(window, 'fetch').as('fetch').resolves({
                ok: true,
                json: cy.stub().resolves({ status: 'fail', message: 'Bad mark' }),
            });

            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: makeConflicts(makeConflict()) } });
            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="mark-conflict-progress"]').should('not.exist');
        });
    });

    describe('emits', () => {
        it('emits close when the close button is clicked', () => {
            mountWithEmitSpy(MarkConflictPopup, 'close', {
                ...defaultProps,
                conflicts: makeConflicts(makeConflict()),
            }, 'onClose');

            cy.get('[data-testid="close-button"]').click();
            cy.get('@onClose').should('have.callCount', 1);
        });
    });
});