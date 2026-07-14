import TableSortManager from '../../vue/src/components/table_sorting/TableSortManager.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('TableSortManager', () => {
    describe('rendering', () => {
        it('renders an empty placeholder div', () => {
            cy.mount(TableSortManager, { props: { tableId: 'student-table' } });
            cy.get('div').should('exist');
            cy.get('div').should('be.empty');
        });
    });

    describe('emit on mount', () => {
        it('emits restore-sort with the tableId on mount', () => {
            mountWithEmitSpy(TableSortManager, 'restore-sort', { tableId: 'docker-table' }, 'restoreSortHandler');
            cy.get('@restoreSortHandler').should('have.been.calledWith', 'docker-table');
        });

        it('emits restore-sort exactly once per mount', () => {
            mountWithEmitSpy(TableSortManager, 'restore-sort', { tableId: 'docker-table' }, 'restoreSortHandler');
            cy.get('@restoreSortHandler').should('have.callCount', 1);
        });

        it('emits restore-sort with different table ID values', () => {
            mountWithEmitSpy(TableSortManager, 'restore-sort', { tableId: 'student-table' }, 'restoreSortHandler');
            cy.get('@restoreSortHandler').should('have.been.calledWith', 'student-table');
        });

        it('emits restore-sort with an empty tableId', () => {
            mountWithEmitSpy(TableSortManager, 'restore-sort', { tableId: '' }, 'restoreSortHandler');
            cy.get('@restoreSortHandler').should('have.been.calledWith', '');
        });
    });
});
