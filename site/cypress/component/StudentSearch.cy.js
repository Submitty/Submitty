import StudentSearch from '../../vue/src/components/sidebar/StudentSearch.vue';

describe('StudentSearch', () => {
    const students = [
        {
            user_id: 'adamsg',
            given_name: 'Greg',
            family_name: 'Adams',
            courses: [
                { term: 'f26', course: 'sample' },
                { term: 'f26', course: 'tutorial' },
            ],
        },
    ];

    const searchRoute = { method: 'GET', pathname: '/home/students/search' };

    beforeEach(() => {
        document.body.dataset.baseUrl = '/';
    });

    function searchFor(text) {
        cy.get('[data-testid="student-search-input"]').type(text);
    }

    it('renders the search input with no results shown', () => {
        cy.mount(StudentSearch);
        cy.get('[data-testid="student-search-input"]').should('exist');
        cy.get('[data-testid="student-search-results"]').should('not.exist');
    });

    it('shows each student once with course links', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'success', data: students } }).as('search');
        cy.mount(StudentSearch);
        searchFor('adams');
        cy.wait('@search').its('request.url').should('include', 'q=adams');
        cy.get('[data-testid="student-search-results"]')
            .should('contain', 'Greg Adams')
            .and('contain', '(adamsg)');
        cy.get('[data-testid="student-search-results"] a').should('have.length', 2);
        cy.get('[data-testid="student-search-results"] a').first()
            .should('have.attr', 'href', '/courses/f26/sample');
        cy.get('[data-testid="student-search-results"] a').last()
            .should('have.attr', 'href', '/courses/f26/tutorial');
    });

    it('debounces typing into a single request', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'success', data: students } }).as('search');
        cy.mount(StudentSearch);
        searchFor('ada');
        cy.wait('@search').its('request.url').should('include', 'q=ada');
        cy.get('@search.all').should('have.length', 1);
    });

    it('shows an empty message when nothing matches', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'success', data: [] } }).as('search');
        cy.mount(StudentSearch);
        searchFor('zzz');
        cy.wait('@search');
        cy.get('[data-testid="student-search-results"]').should('contain', 'No students found');
    });

    it('shows the empty message when the server returns a failure status', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'fail', message: 'No access' } }).as('search');
        cy.mount(StudentSearch);
        searchFor('adams');
        cy.wait('@search');
        cy.get('[data-testid="student-search-results"]').should('contain', 'No students found');
    });

    it('shows the empty message when the response is not valid JSON', () => {
        cy.intercept(searchRoute, { statusCode: 500, body: 'oops' }).as('search');
        cy.mount(StudentSearch);
        searchFor('adams');
        cy.wait('@search');
        cy.get('[data-testid="student-search-results"]').should('contain', 'No students found');
    });

    it('hides results when the query is cleared below two characters', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'success', data: students } }).as('search');
        cy.mount(StudentSearch);
        searchFor('adams');
        cy.wait('@search');
        cy.get('[data-testid="student-search-results"]').should('exist');
        cy.get('[data-testid="student-search-input"]').clear();
        cy.get('[data-testid="student-search-results"]').should('not.exist');
    });

    it('clears the input and closes results on Escape', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'success', data: students } }).as('search');
        cy.mount(StudentSearch);
        searchFor('adams');
        cy.wait('@search');
        cy.get('[data-testid="student-search-input"]').type('{esc}');
        cy.get('[data-testid="student-search-input"]').should('have.value', '');
        cy.get('[data-testid="student-search-results"]').should('not.exist');
    });

    it('closes when focus leaves and reopens when the input is focused again', () => {
        cy.intercept(searchRoute, { statusCode: 200, body: { status: 'success', data: students } }).as('search');
        cy.mount(StudentSearch);
        searchFor('adams');
        cy.wait('@search');
        cy.get('[data-testid="student-search-results"]').should('exist');
        cy.get('[data-testid="student-search-input"]').blur();
        cy.get('[data-testid="student-search-results"]').should('not.exist');
        cy.get('[data-testid="student-search-input"]').focus();
        cy.get('[data-testid="student-search-results"]').should('contain', 'Greg Adams');
    });
});
