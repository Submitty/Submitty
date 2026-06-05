import ToggleAllSectionsButton from '../../vue/src/components/ta_grading/ToggleAllSectionsButton.vue';

describe('ToggleAllSectionsButton', () => {
    const SECTIONS = ['section1', 'section2'];

    const addFixtureTable = (doc, { collapsedIds = [] } = {}) => {
        const table = doc.createElement('table');
        table.id = 'details-table';
        table.setAttribute('data-details-base-path', '/base/path');
        ['thead', 'tbody'].forEach((tag) => {
            const row = doc.createElement(tag === 'thead' ? 'thead' : 'tbody');
            row.innerHTML = tag === 'thead'
                ? '<tr><th>Name</th></tr>'
                : '<tr><td>Dummy</td></tr>';
            table.appendChild(row);
        });
        SECTIONS.forEach((id) => {
            const isCollapsed = collapsedIds.includes(id);
            const hdr = doc.createElement('tbody');
            hdr.className = `details-info-header${isCollapsed ? '' : ' panel-head-active'}`;
            hdr.setAttribute('data-section-id', id);
            hdr.innerHTML = '<tr><td>Header</td></tr>';
            table.appendChild(hdr);
            const content = doc.createElement('tbody');
            content.className = 'details-content';
            content.style.display = isCollapsed ? 'none' : '';
            content.innerHTML = '<tr><td>Content</td></tr>';
            table.appendChild(content);
        });
        doc.body.appendChild(table);
    };

    let store;

    const mockCookies = (initialValue = '[]') => {
        store = { collapsed_sections: initialValue };
        window.Cookies = {
            get: (key) => store[key],
            set: (key, value) => { store[key] = value; },
        };
    };

    beforeEach(() => {
        document.getElementById('details-table')?.remove();
        delete window.Cookies;
        store = null;
    });

    it('shows "Collapse All" when no sections are collapsed, "Expand All" when some are', () => {
        mockCookies('[]');
        addFixtureTable(document);
        cy.mount(ToggleAllSectionsButton);
        cy.get('[data-testid="toggle-all-sections"]').should('have.text', 'Collapse All Sections').click().should('have.text', 'Expand All Sections');
    });

    it('shows "Expand All" on mount when cookie says sections are collapsed', () => {
        mockCookies(JSON.stringify(['section1']));
        addFixtureTable(document, { collapsedIds: ['section1'] });
        cy.mount(ToggleAllSectionsButton);
        cy.get('[data-testid="toggle-all-sections"]').should('have.text', 'Expand All Sections');
    });

    it('collapse all removes panel-head-active, hides content, writes cookie', () => {
        mockCookies('[]');
        addFixtureTable(document);
        cy.mount(ToggleAllSectionsButton);
        cy.get('[data-testid="toggle-all-sections"]').click();
        cy.get('.details-info-header').each(($el) => {
            cy.wrap($el).should('not.have.class', 'panel-head-active');
        });
        cy.get('.details-content').each(($el) => {
            cy.wrap($el).should('have.css', 'display', 'none');
        });
        cy.then(() => {
            expect(JSON.parse(store.collapsed_sections)).to.deep.equal(SECTIONS);
        });
    });

    it('expand all adds panel-head-active, shows content, clears cookie', () => {
        mockCookies(JSON.stringify(SECTIONS));
        addFixtureTable(document, { collapsedIds: SECTIONS });
        cy.mount(ToggleAllSectionsButton);
        cy.get('[data-testid="toggle-all-sections"]').click();
        cy.get('.details-info-header').each(($el) => {
            cy.wrap($el).should('have.class', 'panel-head-active');
        });
        cy.get('.details-content').each(($el) => {
            cy.wrap($el).should('not.have.css', 'display', 'none');
        });
        cy.then(() => {
            expect(JSON.parse(store.collapsed_sections)).to.deep.equal([]);
        });
    });

    it('delegated click on a section header toggles it individually', () => {
        mockCookies('[]');
        addFixtureTable(document);
        cy.mount(ToggleAllSectionsButton);
        // Collapse first section via delegated click
        cy.get('.details-info-header').first().click();
        cy.get('.details-info-header').first().should('not.have.class', 'panel-head-active');
        cy.get('.details-content').first().should('have.css', 'display', 'none');
        cy.then(() => {
            expect(JSON.parse(store.collapsed_sections)).to.include('section1');
        });
        // Expand it again
        cy.get('.details-info-header').first().click();
        cy.get('.details-info-header').first().should('have.class', 'panel-head-active');
        cy.get('.details-content').first().should('not.have.css', 'display', 'none');
        cy.then(() => {
            expect(JSON.parse(store.collapsed_sections)).to.not.include('section1');
        });
    });

    it('handles missing table and missing window.Cookies gracefully', () => {
        delete window.Cookies;
        cy.mount(ToggleAllSectionsButton);
        cy.get('[data-testid="toggle-all-sections"]').should('be.visible').click();
    });

    it('handles invalid cookie value without crashing', () => {
        mockCookies('{{{not json-jndlansh');
        addFixtureTable(document);
        cy.mount(ToggleAllSectionsButton);
        cy.get('[data-testid="toggle-all-sections"]').should('have.text', 'Collapse All Sections');
    });

    it('removes DOM event listeners on unmount', () => {
        mockCookies('[]');
        Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
        cy.mount(ToggleAllSectionsButton).then(({ wrapper }) => {
            wrapper.unmount();
            document.dispatchEvent(new Event('DOMContentLoaded'));
            addFixtureTable(document);
            cy.get('.details-info-header').first().click();
            // Without listener, class should be unchanged
            cy.get('.details-info-header').first().should('have.class', 'panel-head-active');
        });
        Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });
    });
});
