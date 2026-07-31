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

const defaultProps = {
    conflicts: [],
    componentTitle: 'Test Component',
    currentIndex: 0,
};

describe('MarkConflictPopup', () => {
    it('is hidden by default when there are no conflicts', () => {
        cy.mount(MarkConflictPopup, { props: defaultProps });
        cy.get('[data-testid="popup-window"]').should('not.exist');
    });

    it('auto-opens and renders the title and all three mark versions when conflicts are provided', () => {
        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [makeConflict({
                    domMark: makeMark({ id: 1, points: 2, title: 'Edited', publish: false }),
                    serverMark: makeMark({ id: 1, points: 1, title: 'Server', publish: false }),
                    oldServerMark: makeMark({ id: 1, points: 0, title: 'Original', publish: false }),
                })],
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
                conflicts: [makeConflict({ oldServerMark: null })],
            },
        });
        cy.get('[data-testid="mark-conflict-old-server"]').should('not.exist');
    });

    it('shows deleted message and emits resolve when the server mark was deleted', () => {
        mountWithEmitSpy(MarkConflictPopup, 'resolve', {
            ...defaultProps,
            conflicts: [makeConflict({ serverMark: null })],
        }, 'onResolve');

        cy.get('[data-testid="mark-conflict-server-deleted"]').should('be.visible');
        cy.get('[data-testid="mark-conflict-server-btn"]').should('have.value', 'Delete Mark');
        cy.get('[data-testid="mark-conflict-server-btn"]').click();
        cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'server' });
    });

    it('shows deleted message and emits resolve when the local mark was deleted', () => {
        mountWithEmitSpy(MarkConflictPopup, 'resolve', {
            ...defaultProps,
            conflicts: [makeConflict({ localDeleted: true })],
        }, 'onResolve');

        cy.get('[data-testid="mark-conflict-dom-deleted"]').should('be.visible');
        cy.get('[data-testid="mark-conflict-dom-btn"]').should('have.value', 'Delete Mark');
        cy.get('[data-testid="mark-conflict-dom-btn"]').click();
        cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'dom' });
    });

    it('shows the publish indicator only for publishable marks', () => {
        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [makeConflict({
                    domMark: makeMark({ id: 1, title: 'Pub', publish: true }),
                })],
            },
        });
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Show mark to all students');

        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [makeConflict({
                    domMark: makeMark({ id: 1, title: 'Unpub', publish: false }),
                })],
            },
        });
        cy.get('[data-testid="mark-conflict-dom-info"]').should('not.contain.text', 'Show mark to all students');
    });

    it('shows the progress indicator only when there are multiple conflicts', () => {
        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [makeConflict(), makeConflict({ domMark: makeMark({ id: 2 }) })],
                currentIndex: 0,
            },
        });
        cy.get('[data-testid="mark-conflict-progress"]').should('be.visible').and('contain.text', '1 out of 2');

        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [makeConflict()],
                currentIndex: 0,
            },
        });
        cy.get('[data-testid="mark-conflict-progress"]').should('not.exist');
    });

    it('renders the conflict at the given currentIndex', () => {
        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [
                    makeConflict({ domMark: makeMark({ id: 1, title: 'First' }) }),
                    makeConflict({ domMark: makeMark({ id: 2, title: 'Second' }) }),
                ],
                currentIndex: 0,
            },
        });
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'First');
        cy.get('[data-testid="mark-conflict-progress"]').should('contain.text', '1 out of 2');

        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [
                    makeConflict({ domMark: makeMark({ id: 1, title: 'First' }) }),
                    makeConflict({ domMark: makeMark({ id: 2, title: 'Second' }) }),
                ],
                currentIndex: 1,
            },
        });
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Second');
        cy.get('[data-testid="mark-conflict-progress"]').should('contain.text', '2 out of 2');
    });

    it('emits resolve with dom resolution when Use My Edits is clicked', () => {
        mountWithEmitSpy(MarkConflictPopup, 'resolve', {
            ...defaultProps,
            conflicts: [makeConflict({ domMark: makeMark({ id: 7 }) })],
        }, 'onResolve');

        cy.get('[data-testid="mark-conflict-dom-btn"]').click();
        cy.get('@onResolve').should('have.been.calledWith', { markId: 7, resolution: 'dom' });
    });

    it('emits resolve with server resolution when Ignore My Edits is clicked', () => {
        mountWithEmitSpy(MarkConflictPopup, 'resolve', {
            ...defaultProps,
            conflicts: [makeConflict()],
        }, 'onResolve');

        cy.get('[data-testid="mark-conflict-server-btn"]').click();
        cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'server' });
    });

    it('emits resolve with old-server resolution when Revert to Original is clicked', () => {
        mountWithEmitSpy(MarkConflictPopup, 'resolve', {
            ...defaultProps,
            conflicts: [makeConflict()],
        }, 'onResolve');

        cy.get('[data-testid="mark-conflict-old-server-btn"]').click();
        cy.get('@onResolve').should('have.been.calledWith', { markId: 1, resolution: 'old-server' });
    });

    it('emits close when the close button is clicked', () => {
        mountWithEmitSpy(MarkConflictPopup, 'close', {
            ...defaultProps,
            conflicts: [makeConflict()],
        }, 'onClose');

        cy.get('[data-testid="close-button"]').click();
        cy.get('@onClose').should('have.callCount', 1);
    });

    it('has title attributes on the resolution buttons', () => {
        cy.mount(MarkConflictPopup, {
            props: {
                ...defaultProps,
                conflicts: [makeConflict()],
            },
        });
        cy.get('[data-testid="mark-conflict-old-server-btn"]').should('have.attr', 'title', 'Revert to original mark');
        cy.get('[data-testid="mark-conflict-server-btn"]').should('have.attr', 'title', 'Ignore my edits, keep server version');
        cy.get('[data-testid="mark-conflict-dom-btn"]').should('have.attr', 'title', 'Use my local edits');
    });
});
