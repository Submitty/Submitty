import { getCurrentSemester } from '../../support/utils.js';

describe('Tests for auto removal of trailing slash in url', () => {
    beforeEach(() => {
        cy.login();
    });

    const BASE_URL = `/courses/${getCurrentSemester()}/sample`;

    [
        BASE_URL,
        `${BASE_URL}/gradeable`,
        `${BASE_URL}/forum`,
        `${BASE_URL}/course_materials`,
        `${BASE_URL}/office_hours_queue`,
    ].forEach((url) =>
        it(`removes trailing slash for ${url}`, () => {
            cy.visit(`${url}/`);
            cy.location('pathname').should('eq', url);
        }),
    );
});
