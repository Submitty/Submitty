import { describe, expect, it } from '@jest/globals';
import { postRequest, getRequest } from './utils';
import { getCurrentSemester } from './utils';

describe('Tests cases for the Student API', () => {
	it('Should get correct responses', async () => {
		const instructor_key = await postRequest('/api/token', {
			user_id: 'instructor',
			password: 'instructor',
		});
		const instructors_values_request = await getRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/values?user_id=student`,
			instructor_key.data.token,
		);

		expect(instructors_values_request).toHaveProperty('status', 'success');
		// Can't test exact values due to randomness of CI speed
		const data = JSON.stringify(instructors_values_request.data);
		expect(data).toContain('is_queued');
		expect(data).toContain('queue_position'),
        expect(data).toContain('is_grading'),
        expect(data).toContain('has_submission'),
        expect(data).toContain('autograding_complete'),
        expect(data).toContain('has_active_version'),
        expect(data).toContain('highest_version'),
        expect(data).toContain('total_points'),
        expect(data).toContain('total_percent');

		const instructors_bad_values_request = await getRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/values?user_id=not_a_student`,
			instructor_key.data.token,
		);
		expect(instructors_bad_values_request).toHaveProperty('status', 'fail');
		expect(instructors_bad_values_request).toHaveProperty(
			'message',
			'Graded gradeable for user with id not_a_student does not exist',
		);

		const student_key = await postRequest('/api/token', {
			user_id: 'student',
			password: 'student',
		});
		// Success
		getRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/values?user_id=student`,
			student_key.data.token,
		).then((response) => {
			expect(response).toHaveProperty('status', 'success');
			// Can't test exact values due to randomness of CI speed
			const data_string = JSON.stringify(response.data);
			expect(data_string).toContain('is_queued');
			expect(data_string).toContain('queue_position'),
            expect(data_string).toContain('is_grading'),
            expect(data_string).toContain('has_submission'),
            expect(data_string).toContain('autograding_complete'),
            expect(data_string).toContain('has_active_version'),
            expect(data_string).toContain('highest_version'),
            expect(data_string).toContain('total_points'),
            expect(data_string).toContain('total_percent');
			// CI doesn't have grades
			// Requires VCS Subdirectory gradeable to be graded
			// if (Cypress.env('run_area') !== 'CI') {
			//     const python_test = {
			//         'name': 'Python test',
			//         'details': 'python3 *.py',
			//         'is_extra_credit': false,
			//         'points_available': 5,
			//         'points_received': 5,
			//         'testcase_message': '',
			//     };
			//     const submitted_pdf = {
			//         'name': 'Submitted a .pdf file',
			//         'details': '',
			//         'is_extra_credit': false,
			//         'points_available': 1,
			//         'points_received': 1,
			//         'testcase_message': '',
			//     };
			//     const words = {
			//         'name': 'Required 500-1000 Words',
			//         'details': '',
			//         'is_extra_credit': false,
			//         'points_available': 1,
			//         'points_received': 0,
			//         'testcase_message': '',
			//     };
			//     expect(data.test_cases[0]).toContain(python_test);
			//     expect(data.test_cases[1]).toContain(submitted_pdf);
			//     expect(data.test_cases[2]).toContain(words);
			// }
		});

		// Success, successfully sent to be graded
		postRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/grade`,
			{
				user_id: 'student',
				vcs_checkout: 'true',
				git_repo_id: 'none',
			},
			student_key.data.token,
		).then((response) => {
			expect(response).toHaveProperty('status', 'success');
			expect(response.data).toContain('Successfully uploaded version');
			expect(response.data).toContain('for Subdirectory VCS Homework');
		});
		// Fail
		getRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/values`,
			student_key.data.token,
		).then((response) => {
			expect(response).toHaveProperty('status', 'success');
			expect(response).toHaveProperty('message', 'Method not allowed.');
		});

		// Fail, invalid API key
		getRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/values`,
			student_key.data.token,
		).then((response) => {
			expect(response).toHaveProperty('status', 'success');
			expect(response).toHaveProperty(
				'message',
				'Unauthenticated access. Please log in.',
			);
		});
		// Fail, API key not for given user_id
		getRequest(
			`/api/${getCurrentSemester(false)}/sample/gradeable/subdirectory_vcs_homework/values?user_id=not_a_student`,
			student_key.data.token,
		).then((response) => {
			expect(response).toHaveProperty('status', 'success');
			expect(response).toHaveProperty(
				'message',
				'API key and specified user_id are not for the same user.',
			);
		});
		// Fail, endpoint not found.
		getRequest('/api/not/found/url', student_key.data.token).then(
			(response) => {
				expect(response).toHaveProperty('status', 'success');
				expect(response).toHaveProperty(
					'message',
					'Endpoint not found.',
				);
			},
		);

		// Gradeable doesn't exist
		getRequest(
			`api/${getCurrentSemester(false)}/sample/gradeable/not_found_gradeable/values?user_id=student`,
			student_key.data.token,
		).then((response) => {
			expect(response).toHaveProperty('status', 'success');
			expect(response).toHaveProperty(
				'message',
				'Gradeable does not exist',
			);
		});
	});
});
