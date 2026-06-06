import DetailsFiltersControls from '../../vue/src/components/ta_grading/DetailsFiltersControls.vue';

describe('DetailsFiltersControls', () => {
    let store;

    const mockCookies = (initial = {}) => {
        store = { ...initial };
        window.Cookies = {
            get: (key) => store[key],
            set: (key, value) => { store[key] = value; },
        };
    };

    const defaultProps = () => ({
        showAllSections: false,
        toggleAnon: false,
        gradeInquiryOnly: false,
        canFilterWithdrawn: false,
        anonMode: false,
        isTeamAssignment: false,
    });

    const addGradeRows = () => {
        for (let i = 0; i < 5; i++) {
            const row = document.createElement('tr');
            row.className = 'grade-table';
            const hasInquiry = i === 0;
            const btn = hasInquiry
                ? '<button class="grade-button" data-grade-inquiry="active">Grade</button>'
                : '<button class="grade-button">Grade</button>';
            row.innerHTML = `<td>${btn}</td>`;
            document.body.appendChild(row);
        }
    };

    const addWithdrawnRow = (selector) => {
        const row = document.createElement('tr');
        row.setAttribute('data-student', selector);
        row.innerHTML = '<td>Withdrawn</td>';
        row.style.display = 'none';
        document.body.appendChild(row);
    };

    beforeEach(() => {
        delete window.Cookies;
        store = null;
        document.body.dataset.coursePath = '/course';
        window.updateElectronicGradingRowNumbersAndColors = () => {};
        window.updateSimpleGradingRowNumbersAndColors = () => {};
        document.querySelectorAll('.grade-table, [data-student]').forEach((e) => e.remove());
    });

    it('renders only the always-visible filter when all feature props are false', () => {
        cy.mount(DetailsFiltersControls, { props: defaultProps() });
        cy.get('[data-testid="random-order-label"]').should('exist');
        cy.get('[data-testid="view-sections-label"]').should('not.exist');
        cy.get('[data-testid="anon-students-label"]').should('not.exist');
        cy.get('[data-testid="inquiry-only-label"]').should('not.exist');
        cy.get('[data-testid="filter-withdrawn-label"]').should('not.exist');
    });

    it('renders all conditional filters when their props are true', () => {
        cy.mount(DetailsFiltersControls, {
            props: {
                showAllSections: true,
                toggleAnon: true,
                gradeInquiryOnly: true,
                canFilterWithdrawn: true,
                anonMode: false,
                isTeamAssignment: false,
            },
        });
        cy.get('[data-testid="view-sections-label"]').should('exist');
        cy.get('[data-testid="anon-students-label"]').should('exist');
        cy.get('[data-testid="inquiry-only-label"]').should('exist');
        cy.get('[data-testid="filter-withdrawn-label"]').should('exist');
    });

    describe('initial state from cookies and props', () => {
        it('checks view-sections when the "view" cookie is "assigned"', () => {
            mockCookies({ view: 'assigned' });
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), showAllSections: true },
            });
            cy.get('[data-testid="view-sections"]').should('be.checked');
        });

        it('checks view-sections when no "view" cookie exists (defaults to assigned)', () => {
            mockCookies({});
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), showAllSections: true },
            });
            cy.get('[data-testid="view-sections"]').should('be.checked');
        });

        it('checks random-order when the "sort" cookie is "random"', () => {
            mockCookies({ sort: 'random' });
            cy.mount(DetailsFiltersControls, { props: defaultProps() });
            cy.get('[data-testid="random-order-checkbox"]').should('be.checked');
        });

        it('checks the inquiry checkbox and applies inquiry-only-disabled when cookie is "on"', () => {
            mockCookies({ inquiry_status: 'on' });
            addGradeRows();
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), gradeInquiryOnly: true },
            });
            cy.get('[data-testid="inquiry-only-checkbox"]').should('be.checked');
            cy.get('.grade-table').eq(0).should('not.have.class', 'inquiry-only-disabled');
            cy.get('.grade-table').eq(1).should('have.class', 'inquiry-only-disabled');
        });

        it('checks anon-students when the anonMode prop is true', () => {
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), toggleAnon: true, anonMode: true },
            });
            cy.get('[data-testid="anon-students-checkbox"]').should('be.checked');
        });

        it('renders gradeableId as a data attribute on the anon label', () => {
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), toggleAnon: true, gradeableId: 'hw1' },
            });
            cy.get('[data-testid="anon-students-label"]').should('have.attr', 'data-gradeable-id', 'hw1');
        });
    });

    describe('inquiry filter toggles without page reload', () => {
        it('hides non-inquiry rows when checked, shows them when unchecked', () => {
            mockCookies({ inquiry_status: 'off' });
            addGradeRows();
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), gradeInquiryOnly: true },
            });
            cy.get('.grade-table').eq(1).should('not.have.class', 'inquiry-only-disabled');
            cy.get('[data-testid="inquiry-only-checkbox"]').check({ force: true });
            cy.get('.grade-table').eq(1).should('have.class', 'inquiry-only-disabled');
            cy.then(() => {
                expect(store.inquiry_status).to.equal('on');
            });
            cy.get('[data-testid="inquiry-only-checkbox"]').uncheck({ force: true });
            cy.get('.grade-table').eq(1).should('not.have.class', 'inquiry-only-disabled');
            cy.then(() => {
                expect(store.inquiry_status).to.equal('off');
            });
        });

        it('preserves rows with active data-grade-inquiry', () => {
            mockCookies({ inquiry_status: 'off' });
            addGradeRows();
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), gradeInquiryOnly: true },
            });
            cy.get('.grade-table').eq(0).should('not.have.class', 'inquiry-only-disabled');
            cy.get('[data-testid="inquiry-only-checkbox"]').check({ force: true });
            cy.get('.grade-table').eq(0).should('not.have.class', 'inquiry-only-disabled');
        });
    });

    describe('withdrawn filter toggles without page reload', () => {
        it('hides and shows withdrawn rows, writes cookie, calls update functions', () => {
            mockCookies({ include_withdrawn_students: 'include' });
            window.updateElectronicGradingRowNumbersAndColors = cy.stub().as('updateElectronics');
            window.updateSimpleGradingRowNumbersAndColors = cy.stub().as('updateSimple');
            addWithdrawnRow('electronic-grade-withdrawn');
            addWithdrawnRow('simple-grade-withdrawn');
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), canFilterWithdrawn: true },
            });
            cy.get('[data-student="electronic-grade-withdrawn"]').should('be.visible');
            cy.get('[data-testid="filter-withdrawn-checkbox"]').check({ force: true });
            cy.get('[data-student="electronic-grade-withdrawn"]').should('not.be.visible');
            cy.get('[data-student="simple-grade-withdrawn"]').should('not.be.visible');
            cy.then(() => {
                expect(store.include_withdrawn_students).to.equal('omit');
            });
            cy.get('@updateElectronics').should('have.been.called');
            cy.get('@updateSimple').should('have.been.called');
            cy.get('[data-testid="filter-withdrawn-checkbox"]').uncheck({ force: true });
            cy.get('[data-student="electronic-grade-withdrawn"]').should('be.visible');
            cy.get('[data-student="simple-grade-withdrawn"]').should('be.visible');
            cy.then(() => {
                expect(store.include_withdrawn_students).to.equal('include');
            });
        });
    });

    describe('edge cases', () => {
        it('handles missing window.Cookies without crashing', () => {
            delete window.Cookies;
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), gradeInquiryOnly: true, canFilterWithdrawn: true },
            });
            cy.get('[data-testid="random-order-label"]').should('exist');
            cy.get('[data-testid="inquiry-only-checkbox"]').should('not.be.checked');
        });

        it('handles missing document.body.dataset.coursePath without crashing', () => {
            mockCookies({ view: 'assigned' });
            delete document.body.dataset.coursePath;
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), showAllSections: true },
            });
            cy.get('[data-testid="view-sections"]').should('be.checked');
        });

        it('handles an invalid cookie value by treating it as "off"', () => {
            mockCookies({ inquiry_status: 'garbage' });
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), gradeInquiryOnly: true },
            });
            cy.get('[data-testid="inquiry-only-checkbox"]').should('not.be.checked');
        });

        it('shows withdrawn rows on team assignments regardless of cookie', () => {
            mockCookies({ include_withdrawn_students: 'omit' });
            addWithdrawnRow('electronic-grade-withdrawn');
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), isTeamAssignment: true },
            });
            cy.get('[data-student="electronic-grade-withdrawn"]').should('be.visible');
        });
    });
});
