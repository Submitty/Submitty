import PanelSelectorModal from '../../vue/src/components/ta_grading/PanelSelectorModal.vue';

describe('PanelSelectorModal', () => {
    beforeEach(() => {
        cy.window().then((win) => {
            win.changePanelsLayout = cy.stub().as('changePanelsLayout');
        });
    });

    describe('rendering', () => {
        it('is hidden when visible prop is false', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: false },
            });

            cy.get('#panels-selector-modal').should('not.be.visible');
        });

        it('is visible when visible prop is true', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#panels-selector-modal').should('be.visible');
        });

        it('shows all four layout sections with titles', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.contains('h2', 'Single panel option').should('be.visible');
            cy.contains('h2', 'Two panel options').should('be.visible');
            cy.contains('h2', 'Three panel options').should('be.visible');
            cy.contains('h2', 'Four panel options').should('be.visible');
        });

        it('shows all nine canvas elements', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            const canvasIds = [
                'single-panel',
                'equal-height',
                'tall-left',
                'equal-two-in-left',
                'equal-two-in-right',
                'tall-left-two-in-left',
                'tall-left-two-in-right',
                'equal-four-panel',
                'tall-left-four-panel',
            ];

            canvasIds.forEach((id) => {
                cy.get(`#${id}`).should('exist');
            });
        });

        it('draws canvas content — single-panel has aliceblue background', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#single-panel').should(($canvas) => {
                const ctx = $canvas[0].getContext('2d');
                const pixel = ctx.getImageData(0, 0, 1, 1).data;
                // aliceblue = rgba(240, 248, 255, 255)
                expect(pixel[0]).to.equal(240);
                expect(pixel[1]).to.equal(248);
                expect(pixel[2]).to.equal(255);
                expect(pixel[3]).to.equal(255);
            });
        });

        it('draws canvas content — panel rectangle has #6d91b5 fill', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            // This pixel should be inside one of the header rectangles
            cy.get('#single-panel').should(($canvas) => {
                const ctx = $canvas[0].getContext('2d');
                const pixel = ctx.getImageData(6, 3, 1, 1).data;
                // #6d91b5 = rgba(109, 145, 181, 255)
                expect(pixel[0]).to.equal(109);
                expect(pixel[1]).to.equal(145);
                expect(pixel[2]).to.equal(181);
                expect(pixel[3]).to.equal(255);
            });
        });
    });

    describe('close', () => {
        it('emits close when Close button is clicked', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: true, onClose },
            });

            cy.get('[data-testid="close-button"]').click();
            cy.get('@onClose').should('have.been.calledOnce');
        });

        it('emits close when overlay (popup-box) is clicked', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: true, onClose },
            });

            cy.get('.popup-box').click({ force: true });
            cy.get('@onClose').should('have.been.calledOnce');
        });

        it('does NOT emit close when clicking inside popup-window', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: true, onClose },
            });

            cy.get('[data-testid="popup-window"]').click({ force: true });
            cy.get('@onClose').should('not.have.been.called');
        });

        it('emits close on Escape key', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: true, onClose },
            });

            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@onClose').should('have.been.calledOnce');
        });

        it('does NOT emit close on Escape when modal is hidden', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: false, onClose },
            });

            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@onClose').should('not.have.been.called');
        });

        it('does NOT emit close on non-Escape key', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: true, onClose },
            });

            cy.document().trigger('keydown', { key: 'Enter' });
            cy.get('@onClose').should('not.have.been.called');
        });
    });

    describe('CustomEvent toggle-panel-modal', () => {
        it('shows modal when event fires with detail: true', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: false },
            });

            cy.get('#panels-selector-modal').should('not.be.visible');

            cy.window().then((win) => {
                win.dispatchEvent(new CustomEvent('toggle-panel-modal', { detail: true }));
            });

            cy.get('#panels-selector-modal').should('be.visible');
        });

        it('hides modal when event fires with detail: false', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#panels-selector-modal').should('be.visible');

            cy.window().then((win) => {
                win.dispatchEvent(new CustomEvent('toggle-panel-modal', { detail: false }));
            });

            cy.get('#panels-selector-modal').should('not.be.visible');
        });
    });

    describe('Apply buttons', () => {
        it('calls window.changePanelsLayout(1, false) for single panel', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-1 .btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 1, false, false);
        });

        it('calls window.changePanelsLayout(2, false) for side-by-side equal', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-2 .layout-option-item').first().find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 2, false, false);
        });

        it('calls window.changePanelsLayout(2, true) for side-by-side taller left', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-2 .layout-option-item').eq(1).find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 2, true, false);
        });

        it('calls window.changePanelsLayout(3, false) for equal two in left', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-3 .layout-option-item').first().find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 3, false, false);
        });

        it('calls window.changePanelsLayout(3, true) for tall left two in left', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-3 .layout-option-item').eq(1).find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 3, true, false);
        });

        it('calls window.changePanelsLayout(3, false, true) for equal two in right', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-3 .layout-option-item').eq(2).find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 3, false, true);
        });

        it('calls window.changePanelsLayout(3, true, true) for tall left two in right', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-3 .layout-option-item').eq(3).find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 3, true, true);
        });

        it('calls window.changePanelsLayout(4, false) for equal four panel', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-4 .layout-option-item').first().find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 4, false, false);
        });

        it('calls window.changePanelsLayout(4, true) for tall left four panel', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-4 .layout-option-item').eq(1).find('.btn-primary').click();
            cy.get('@changePanelsLayout').should('have.been.calledOnceWith', 4, true, false);
        });

        it('calls window.changePanelsLayout on every Apply click (no debounce)', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.get('#layout-option-1 .btn-primary').click();
            cy.get('#layout-option-1 .btn-primary').click();
            cy.get('#layout-option-1 .btn-primary').click();

            cy.get('@changePanelsLayout').should('have.callCount', 3);
        });
    });

    describe('lifecycle', () => {
        it('cleans up event listeners on unmount', () => {
            const onClose = cy.stub().as('onClose');

            cy.mount(PanelSelectorModal, {
                props: { visible: true, onClose },
            }).then(({ wrapper }) => {
                wrapper.unmount();
            });

            // After unmount, Escape should not trigger close
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@onClose').should('not.have.been.called');

            // After unmount, CustomEvent should be handled (listener removed)
            cy.window().then((win) => {
                win.dispatchEvent(new CustomEvent('toggle-panel-modal', { detail: false }));
            });
        });
    });

    describe('accessibility', () => {
        it('has visible heading and close button text', () => {
            cy.mount(PanelSelectorModal, {
                props: { visible: true },
            });

            cy.contains('h1', 'Panel Selector').should('be.visible');
            cy.get('[data-testid="close-button"]').should('contain', 'Close');
        });
    });
});
