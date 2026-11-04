import { registerKeyHandler } from '../../ts/ta-grading-keymap';
import { changeStudentArrowTooltips } from '../../ts/ta-grading';

jest.mock('../../ts/ta-grading-keymap', () => ({ registerKeyHandler: jest.fn() }));

beforeAll(async () => {
    await new Promise((resolve) => $(resolve));
});

beforeEach(() => {
    const toggle = document.createElement('button');
    toggle.id = 'toggle-cluster-mode';
    toggle.dataset.clusterMode = 'true';
    document.body.replaceChildren(toggle);
    for (const direction of ['prev', 'next']) {
        const container = document.createElement('span');
        container.id = `${direction}-student-navlink`;
        const button = document.createElement('button');
        button.id = `${direction}-student`;
        button.append(document.createElement('i'));
        container.append(button);
        document.body.append(container);
    }
    window.Cookies = { get: jest.fn(() => 'off') };
});

test.each([['ArrowLeft', 'prev-student'], ['ArrowRight', 'next-student']])(
    '%s uses the same button action as mouse navigation',
    (code, id) => {
        const click = jest.fn();
        document.getElementById(id).addEventListener('click', click);
        const [, handler] = registerKeyHandler.mock.calls.find(([binding]) => binding.code === code);
        handler();
        expect(click).toHaveBeenCalledTimes(1);
    },
);

test('cluster titles follow the effective filter and are cleared when it changes', () => {
    changeStudentArrowTooltips('default');
    expect($('#prev-student').attr('title')).toBe('Previous cluster');
    expect($('#next-student').attr('title')).toBe('Next cluster');
    changeStudentArrowTooltips('ungraded');
    expect($('#prev-student').attr('title')).toBe('Previous student');
    expect($('#next-student-navlink i').attr('title')).toBe('Next ungraded student');
});
