export {};
declare global {
    interface Window {
        filter_overriden_grades: () => void;
        filter_bad_grades: () => void;
        filter_null_section: () => void;
        changeSections: () => void;
        changeInquiry: () => void;
        changeSortOrder: () => void;
    }
}

const coursePath = document.body.dataset.coursePath ?? '';

window.filter_overriden_grades = () => {
    const override_status = window.Cookies.get('include_grade_override') ?? 'omit';
    window.Cookies.set('include_grade_override', override_status === 'omit' ? 'include' : 'omit', { path: coursePath, expires: 365 });
};

window.filter_bad_grades = () => {
    const bad_submissions_status = window.Cookies.get('include_bad_submissions') ?? 'omit';
    window.Cookies.set('include_bad_submissions', bad_submissions_status === 'omit' ? 'include' : 'omit', { path: coursePath, expires: 365 });
};

window.filter_null_section = () => {
    const null_section_status = window.Cookies.get('include_null_section') ?? 'omit';
    window.Cookies.set('include_null_section', null_section_status === 'omit' ? 'include' : 'omit', { path: coursePath, expires: 365 });
};

window.changeSections = () => {
    const view_all = window.Cookies.get('view') ?? 'assigned';
    if (view_all === 'all') {
        window.Cookies.set('view', 'assigned', { path: coursePath, expires: 365 });
        localStorage.setItem('general-setting-navigate-assigned-students-only', 'true');
    }
    else {
        window.Cookies.set('view', 'all', { path: coursePath, expires: 365 });
        localStorage.setItem('general-setting-navigate-assigned-students-only', 'false');
    }
    location.reload();
};

window.changeInquiry = () => {
    const inquiry_status = window.Cookies.get('inquiry_status') ?? 'off';
    window.Cookies.set('inquiry_status', inquiry_status === 'off' ? 'on' : 'off', { path: coursePath, expires: 365 });
    location.reload();
};

window.changeSortOrder = () => {
    window.Cookies.set('sort', window.Cookies.get('sort') === 'random' ? 'id' : 'random', { path: coursePath, expires: 365 });
    location.reload();
};
