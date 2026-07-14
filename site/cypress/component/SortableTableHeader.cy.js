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
            cy.get('a.sortable-header').should('contain', 'Image Name');
            cy.get('a.sortable-header i').should('have.class', 'fa-sort');
        });

        it('sets aria-label and title attributes', () => {
            cy.mount(SortableTableHeader, { props: defaultProps });
            cy.get('a.sortable-header')
                .should('have.attr', 'aria-label', 'Sort by Image Name')
                .and('have.attr', 'title', 'Sort by Image Name');
        });

        it('sets data-sort-key from sortKey prop', () => {
            cy.mount(SortableTableHeader, { props: defaultProps });
            cy.get('a.sortable-header').should('have.attr', 'data-sort-key', 'name');
        });
    });

    describe('click behavior', () => {
        it('emits sort-click with correct payload on click', () => {
            mountWithEmitSpy(SortableTableHeader, 'sort-click', defaultProps, 'sortClickHandler');
            cy.get('a.sortable-header').click();
            cy.get('@sortClickHandler').should('have.been.calledWith', {
                tableId: 'docker-table',
                sortKey: 'name',
                colDataType: 'string',
                usingRowGroups: false,
            });
        });

        it('emits sort-click on every click', () => {
            mountWithEmitSpy(SortableTableHeader, 'sort-click', defaultProps, 'sortClickHandler');
            cy.get('a.sortable-header').click().click();
            cy.get('@sortClickHandler').should('have.callCount', 2);
        });
    });

    describe('prop variants', () => {
        it('passes usingRowGroups: true in the emit payload', () => {
            mountWithEmitSpy(SortableTableHeader, 'sort-click', {
                ...defaultProps,
                sortKey: 'total_posts',
                usingRowGroups: true,
            }, 'sortClickHandler');
            cy.get('a.sortable-header').click();
            cy.get('@sortClickHandler').should('have.been.calledWith', {
                tableId: 'docker-table',
                sortKey: 'total_posts',
                colDataType: 'string',
                usingRowGroups: true,
            });
        });

        it('passes each colDataType variant in the emit payload', () => {
            const colDataTypeCases = ['string', 'number', 'date'];
            colDataTypeCases.forEach((dataType) => {
                mountWithEmitSpy(SortableTableHeader, 'sort-click', {
                    ...defaultProps,
                    sortKey: `col_${dataType}`,
                    colDataType: dataType,
                }, `sortClickHandler_${dataType}`);
                cy.get('a.sortable-header').click();
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
            cy.get('a.sortable-header')
                .should('contain', 'Score (out of 100%)')
                .and('have.attr', 'aria-label', 'Sort by Score (out of 100%)');
        });
    });
});
