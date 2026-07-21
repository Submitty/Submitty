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
        it('emits restore-table-sort with the tableId on mount', () => {
            mountWithEmitSpy(TableSortManager, 'restore-table-sort', { tableId: 'docker-table' }, 'restoreTableSortHandler');
            cy.get('@restoreTableSortHandler').should('have.been.calledWith', 'docker-table');
        });

        it('emits restore-table-sort exactly once per mount', () => {
            mountWithEmitSpy(TableSortManager, 'restore-table-sort', { tableId: 'docker-table' }, 'restoreTableSortHandler');
            cy.get('@restoreTableSortHandler').should('have.callCount', 1);
        });

        it('emits restore-table-sort with different table ID values', () => {
            mountWithEmitSpy(TableSortManager, 'restore-table-sort', { tableId: 'student-table' }, 'restoreTableSortHandler');
            cy.get('@restoreTableSortHandler').should('have.been.calledWith', 'student-table');
        });

        it('emits restore-table-sort with an empty tableId', () => {
            mountWithEmitSpy(TableSortManager, 'restore-table-sort', { tableId: '' }, 'restoreTableSortHandler');
            cy.get('@restoreTableSortHandler').should('have.been.calledWith', '');
        });
    });
});
