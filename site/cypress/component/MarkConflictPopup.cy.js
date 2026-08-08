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
};

describe('MarkConflictPopup', () => {
    describe('display states', () => {
        it('is hidden when there are no conflicts', () => {
            cy.mount(MarkConflictPopup, { props: defaultProps });
            cy.get('[data-testid="popup-window"]').should('not.exist');

            cy.mount(MarkConflictPopup, { props: { ...defaultProps, conflicts: null } });
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
            cy.get('[data-testid="popup-window"]').should('contain.text', 'Mark Conflicts: Test Component');
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

        it('shows deleted messages when a mark version is missing', () => {
            // Server mark deleted
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({ serverMark: null })),
                },
            });
            cy.get('[data-testid="mark-conflict-server-deleted"]').should('be.visible');
            cy.get('[data-testid="mark-conflict-server-btn"]').should('have.value', 'Delete Mark');

            // Local mark deleted
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

        it('honors the currentIndex prop - renders and resolves the targeted conflict', () => {
            mountWithEmitSpy(MarkConflictPopup, 'resolve', {
                ...defaultProps,
                currentIndex: 1,
                conflicts: makeConflicts(
                    makeConflict({ domMark: makeMark({ id: 1, title: 'First' }) }),
                    makeConflict({ domMark: makeMark({ id: 2, title: 'Second' }) }),
                ),
            }, 'onResolve');

            cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Second');
            cy.get('[data-testid="mark-conflict-progress"]').should('contain.text', '2 out of 2');

            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('@onResolve').should('have.been.calledWith', { markId: 2, resolution: 'dom' });
        });

        it('renders empty titles when marks have no title', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict({
                        domMark: makeMark({ id: 1, title: undefined }),
                        serverMark: makeMark({ id: 1, title: undefined }),
                        oldServerMark: makeMark({ id: 1, title: undefined }),
                    })),
                },
            });
            cy.get('[data-testid="mark-conflict-old-server-info"]')
                .should('contain.text', '(2)')
                .and('not.contain.text', 'Read me');
            cy.get('[data-testid="mark-conflict-server-info"]')
                .should('contain.text', '(2)')
                .and('not.contain.text', 'Read me');
            cy.get('[data-testid="mark-conflict-dom-info"]')
                .should('contain.text', '(2)')
                .and('not.contain.text', 'Read me');
        });
    });

    describe('resolve emits (parent performs the AJAX)', () => {
        it('emits resolve with the dom resolution and mark id when Use My Edits is clicked', () => {
            mountWithEmitSpy(MarkConflictPopup, 'resolve', {
                ...defaultProps,
                conflicts: makeConflicts(makeConflict()),
            }, 'onResolve');

            cy.get('[data-testid="mark-conflict-dom-btn"]').click();
            cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'dom' });
        });

        it('emits resolve with the old-server resolution when Revert to Original is clicked', () => {
            mountWithEmitSpy(MarkConflictPopup, 'resolve', {
                ...defaultProps,
                conflicts: makeConflicts(makeConflict()),
            }, 'onResolve');

            cy.get('[data-testid="mark-conflict-old-server-btn"]').click();
            cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'old-server' });
        });

        it('emits resolve with the server resolution when Ignore My Edits is clicked', () => {
            mountWithEmitSpy(MarkConflictPopup, 'resolve', {
                ...defaultProps,
                conflicts: makeConflicts(makeConflict()),
            }, 'onResolve');

            cy.get('[data-testid="mark-conflict-server-btn"]').click();
            cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'server' });
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

    describe('accessibility', () => {
        it('exposes accessible names on all resolution buttons', () => {
            cy.mount(MarkConflictPopup, {
                props: {
                    ...defaultProps,
                    conflicts: makeConflicts(makeConflict()),
                },
            });
            cy.get('[data-testid="mark-conflict-dom-btn"]').should('have.attr', 'title', 'Use my local edits');
            cy.get('[data-testid="mark-conflict-server-btn"]').should('have.attr', 'title', 'Ignore my edits, keep server version');
            cy.get('[data-testid="mark-conflict-old-server-btn"]').should('have.attr', 'title', 'Revert to original mark');
        });

        it('resolves via keyboard activation (Enter) on a resolution button', () => {
            mountWithEmitSpy(MarkConflictPopup, 'resolve', {
                ...defaultProps,
                conflicts: makeConflicts(makeConflict()),
            }, 'onResolve');

            cy.get('[data-testid="mark-conflict-dom-btn"]').focus();
            cy.get('[data-testid="mark-conflict-dom-btn"]').type('{enter}');
            cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'dom' });
        });
    });
});
