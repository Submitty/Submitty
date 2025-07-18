const checkGradeable = (gradeable_id) => {
    cy.visit(['development']);
    cy.get(`[data-testid="${gradeable_id}"`).find('[data-testid="submit-btn"]').click();

    if (gradeable_id === 'notebook_time_limit') {
        cy.get('[data-testid="load-message-accept"]').should('exist');
        cy.get('[data-testid="load-gradeable-message"]').should('contain.text', 'This gradeable is TIMED');
        return;
    }

    cy.get('[data-testid="new-submission-info"]').should('contain.text', 'New submission for');
};

describe('Test course should exist', () => {
    it('Check test gradeables exists', () => {
        cy.login('instructor');
        checkGradeable('c_failure_messages');
        checkGradeable('c_malloc_not_allowed');
        checkGradeable('choice_of_language');
        checkGradeable('comment_count');
        checkGradeable('cpp_buggy_custom');
        checkGradeable('cpp_cats');
        checkGradeable('cpp_count_ts');
        checkGradeable('cpp_custom');
        checkGradeable('cpp_hidden_tests');
        checkGradeable('cpp_provided_code');
        checkGradeable('cpp_simple_lab');
        checkGradeable('cpp_random_input_output');
        checkGradeable('early_submission_incentive');
        checkGradeable('file_check');
        // checkGradeable('haskell_hello_world');
        checkGradeable('image_diff_mirror');
        checkGradeable('input_output_subdirectories');
        checkGradeable('leaderboard');
        checkGradeable('left_right_exam_seating');
        checkGradeable('matlab');
        checkGradeable('minimal_code_editing');
        checkGradeable('multiple_pdf_annotations');
        checkGradeable('notebook_basic');
        checkGradeable('notebook_expected_string');
        checkGradeable('notebook_filesubmission');
        checkGradeable('notebook_itempool');
        checkGradeable('notebook_itempool_random');
        checkGradeable('notebook_time_limit');
        checkGradeable('pdf_exam');
        checkGradeable('pdf_word_count');
        checkGradeable('pre_commands');
        // checkGradeable('prolog_simple_goal');
        checkGradeable('python_count_ts');
        checkGradeable('python_custom_docker_rlimits');
        checkGradeable('python_custom_validation');
        checkGradeable('python_linehighlight');
        checkGradeable('python_multipart_static_analysis');
        checkGradeable('python_simple_homework');
        checkGradeable('python_random_input_output');
        // checkGradeable('qiskit_circuit_draw_diff');
        // checkGradeable('qiskit_tolerance_diff');
        checkGradeable('test_notes_upload');
        checkGradeable('tolerance_check');
        checkGradeable('test_notes_upload_3page');
        checkGradeable('upload_only');
        checkGradeable('vcs_submissions');
        // checkGradeable('verilog_hello_world');
        // checkGradeable('rust_hello_world');
        checkGradeable('docker_choice_of_language');
    });
});
