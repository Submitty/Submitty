import { runSqlQuery, init } from '../../public/mjs/sql-toolbox';
import { test } from '@jest/globals';
import { mockFetch } from './utils';

const originalError = console.error;

beforeEach(() => {
    if (global.fetch && global.fetch.mockClear) {
        global.fetch.mockClear();
    }

    document.body.innerHTML = `
        <form>
        <div>
            <button id="sql-database-schema">Database Schema Documentation</button>
            <div id="sql-database-schema-content" hidden>
                <ul>
                    <li>
                        <a class="sql-database-table"></a>
                        <div class="sql-database-columns"></div>
                    </li>
                </ul>
            </div>
        </div>
        <textarea name='sql'>SELECT * FROM users;</textarea>
        <div id='run-sql-btn'>Submit</div>
        </form>
        <div id='query-results-error' class='red-message'><pre id='query-results-error-message'></pre></div>
        <table id='query-results'></table>
    `;
});

afterEach(() => {
    console.error = originalError;
});

test('success with results', async () => {
    mockFetch({
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
    });

    await runSqlQuery();

    expect(document.getElementById('query-results-error').style.display).toEqual('none');
    expect(document.getElementById('query-results-error-message').innerHTML).toEqual('');
    expect(document.getElementById('query-results').innerHTML).toEqual('<thead><tr><td>#</td><td>col_1</td><td>col_2</td></tr></thead><tbody><tr><td>1</td><td>foo</td><td>bar</td></tr><tr><td>2</td><td>baz</td><td>qux</td></tr></tbody>');
});

test('success with no results', async () => {
    mockFetch({
        status: 'success',
        data: [],
    });

    await runSqlQuery();

    expect(document.getElementById('query-results-error').style.display).toEqual('none');
    expect(document.getElementById('query-results-error-message').innerHTML).toEqual('');
    expect(document.getElementById('query-results').innerHTML).toEqual('<tr><td>No rows returned</td></tr>');
});

test('failure', async () => {
    mockFetch({
        status: 'failure',
        message: 'Invalid query',
    });

    await runSqlQuery();

    expect(document.getElementById('query-results-error').style.display).toEqual('block');
    expect(document.getElementById('query-results-error-message').innerHTML).toEqual('Invalid query');
    expect(document.getElementById('query-results').innerHTML).toEqual('');
});

test('thrown exception is caught and logged to console.error', async () => {
    console.error = jest.fn();
    jest.spyOn(window, 'alert').mockImplementation(() => {});

    mockFetch({
        status: 'success',
    });

    await runSqlQuery();

    const exceptionString = "TypeError: Cannot read property 'length' of undefined";

    expect(console.error.mock.calls.length).toEqual(1);
    expect(console.error.mock.calls[0][0].toString()).toEqual(exceptionString);
    expect(document.getElementById('query-results').innerHTML).toEqual('');
    expect(window.alert).toBeCalledWith(exceptionString);
});

test('init binds submit button', (done) => {
    mockFetch({
        status: 'success',
        data: [],
    });

    init();
    const event = document.createEvent('HTMLEvents');
    event.initEvent('click', false, false);
    document.getElementById('run-sql-btn').dispatchEvent(event);

    // Pause briefly to allow the async runSqlQuery method to fire
    setTimeout(() => {
        expect(document.getElementById('query-results').innerHTML).toEqual('<tr><td>No rows returned</td></tr>');
        done();
    }, 50);
});
