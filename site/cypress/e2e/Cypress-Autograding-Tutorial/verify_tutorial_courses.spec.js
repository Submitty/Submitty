const checkGradeable = (gradeable_id) => {
    cy.visit(['tutorial']);
    cy.get(`[data-testid="${gradeable_id}"`).find('[data-testid="submit-btn"]').click();
    cy.get('[data-testid="new-submission-info"]').should('contain.text', 'New submission for');
};

describe('Test course should exist', () => {
    it('Check test gradeables exists', () => {
        cy.login('instructor');
        checkGradeable('01_simple_python');
        checkGradeable('02_simple_cpp');
        checkGradeable('03_multipart');
        checkGradeable('04_python_static_analysis');
        checkGradeable('05_cpp_static_analysis');
        checkGradeable('06_loop_types');
        checkGradeable('07_loop_depth');
        checkGradeable('08_memory_debugging');
        checkGradeable('09_java_testing');
        checkGradeable('10_java_coverage');
        checkGradeable('11_resources');
        checkGradeable('12_system_calls');
        checkGradeable('13_cmake_compilation');
        checkGradeable('14_tkinter');
        checkGradeable('15_GLFW');
        checkGradeable('16_docker_network_python');
        checkGradeable('17_dispatched_actions_and_standard_input');
        // checkGradeable('18_postgres_database');
    });
});
