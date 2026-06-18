import { describe, expect, it } from '@jest/globals';
import { postRequest, getRequest } from './utils';

type ApiTokenResponse = { status: string; data: { token: string } };

describe('Test cases revolving around the API', () => {
    it('should authenticate a user', async () => {
        const response: ApiTokenResponse = await postRequest('/api/token', {
            user_id: 'instructor',
            password: 'instructor',
        });
        expect(response).toHaveProperty('status', 'success');
        expect(response).toHaveProperty('data.token');
        expect(typeof response.data.token).toBe('string');
        expect(response.data.token.length).not.toEqual(0);
    });

    it.each([
        ['no user_id or password', { foo: 'bar' }],
        ['no password', { user_id: 'instructor' }],
        ['no user_id', { password: 'instructor' }],
    ])('should require a user_id and password - %s', async (_, postBody) => {
        const response: ApiTokenResponse = await postRequest('/api/token', postBody);
        expect(response).toHaveProperty('status', 'fail');
        expect(response).toHaveProperty('message', 'Cannot leave user id or password blank');
    });

    it('should invalidate older tokens on request', async () => {
        const postBody = {
            user_id: 'instructor',
            password: 'instructor',
        };
        const oldResponse: ApiTokenResponse = await postRequest('/api/token', postBody);
        expect(oldResponse).toHaveProperty('status', 'success');
        await postRequest('/api/token/invalidate', postBody);
        const response: ApiTokenResponse = await postRequest('/api/token', postBody);
        expect(response).toHaveProperty('status', 'success');
        expect(response.data.token).not.toEqual(oldResponse.data.token);
        const coursesResponse = await getRequest('/api/courses', oldResponse.data.token);
        expect(coursesResponse).toHaveProperty('status', 'fail');
        expect(coursesResponse).toHaveProperty('message', 'Unauthenticated access. Please log in.');
    });
});
