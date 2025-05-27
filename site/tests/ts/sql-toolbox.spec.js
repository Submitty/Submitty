import { test } from '@jest/globals';
import { generateCSV, init, runSqlQuery } from '../../ts/sql-toolbox';
import { mockFetch } from './utils';

const originalError = console.error;

beforeEach(() => {
    if (global.fetch && global.fetch.mockClear) {
        global.fetch.mockClear();
    }

    document.body.innerHTML = `
    <div>
      Use this toolbox to run a SELECT query. You cannot run any other type of query, and may only run a single query at a time.
      You can download a CSV of the query results. Must Run Query before you can Download.
      <br /><br />

        <div>
            <button id="sql-database-schema" class="btn btn-primary">Database Schema Documentation</button>
            <div id="sql-database-schema-content" hidden>
            </div>
        </div>
        <br>
      <textarea id="toolbox-textarea" name="sql" style="margin-bottom: 2px;" aria-label="Input SQL">SELECT * FROM users;</textarea>
      <br />
      <button id='run-sql-btn' class="btn btn-primary">Run Query</button>
      <button id='download-sql-btn' class="btn btn-primary" disabled>Download CSV</button>
    </div>

    <div>
      <h2>Query Results</h2>
      <div id='query-results-error' class='red-message'><pre id='query-results-error-message'></pre></div>
      <table id="query-results" class="table table-striped mobile-table">
      </table>
    </div>
    `;
});

afterEach(() => {
    console.error = originalError;
});

test('csv generation', () => {
    document.body.innerHTML = `
        <table id='table-foo'>
            <thread>
                <tr>
                    <td>#</td>
                    <td>col_1</td>
                    <td>col_2</td>
                </tr>
            </thread>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>foo</td>
                    <td>bar</td>
                </tr>
                <tr>
                    <td>1</td>
                    <td>baz</td>
                    <td>qux</td>
                </tr>
            </tbody>
        </table>
    `;
    expect(generateCSV('table-foo')).toEqual('"col_1","col_2",\n'
        + '"foo","bar",\n'
        + '"baz","qux",\n');
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

    const exceptionString = "TypeError: Cannot read properties of undefined (reading 'length')";

    expect(console.error.mock.calls.length).toEqual(1);
    expect(console.error.mock.calls[0][0].toString()).toEqual(exceptionString);
    expect(document.getElementById('query-results').innerHTML).toEqual('');
    expect(window.alert).toHaveBeenCalledWith(exceptionString);
});

test('init binds submit button', async () => {
    mockFetch({
        status: 'success',
        data: [],
    });

    init();
    const event = document.createEvent('HTMLEvents');
    event.initEvent('click', false, false);
    document.getElementById('run-sql-btn').dispatchEvent(event);

    // Pause briefly to allow the async runSqlQuery method to fire
    await new Promise((resolve) => {
        setTimeout(() => {
            expect(document.getElementById('query-results').innerHTML).toEqual('<tr><td>No rows returned</td></tr>');
            resolve();
        }, 50);
    });
});
