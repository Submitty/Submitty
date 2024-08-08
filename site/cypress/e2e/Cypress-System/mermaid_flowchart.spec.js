describe('Mermaid Flowcharts', () => {
    it('Visual testing', () => {
        cy.viewport(1200, 900);
        cy.login();
        cy.visit('http://localhost:1511/courses/s24/tutorial/gradeable/16_docker_network_python/grading/grade?who_id=QvzNFLl2MK88Skw&sort=id&direction=ASC');
        cy.get('[data-testid="autograding-results"]').should('contain', 'Autograding Testcases');
        cy.get('#nav-sidebar-collapse-sidebar').click();
        cy.get('#tc_0').should('contain', 'Simple Testcase, No Router');
        cy.get('#tc_1').should('contain', 'Simple Testcase, With Router');
        cy.get('#tc_2').should('contain', 'Moderate Testcase, With Router');
        cy.get('#tc_3').should('contain', 'Large Testcase, With Router');
        // simple testcase, with router
        cy.get('#tc_1').click();
        cy.get('#container_1_3_0').scrollIntoView();
        cy.get('#container_1_3_0').compareSnapshot('simple_with_router');
        // moderate testcase, with router
        // cy.viewport(1200, 1200);
        cy.get('#tc_2').click();
        cy.get('#container_2_3_0').scrollIntoView();
        cy.get('#container_2_3_0').compareSnapshot('moderate_with_router');
        // large testcase, with router
        // cy.viewport(1800, 1800);
        cy.get('#tc_3').click();
        cy.get('#container_3_4_0').scrollIntoView();
        cy.get('#container_3_4_0').compareSnapshot('large_with_router');
    });
});
