/* global __dirname */
import fs from 'fs';
import path from 'path';
import vm from 'vm';
import $ from 'jquery';

let editor;

beforeEach(() => {
    document.body.innerHTML = `
        <form id="extensions-form">
            <input name="csrf_token" value="token">
            <input name="g_id" value="homework">
            <input name="option" value="1">
            <input id="user_id" name="user_id" value="pending">
            <input id="late-days" name="late_days" value="7">
            <input id="late-calendar" value="2026-12-01">
            <input id="csv-upload" name="csv_upload" type="file">
            <select id="reason-for-exception" name="reason_for_exception"><option>illness</option></select>
        </form>
        <div id="edit-extension-popup" style="display:none">
            <span id="edit-extension-user"></span>
            <input id="edit-extension-days" type="number" min="0" step="1" required>
            <select id="edit-extension-reason"><option>illness</option></select>
        </div>`;
    $.ajax = jest.fn();
    editor = {
        $, document, Option, FormData, window,
        luxon: { DateTime: {} },
        buildCourseUrl: jest.fn(() => '/extensions/update'),
        showPopup: (selector) => $(selector).show(),
        closePopup: (id) => $(`#${id}`).hide(),
        captureTabInModal: jest.fn(),
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/extensions.js'), 'utf8'), editor);
});

function openEditor(reason = 'illness') {
    const button = document.createElement('button');
    button.dataset.userId = 'student';
    button.dataset.days = '2';
    button.dataset.reason = reason;
    editor.editHomeworkExtension(button);
}

test('opening and cancelling preserves the pending entry and custom reason text', () => {
    const reason = '<script>alert(1)</script> "custom"';
    openEditor(reason);
    expect($('#edit-extension-user').text()).toBe('student');
    expect($('#edit-extension-days').val()).toBe('2');
    expect($('#edit-extension-reason').val()).toBe(reason);
    expect(document.querySelector('script')).toBeNull();
    editor.closePopup('edit-extension-popup');
    expect($('#user_id').val()).toBe('pending');
    expect($('#late-days').val()).toBe('7');
    expect($.ajax).not.toHaveBeenCalled();
});

test('saving sends edited values and resets the team choice', () => {
    openEditor('custom reason');
    $('#edit-extension-days').val('3');
    editor.saveHomeworkExtension();
    expect($.ajax).toHaveBeenCalledTimes(1);
    const request = $.ajax.mock.calls[0][0];
    expect(request.url).toBe('/extensions/update');
    expect(request.data.get('user_id')).toBe('student');
    expect(request.data.get('late_days')).toBe('3');
    expect(request.data.get('reason_for_exception')).toBe('custom reason');
    expect(request.data.get('option')).toBe('-1');
    expect(request.data.get('csrf_token')).toBe('token');
    expect($('#late-calendar').val()).toBe('');
    expect($('#edit-extension-popup').css('display')).toBe('none');
    editor.confirmExtension(1);
    expect($.ajax.mock.calls[1][0].data.get('option')).toBe('1');
    expect($.ajax.mock.calls[1][0].data.get('reason_for_exception')).toBe('custom reason');
});

test.each(['', '-1', '1.5'])('invalid days %s do not submit', (days) => {
    openEditor();
    $('#edit-extension-days').val(days);
    editor.saveHomeworkExtension();
    expect($.ajax).not.toHaveBeenCalled();
    expect($('#user_id').val()).toBe('pending');
});
