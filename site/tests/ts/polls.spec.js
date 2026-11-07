import fs from 'fs';
import path from 'path';

// Exercise the classic browser script with controlled HTTP/socket transport and time.
const source = fs.readFileSync(path.resolve('public/js/polls.js'), 'utf8');
const succeed = (request, html) => request.resolve({ status: 'success', data: { html } });
const deadline = '2030-01-01T12:01:00+05:30';
const state = (action = 'Closed', endTime = '', timer = '—', extra = '') => `
    <div id="student-polls"><table id="today-table"><tbody>
        <tr class="student-poll-row" data-poll-id="1">
            <td>Poll</td><td id="answer"><div id="vue-answer" class="student-poll-answer" data-content="**My answer**">My rendered answer</div></td>
            <td class="poll-index-timer" ${endTime ? `data-end-time="${endTime}"` : ''}>${timer}</td>
            <td class="student-poll-action">${action === 'Closed' ? 'Closed' : `<a href="/polls/1"><button>${action}</button></a>`}</td>
        </tr>${extra}
    </tbody></table><table id="older-table"><tbody></tbody></table></div>`;
const newRow = `<tr class="student-poll-row" data-poll-id="2"><td>New Poll</td>
    <td><div id="new-answer" class="student-poll-answer" data-content="**No Response**"></div></td>
    <td class="poll-index-timer">—</td><td class="student-poll-action">Closed</td></tr>`;
const emptyState = '<div id="student-polls"><table id="today-table"><tbody></tbody></table><table id="older-table"><tbody></tbody></table></div>';

describe('student poll index socket updates', () => {
    let requests;
    let socket;
    let render;

    beforeEach(() => {
        jest.useFakeTimers().setSystemTime(new Date('2030-01-01T06:30:00Z'));
        document.body.replaceChildren(...new DOMParser().parseFromString(state(), 'text/html').body.childNodes);
        requests = [];
        jest.spyOn($, 'ajax').mockImplementation(() => {
            const request = $.Deferred();
            requests.push(request);
            return request.promise();
        });
        render = jest.fn((answer) => {
            answer.unmount = jest.fn();
            return Promise.resolve();
        });
        window.submitty = { render };
        const Socket = jest.fn().mockImplementation(function () {
            this.open = jest.fn();
        });
        const initialize = new Function('WebSocketClient', `${source}\nreturn initializeStudentIndexSocketClient;`)(Socket);
        jest.runOnlyPendingTimers();
        initialize('/courses/s30/sample/polls');
        socket = window.socketClient;
    });

    afterEach(() => {
        jest.clearAllTimers();
        jest.useRealTimers();
        jest.restoreAllMocks();
        delete window.socketClient;
        delete window.submitty;
    });

    test('subscribes only to the safe channel and fetches on connect and reconnect', () => {
        expect(socket.open).toHaveBeenCalledWith('polls_student_index');
        socket.onopen();
        expect($.ajax).toHaveBeenCalledWith({ url: '/courses/s30/sample/polls/student_index', dataType: 'json', headers: { Accept: 'application/json' }, cache: false });
        succeed(requests[0], state());
        socket.onmessage({ type: 'update_num_responses', message: 99 });
        expect(requests).toHaveLength(1);
        socket.onopen();
        succeed(requests[1], state('Answer', deadline));
        expect(document.querySelector('.student-poll-action').textContent).toBe('Answer');
        expect(document.querySelector('.poll-index-timer').textContent).toBe('00:01:00');
    });

    test('opens, ends, reopens and closes existing rows while preserving the answer mount', () => {
        const page = document.body.firstElementChild;
        const answer = document.getElementById('vue-answer');
        answer.reRender = jest.fn();
        const update = (html) => {
            socket.onmessage({ type: 'poll_state_changed' });
            succeed(requests[requests.length - 1], html);
        };
        update(state('Answer', deadline));
        jest.advanceTimersByTime(1000);
        expect(document.querySelector('.poll-index-timer').textContent).toBe('00:00:59');
        update(state('View Poll', '', 'Poll Ended'));
        expect(document.querySelector('.student-poll-action').textContent).toBe('View Poll');
        expect(document.querySelector('.poll-index-timer').hasAttribute('data-end-time')).toBe(false);
        expect(jest.getTimerCount()).toBe(0);
        update(state('Edit Answer', '2030-01-01T06:32:01Z'));
        expect(document.querySelector('.poll-index-timer').textContent).toBe('00:02:00');
        update(state());
        expect(document.querySelector('.student-poll-action').textContent).toBe('Closed');
        expect(document.querySelector('.poll-index-timer').textContent).toBe('—');
        expect(jest.getTimerCount()).toBe(0);
        expect(document.body.firstElementChild).toBe(page);
        expect(document.getElementById('vue-answer')).toBe(answer);
        expect(answer.reRender).not.toHaveBeenCalled();
        expect(answer.unmount).not.toHaveBeenCalled();
        expect(render).toHaveBeenCalledTimes(1);
    });

    test('coalesces duplicate notifications and discards a response superseded while in flight', () => {
        socket.onopen();
        socket.onmessage({ type: 'poll_state_changed' });
        socket.onmessage({ type: 'poll_state_changed' });
        expect(requests).toHaveLength(1);
        succeed(requests[0], state('Answer', deadline));
        expect(document.querySelector('.student-poll-action').textContent).toBe('Closed');
        expect(requests).toHaveLength(2);
        succeed(requests[1], state('View Poll', '', 'Poll Ended'));
        expect(document.querySelector('.student-poll-action').textContent).toBe('View Poll');
        expect(jest.getTimerCount()).toBe(0);
    });

    test('updates older actions and multiple timers without creating extra intervals', () => {
        const extra = `<tr class="student-poll-row" data-poll-id="2">
            <td>Second</td><td><div class="student-poll-answer" data-content="No Response"></div></td>
            <td class="poll-index-timer" data-end-time="2030-01-01T06:32:00Z">—</td>
            <td class="student-poll-action">Answer</td></tr>
            <tr class="student-poll-row" data-poll-id="3"><td>Older</td><td><div class="student-poll-answer" data-content="No Response"></div></td><td class="student-poll-action">View Poll</td></tr>`;
        document.body.replaceChildren(...new DOMParser().parseFromString(state('Closed', '', '—', extra), 'text/html').body.childNodes);
        socket.onopen();
        succeed(requests[0], state('Answer', deadline, '—', extra));
        expect(jest.getTimerCount()).toBe(1);
        jest.advanceTimersByTime(60000);
        expect(Array.from(document.querySelectorAll('.poll-index-timer'), (timer) => timer.textContent))
            .toEqual(['Poll Ended', '00:01:00']);
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[1], state('View Poll', '', 'Poll Ended', extra.replace('View Poll', 'Closed')));
        expect(document.querySelector('[data-poll-id="3"] .student-poll-action').textContent).toBe('Closed');
        expect(jest.getTimerCount()).toBe(1);
    });

    test('recovers on the next notification after failure and ignores non-student pages', () => {
        jest.spyOn(console, 'error').mockImplementation(() => {});
        socket.onopen();
        requests[0].reject({ status: 503 });
        expect(requests).toHaveLength(1);
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[1], '<div>Login or instructor index</div>');
        expect(document.querySelector('.student-poll-action').textContent).toBe('Closed');
        socket.onopen();
        succeed(requests[2], state('Answer', deadline));
        expect(document.querySelector('.student-poll-action').textContent).toBe('Answer');
    });

    test('ignores JSON access errors or missing markup without clearing the list', () => {
        socket.onopen();
        requests[0].resolve({ status: 'error', message: 'Error handling request, redirecting' });
        expect(document.querySelector('.student-poll-action').textContent).toBe('Closed');
        socket.onmessage({ type: 'poll_state_changed' });
        requests[1].resolve({ status: 'success', data: { html: null } });
        expect(document.querySelectorAll('.student-poll-row')).toHaveLength(1);
        socket.onopen();
        succeed(requests[2], state('Answer', deadline));
        expect(document.querySelector('.poll-index-timer').textContent).toBe('00:01:00');
    });

    test('inserts released rows, mounts Markdown, opens them, and removes their resources', async () => {
        const withScript = newRow.replace('</tr>', '<script>throw new Error("Fetched scripts must not run");</script></tr>');
        socket.onopen();
        succeed(requests[0], state('Closed', '', '—', withScript));
        const answer = document.getElementById('new-answer');
        expect(render).toHaveBeenLastCalledWith(answer, 'component', 'Markdown', { content: '**No Response**' });
        expect(document.querySelectorAll('.student-poll-row')).toHaveLength(2);
        expect(document.querySelector('[data-poll-id="2"] script')).toBeNull();
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[1], state('Closed', '', '—', newRow.replace('class="poll-index-timer"', `class="poll-index-timer" data-end-time="${deadline}"`).replace('>Closed</td>', '>Answer</td>')));
        expect(document.querySelector('[data-poll-id="2"] .poll-index-timer').textContent).toBe('00:01:00');
        expect(render).toHaveBeenCalledTimes(2);
        await Promise.resolve();
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[2], state());
        expect(document.querySelector('[data-poll-id="2"]')).toBeNull();
        expect(answer.unmount).toHaveBeenCalledTimes(1);
        expect(jest.getTimerCount()).toBe(0);
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[3], state('Closed', '', '—', newRow));
        expect(document.querySelector('[data-poll-id="2"]')).not.toBeNull();
        expect(document.getElementById('new-answer')).not.toBe(answer);
        expect(render).toHaveBeenCalledTimes(3);
    });

    test('unmounts a new answer even if its lazy import completes after deletion', async () => {
        let finishMount;
        render.mockImplementationOnce((answer) => new Promise((resolve) => {
            finishMount = () => {
                answer.unmount = jest.fn();
                resolve();
            };
        }));
        socket.onopen();
        succeed(requests[0], state('Closed', '', '—', newRow));
        const answer = document.getElementById('new-answer');
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[1], state());
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[2], state('Closed', '', '—', newRow.replace('id="new-answer"', 'id="replacement-answer"')));
        const replacement = document.getElementById('replacement-answer');
        finishMount();
        await Promise.resolve();
        expect(answer.isConnected).toBe(false);
        expect(answer.unmount).toHaveBeenCalledTimes(1);
        expect(replacement.isConnected).toBe(true);
        expect(replacement.unmount).not.toHaveBeenCalled();
    });

    test('matches server ordering and moves existing mounts between Today and Older', () => {
        const answer = document.getElementById('vue-answer');
        const row = document.querySelector('.student-poll-row');
        const response = new DOMParser().parseFromString(state('Answer', deadline, '—', newRow), 'text/html');
        const oldRow = response.querySelector('[data-poll-id="1"]');
        oldRow.querySelector('.poll-index-timer').remove();
        const earlier = response.querySelector('[data-poll-id="2"]');
        earlier.querySelector('.poll-index-timer').remove();
        response.querySelector('#older-table tbody').append(earlier, oldRow);
        socket.onopen();
        succeed(requests[0], response.body.outerHTML);
        expect(document.querySelector('#today-table tbody').children).toHaveLength(0);
        expect(Array.from(document.querySelector('#older-table tbody').children, (el) => el.dataset.pollId)).toEqual(['2', '1']);
        expect(document.getElementById('vue-answer')).toBe(answer);
        expect(document.querySelector('[data-poll-id="1"]')).toBe(row);
        expect(row.cells).toHaveLength(3);
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[1], state('Answer', deadline));
        expect(row.parentElement).toBe(document.querySelector('#today-table tbody'));
        expect(row.cells).toHaveLength(4);
        expect(row.querySelector('.poll-index-timer').textContent).toBe('00:01:00');
        expect(document.getElementById('vue-answer')).toBe(answer);
        expect(document.querySelector('[data-poll-id="2"]')).toBeNull();
    });

    test('recovers missed insertion/removal on reconnect and never resurrects stale rows', () => {
        socket.onopen();
        // Only rows in the server-authorized response can be inserted.
        succeed(requests[0], state('Closed', '', '—', newRow));
        expect(document.querySelector('[data-poll-id="2"]')).not.toBeNull();
        socket.onopen();
        socket.onmessage({ type: 'poll_state_changed' });
        socket.onmessage({ type: 'poll_state_changed' });
        succeed(requests[1], state('Answer', deadline, '—', newRow));
        expect(requests).toHaveLength(3);
        succeed(requests[2], emptyState);
        expect(document.querySelectorAll('.student-poll-row')).toHaveLength(0);
        expect(jest.getTimerCount()).toBe(0);
        socket.onopen();
        succeed(requests[3], state('Answer', deadline));
        expect(document.querySelectorAll('.student-poll-row')).toHaveLength(1);
        expect(document.querySelector('.poll-index-timer').textContent).toBe('00:01:00');
    });
});
