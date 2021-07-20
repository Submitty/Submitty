import {init} from '../../public/mjs/team';

beforeEach(() => {
    document.body.innerHTML = `
    <button id="team-name-change-icon"></button>
    <div class="popup-form" id="edit-team-name-form" style="display: none;"></div>
    <div class="popup-form" id="first-pop-up" style="display: block;"></div>
    `;
});

test('validate form shows', async () => {
    init();
    document.getElementById('team-name-change-icon').click();
    expect(document.getElementById('edit-team-name-form').style.display).toEqual('block');
    expect(document.getElementById('first-pop-up').style.display).toEqual('none');
});
