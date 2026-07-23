import MarkConflictPopup from '../../vue/src/components/ta_grading/MarkConflictPopup.vue';

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

const showPopup = (conflicts, componentTitle = 'Test Component') => {
    cy.window().then((win) => {
        win.dispatchEvent(new CustomEvent('show-conflict-popup', {
            detail: { conflicts, componentTitle },
        }));
    });
};

const resolveCurrent = () => {
    cy.window().then((win) => {
        win.dispatchEvent(new CustomEvent('conflict-resolved'));
    });
};

describe('MarkConflictPopup', () => {
    beforeEach(() => {
        cy.mount(MarkConflictPopup);
    });

    it('is hidden by default', () => {
        cy.get('[data-testid="popup-window"]').should('not.exist');
    });

    it('opens popup and renders conflict rows with all three mark versions', () => {
        showPopup([makeConflict({
            domMark: makeMark({ id: 1, points: 2, title: 'Edited', publish: false }),
            serverMark: makeMark({ id: 1, points: 1, title: 'Server', publish: false }),
            oldServerMark: makeMark({ id: 1, points: 0, title: 'Original', publish: false }),
        })]);

        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.contains('h1', 'Mark Conflicts: Test Component');
        cy.get('[data-testid="mark-conflict-old-server-info"]').should('contain.text', '(0) Original');
        cy.get('[data-testid="mark-conflict-server-info"]').should('contain.text', '(1) Server');
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', '(2) Edited');
    });

    it('handles missing old server mark gracefully', () => {
        showPopup([makeConflict({ oldServerMark: null })]);
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="mark-conflict-old-server"]').should('not.exist');
    });

    it('shows deleted-message when server mark is null', () => {
        showPopup([makeConflict({ serverMark: null })]);
        cy.get('[data-testid="mark-conflict-server-deleted"]').should('be.visible');
        cy.get('[data-testid="mark-conflict-server-btn"]').should('have.value', 'Delete Mark');
    });

    it('shows deleted-message when local mark is deleted', () => {
        showPopup([makeConflict({ localDeleted: true })]);
        cy.get('[data-testid="mark-conflict-dom-deleted"]').should('be.visible');
        cy.get('[data-testid="mark-conflict-dom-btn"]').should('have.value', 'Delete Mark');
    });

    it('shows publish indicator and hides it for non-publishable marks', () => {
        // publish: true
        showPopup([makeConflict({
            domMark: makeMark({ id: 1, title: 'Pub', publish: true }),
        })]);
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Show mark to all students');

        // Re-open with publish: false
        cy.get('[data-testid="close-button"]').click();
        showPopup([makeConflict({
            domMark: makeMark({ id: 1, title: 'Unpub', publish: false }),
        })]);
        cy.get('[data-testid="mark-conflict-dom-info"]').should('not.contain.text', 'Show mark to all students');
    });

    it('shows and hides progress indicator based on conflict count', () => {
        showPopup([makeConflict(), makeConflict({ domMark: makeMark({ id: 2 }) })]);
        cy.get('[data-testid="mark-conflict-progress"]').should('be.visible').and('contain.text', '1 out of 2');

        cy.get('[data-testid="close-button"]').click();
        showPopup([makeConflict()]);
        cy.get('[data-testid="mark-conflict-progress"]').should('not.exist');
    });

    it('advances to next conflict and dispatches all-conflicts-resolved on last', () => {
        const resolvedSpy = cy.stub();
        cy.window().then((win) => {
            win.addEventListener('all-conflicts-resolved', resolvedSpy);
        });

        showPopup([
            makeConflict({ domMark: makeMark({ id: 1, title: 'First' }) }),
            makeConflict({ domMark: makeMark({ id: 2, title: 'Second' }) }),
        ]);

        // On first conflict
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'First');
        resolveCurrent();

        // Advances to second
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Second');
        resolveCurrent();

        // Last: popup closes, event fires
        cy.get('[data-testid="popup-window"]').should('not.exist');
        cy.wrap(resolvedSpy).should('have.been.calledOnce');
    });

    it('emits correct resolution type per button', () => {
        const spy = cy.stub();
        cy.window().then((win) => {
            win.addEventListener('resolve-conflict', spy);
        });

        showPopup([makeConflict()]);

        cy.get('[data-testid="mark-conflict-old-server-btn"]').click();
        cy.wrap(spy).should('have.been.calledWithMatch', { detail: { markId: 1, resolution: 'old-server' } });

        cy.get('[data-testid="mark-conflict-server-btn"]').click();
        cy.wrap(spy).should('have.been.calledWithMatch', { detail: { markId: 1, resolution: 'server' } });

        cy.get('[data-testid="mark-conflict-dom-btn"]').click();
        cy.wrap(spy).should('have.been.calledWithMatch', { detail: { markId: 1, resolution: 'dom' } });
    });

    it('closes popup on X and dispatches close-conflict-popup', () => {
        const spy = cy.stub();
        cy.window().then((win) => {
            win.addEventListener('close-conflict-popup', spy);
        });

        showPopup([makeConflict()]);
        cy.get('[data-testid="close-button"]').click();
        cy.get('[data-testid="popup-window"]').should('not.exist');
        cy.wrap(spy).should('have.been.calledOnce');
    });

    it('closes on Escape key', () => {
        showPopup([makeConflict()]);
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.document().trigger('keydown', { key: 'Escape' });
        cy.get('[data-testid="popup-window"]').should('not.exist');
    });

    it('handles close mid-resolution without false all-conflicts-resolved', () => {
        const spy = cy.stub();
        cy.window().then((win) => {
            win.addEventListener('all-conflicts-resolved', spy);
        });

        showPopup([makeConflict(), makeConflict({ domMark: makeMark({ id: 2 }) })]);
        cy.get('[data-testid="close-button"]').click();
        cy.wrap(spy).should('not.have.been.called');
    });

    it('resets state when re-opened with new data', () => {
        showPopup([makeConflict({ domMark: makeMark({ id: 1, title: 'A' }) }),
            makeConflict({ domMark: makeMark({ id: 2, title: 'B' }) })]);
        resolveCurrent();
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'B');

        showPopup([makeConflict({ domMark: makeMark({ id: 3, title: 'Fresh' }) })]);
        cy.get('[data-testid="mark-conflict-dom-info"]').should('contain.text', 'Fresh');
        cy.get('[data-testid="mark-conflict-progress"]').should('not.exist');
    });

    it('has accessible close button', () => {
        showPopup([makeConflict()]);
        cy.get('[data-testid="close-button"]').should('be.visible').and('not.have.attr', 'aria-hidden');
    });
});
