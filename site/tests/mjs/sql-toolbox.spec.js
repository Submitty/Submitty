import { runSqlQuery } from '../../public/mjs/sql-toolbox';

global.fetch = jest.fn(() => Promise.resolve({
    json: () => Promise.resolve({
        status: 'success',
        data: [
            {
                col_1: 'foo',
                col_2: 'bar',
            },
            {
                col_1: 'baz',
                col_2: 'qux',
            },
        ],
    }),
}));

beforeEach(() => {
    fetch.mockClear();
});

test('runSqlQuery', async () => {
    document.body.innerHTML = `
        <form>
        <textarea name='sql'>SELECT * FROM users;</textarea>
        </form>
        <div id='query-results-error'></div>
        <div id='query-results-error-message'></div>
        <table id='query-results'></table>
    `;

    await runSqlQuery();

    expect(document.getElementById('query-results').innerHTML).toEqual('<thead><tr><td>#</td><td>col_1</td><td>col_2</td></tr></thead><tbody><tr><td>1</td><td>foo</td><td>bar</td></tr><tr><td>2</td><td>baz</td><td>qux</td></tr></tbody>');
});
