export function mockFetch(payload) {
    global.fetch = jest.fn(() => Promise.resolve({
        json: () => Promise.resolve(payload),
    }));
}
