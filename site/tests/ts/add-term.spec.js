import { init, updateTermLength } from '../../ts/add-term';

beforeEach(() => {
    document.body.innerHTML = `
    <input type="date" id="start-date">
    <input type="date" id="end-date">
    <div id="term-length-msg"></div>
    <input type="submit" id="add-term-submit">
    `;
});

test('clears the message when either date is empty', () => {
    document.getElementById('start-date').value = '2026-01-01';
    updateTermLength();
    expect(document.getElementById('term-length-msg').textContent).toEqual('');
});

test('warns and disables submit when the end date is before the start date', () => {
    document.getElementById('start-date').value = '2026-02-14';
    document.getElementById('end-date').value = '2026-01-15';
    updateTermLength();

    expect(document.getElementById('term-length-msg').textContent).toEqual('Warning: End date is before start date.');
    expect(document.getElementById('add-term-submit').disabled).toBe(true);
});

test('warns and disables submit when the term is longer than 360 days', () => {
    document.getElementById('start-date').value = '2026-01-01';
    document.getElementById('end-date').value = '2027-01-01';
    updateTermLength();

    expect(document.getElementById('term-length-msg').textContent).toContain('exceeds the 360 day maximum');
    expect(document.getElementById('add-term-submit').disabled).toBe(true);
});

test('shows the term length and enables submit for a valid date range', () => {
    document.getElementById('start-date').value = '2026-01-01';
    document.getElementById('end-date').value = '2026-01-31';
    updateTermLength();

    expect(document.getElementById('term-length-msg').textContent).toEqual('Term length: 30 days');
    expect(document.getElementById('add-term-submit').disabled).toBe(false);
});

test('uses singular "day" for a one-day term', () => {
    document.getElementById('start-date').value = '2026-01-01';
    document.getElementById('end-date').value = '2026-01-02';
    updateTermLength();

    expect(document.getElementById('term-length-msg').textContent).toEqual('Term length: 1 day');
});

test('init() recalculates the message when the dates change', () => {
    init();

    document.getElementById('start-date').value = '2026-01-01';
    document.getElementById('start-date').dispatchEvent(new Event('change'));
    document.getElementById('end-date').value = '2026-01-31';
    document.getElementById('end-date').dispatchEvent(new Event('change'));

    expect(document.getElementById('term-length-msg').textContent).toEqual('Term length: 30 days');
});
