import { describe, expect, it } from '@jest/globals';
import {postRequest, getRequest} from './utils';

describe('Test cases revolving around the API', () => {
    it('should authenticate a user', async () => {
        const response = await postRequest('/api/token', {
            'user_id': 'instructor',
            'password': 'instructor',
        });
        expect(response).toHaveProperty(['status', 'data']);
        expect(response.status).toEqual('success');
        expect(response.data).toHaveProperty('token');
    });

    it.each([
        ['no user_id or password', {'foo': 'bar'}],
        ['no password', { 'user_id': 'instructor'}],
        ['no user_id', {'password': 'instructor'}],
    ])(`should require a user_id and password - %s`, async (_, postBody) => {
        const response = await postRequest('/api/token', postBody);
        expect(response).toHaveProperty(['status', 'message']);
        expect(response.status).toEqual('fail');
        expect(response.message).toEqual('Cannot leave user id or password blank');
    });

    it('should invalidate older tokens on request', async () => {
        const postBody = {
            user_id: 'instructor',
            password: 'instructor',
        };
        const oldResponse = await postRequest('/api/token', postBody);
        await postRequest('/api/token/invalidate', postBody);
        const response = await postRequest('/api/token', postBody);
        expect(response.token).not.toEqual(oldResponse.token);
        const data = await getRequest('/api/courses', oldResponse.token);
        expect(data).toHaveProperty(['status', 'message']);
        expect(data.status).toEqual('fail');
        expect(data.message).toEqual('Unauthenticated access. Please log in.');
    });
});
