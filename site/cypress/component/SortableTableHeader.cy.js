import SortableTableHeader from '../../vue/src/components/table_sorting/SortableTableHeader.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const defaultProps = {
    tableId: 'docker-table',
    title: 'Image Name',
    sortKey: 'name',
    colDataType: 'string',
    usingRowGroups: false,
};

describe('SortableTableHeader', () => {
    describe('rendering', () => {
        it('renders the title text and sort icon', () => {
            cy.mount(SortableTableHeader, { props: defaultProps });
            cy.get('[data-testid=sortable-header-link]').should('contain', 'Image Name');
            cy.get('[data-testid=sortable-header-link] i').should('have.class', 'fa-sort');
        });

        it('sets aria-label and title attributes', () => {
            cy.mount(SortableTableHeader, { props: defaultProps });
            cy.get('[data-testid=sortable-header-link]')
                .should('have.attr', 'aria-label', 'Sort by Image Name')
                .and('have.attr', 'title', 'Sort by Image Name');
        });

        it('sets data-sort-key from sortKey prop', () => {
            cy.mount(SortableTableHeader, { props: defaultProps });
            cy.get('[data-testid=sortable-header-link]').should('have.attr', 'data-sort-key', 'name');
        });
    });

    describe('click behavior', () => {
        it('emits sort-table-column-click with correct payload on click', () => {
            mountWithEmitSpy(SortableTableHeader, 'sort-table-column-click', defaultProps, 'sortClickHandler');
            cy.get('[data-testid=sortable-header-link]').click();
            cy.get('@sortClickHandler').should('have.been.calledWith', {
                tableId: 'docker-table',
                sortKey: 'name',
                colDataType: 'string',
                usingRowGroups: false,
            });
        });

        it('emits sort-table-column-click on every click', () => {
            mountWithEmitSpy(SortableTableHeader, 'sort-table-column-click', defaultProps, 'sortClickHandler');
            cy.get('[data-testid=sortable-header-link]').click();
            cy.get('[data-testid=sortable-header-link]').click();
            cy.get('@sortClickHandler').should('have.callCount', 2);
        });
    });

    describe('prop variants', () => {
        it('passes usingRowGroups: true in the emit payload', () => {
            mountWithEmitSpy(SortableTableHeader, 'sort-table-column-click', {
                ...defaultProps,
                sortKey: 'total_posts',
                usingRowGroups: true,
            }, 'sortClickHandler');
            cy.get('[data-testid=sortable-header-link]').click();
            cy.get('@sortClickHandler').should('have.been.calledWith', {
                tableId: 'docker-table',
                sortKey: 'total_posts',
                colDataType: 'string',
                usingRowGroups: true,
            });
        });

        it('passes each colDataType variant in the emit payload', () => {
            ['string', 'number', 'date'].forEach((dataType) => {
                mountWithEmitSpy(SortableTableHeader, 'sort-table-column-click', {
                    ...defaultProps,
                    sortKey: `col_${dataType}`,
                    colDataType: dataType,
                }, `sortClickHandler_${dataType}`);
                cy.get('[data-testid=sortable-header-link]').click();
                cy.get(`@sortClickHandler_${dataType}`).should('have.been.calledWith', {
                    tableId: 'docker-table',
                    sortKey: `col_${dataType}`,
                    colDataType: dataType,
                    usingRowGroups: false,
                });
            });
        });

        it('renders title with special characters', () => {
            cy.mount(SortableTableHeader, {
                props: { ...defaultProps, title: 'Score (out of 100%)' },
            });
            cy.get('[data-testid=sortable-header-link]')
                .should('contain', 'Score (out of 100%)')
                .and('have.attr', 'aria-label', 'Sort by Score (out of 100%)');
        });
    });
});
