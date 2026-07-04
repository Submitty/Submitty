import PanelSelectorModal from '../../vue/src/components/ta_grading/PanelSelectorModal.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

describe('PanelSelectorModal', () => {
    describe('rendering', () => {
        it('is visible when mounted', () => {
            cy.mount(PanelSelectorModal);

            cy.get('#panels-selector-modal').should('be.visible');
        });

        it('shows all four layout section headings', () => {
            cy.mount(PanelSelectorModal);

            cy.contains('h2', 'Single panel option').should('be.visible');
            cy.contains('h2', 'Two panel options').should('be.visible');
            cy.contains('h2', 'Three panel options').should('be.visible');
            cy.contains('h2', 'Four panel options').should('be.visible');
        });

        it('draws canvas content', () => {
            cy.mount(PanelSelectorModal);

            cy.get('#single-panel').should(($canvas) => {
                const ctx = $canvas[0].getContext('2d');
                // aliceblue fill at top-left proves canvas was drawn
                const pixel = ctx.getImageData(0, 0, 1, 1).data;
                expect(pixel[0]).to.equal(240);
                expect(pixel[1]).to.equal(248);
                expect(pixel[2]).to.equal(255);
                expect(pixel[3]).to.equal(255);
            });
        });
    });

    describe('close emit', () => {
        it('emits close when Close button is clicked', () => {
            mountWithEmitSpy(PanelSelectorModal, 'close');

            cy.get('[data-testid="close-button"]').click();
            cy.get('@eventHandler').should('have.been.calledOnce');
        });

        it('emits close when overlay (popup-box) is clicked', () => {
            mountWithEmitSpy(PanelSelectorModal, 'close');

            cy.get('.popup-box').click({ force: true });
            cy.get('@eventHandler').should('have.been.calledOnce');
        });

        it('does NOT emit close when clicking inside popup-window', () => {
            mountWithEmitSpy(PanelSelectorModal, 'close');

            cy.get('[data-testid="popup-window"]').click({ force: true });
            cy.get('@eventHandler').should('not.have.been.called');
        });
    });

    describe('select-layout emit', () => {
        it('emits select-layout with default twoInRight=false', () => {
            mountWithEmitSpy(PanelSelectorModal, 'selectLayout', {}, 'eventHandler');

            cy.get('#layout-option-1 .btn-primary').click();
            cy.get('@eventHandler').should('have.been.calledOnceWith', 1, false, false);
        });

        it('emits select-layout with twoInRight=true', () => {
            mountWithEmitSpy(PanelSelectorModal, 'selectLayout', {}, 'eventHandler');

            cy.get('#layout-option-3 .layout-option-item').eq(2).find('.btn-primary').click();
            cy.get('@eventHandler').should('have.been.calledOnceWith', 3, false, true);
        });

        it('emits select-layout on every Apply click', () => {
            mountWithEmitSpy(PanelSelectorModal, 'selectLayout', {}, 'eventHandler');

            cy.get('#layout-option-1 .btn-primary').click();
            cy.get('#layout-option-1 .btn-primary').click();
            cy.get('#layout-option-1 .btn-primary').click();

            cy.get('@eventHandler').should('have.callCount', 3);
        });
    });

    describe('accessibility', () => {
        it('has visible heading and close button text', () => {
            cy.mount(PanelSelectorModal);

            cy.contains('h1', 'Panel Selector').should('be.visible');
            cy.get('[data-testid="close-button"]').should('contain', 'Close');
        });
    });
});
