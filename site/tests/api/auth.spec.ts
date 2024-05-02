import { describe, expect, it } from '@jest/globals';
import { postRequest, getRequest, getCurrentSemester } from './utils';

const tokenPostBody = {
    user_id: 'instructor',
    password: 'instructor',
};


describe('Test cases revolving around the API', () => {
    it('should authenticate a user', async () => {
        const response = await postRequest('/api/token', tokenPostBody);
        expect(response).toHaveProperty('status', 'success');
        expect(response).toHaveProperty('data.token');
        expect(typeof response.data.token).toBe('string');
        expect(response.data.token.length).not.toEqual(0);
    });

    it.each([
        ['no user_id or password', {'foo': 'bar'}],
        ['no password', { 'user_id': 'instructor'}],
        ['no user_id', {'password': 'instructor'}],
    ])(`should require a user_id and password - %s`, async (_, postBody) => {
        const response = await postRequest('/api/token', postBody);
        expect(response).toHaveProperty('status', 'fail');
        expect(response).toHaveProperty('message', 'Cannot leave user id or password blank')
    });

    it('should invalidate older tokens on request', async () => {
        const oldResponse = await postRequest('/api/token', tokenPostBody);
        expect(oldResponse).toHaveProperty('status', 'success');
        await postRequest('/api/token/invalidate', tokenPostBody);
        const response = await postRequest('/api/token', tokenPostBody);
        expect(response).toHaveProperty('status', 'success');
        expect(response.data.token).not.toEqual(oldResponse.data.token);
        const coursesResponse = await getRequest('/api/courses', oldResponse.data.token);
        expect(coursesResponse).toHaveProperty('status', 'fail');
        expect(coursesResponse).toHaveProperty('message', 'Unauthenticated access. Please log in.')
    });

    it('Course API should return valid response', async () => {
        const createCourseBody = {
            course_semester: getCurrentSemester(false),
            course_title: 'test4',
            head_instructor: 'instructor',
            group_name: 'blank_tas_www'
        };

        const expectedUnarchivedCourses = [
            {"display_name": "", "display_semester": getCurrentSemester(true), "registration_section": null, "semester": getCurrentSemester(false), "title": "blank", "user_group": 1},
            {"display_name": "", "display_semester": getCurrentSemester(true), "registration_section": null, "semester": getCurrentSemester(false), "title": "development", "user_group": 1},
            {"display_name": "", "display_semester": getCurrentSemester(true), "registration_section": null, "semester": getCurrentSemester(false), "title": "sample", "user_group": 1},
            {"display_name": "", "display_semester": getCurrentSemester(true), "registration_section": null, "semester": getCurrentSemester(false), "title": "tutorial", "user_group": 1}
        ]

        const token_response = await postRequest('/api/token', tokenPostBody);

        const coursesResponse = await getRequest('/api/courses', token_response.data.token);
        expect(coursesResponse).toHaveProperty('status', 'success');
        expect(coursesResponse.data).toHaveProperty('unarchived_courses');
        expect(coursesResponse.data).toHaveProperty('archived_courses');
        // Expect to be JSON object
        expect(typeof coursesResponse.data.archived_courses).toBe('object');
        expect(typeof coursesResponse.data.unarchived_courses).toBe('object');

        expect(coursesResponse.data.unarchived_courses).toEqual(expectedUnarchivedCourses);
        expect(coursesResponse.data.archived_courses).toEqual([]);

        const createCourseResponse = await postRequest('/api/courses', createCourseBody, token_response.data.token);

        expect(createCourseResponse).toHaveProperty('status', 'success');
        expect(createCourseResponse.data).toBe(null);
    });
});
