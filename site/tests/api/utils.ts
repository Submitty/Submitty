import fetch, { HeadersInit } from 'node-fetch';

const BASE_URL = process.env.BASE_URL || 'http://localhost:1511';

function getHeaders(auth: string | null = null) {
    const headers: HeadersInit = { 'Content-Type': 'application/json' };
    if (auth !== null) {
        headers['Authorization'] = auth;
    }
    return headers;
}

/**
* Build a dictionary that can be sent as a POST request, with either fetch or cy.request
*/
export async function postRequest<T, R>(url: string, body: Record<string, R>, auth: string | null = null): Promise<T> {
    const req = await fetch(`${BASE_URL}${url}`, {
        method: 'POST',
        headers: getHeaders(auth),
        body: JSON.stringify(body),
    });

    if (!req.ok) {
        throw new Error(`Request failed: ${req.status} ${req.statusText}`);
    }

    return req.json() as Promise<T>;
}

/**
* Build a dictionary that can be sent as a GET request, with either fetch or cy.request
*
* @param {String}  url endpoint, if using cy.request you do not need to include the base url
* @param {String} [auth=null] optional authentication token to be put in the Authorization header
* @returns {Object}
*/
export async function getRequest<T>(url: string, auth: string | null = null): Promise<T> {
    const req = await fetch(`${BASE_URL}${url}`, {
        method: 'GET',
        headers: getHeaders(auth),
    });

    if (!req.ok) {
        throw new Error(`Request failed: ${req.status} ${req.statusText}`);
    }

    return req.json() as Promise<T>;
}
