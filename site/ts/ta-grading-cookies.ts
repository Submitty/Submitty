export {};
declare global {
    interface Window {
        filter_overriden_grades: () => void;
        filter_bad_grades: () => void;
        filter_null_section: () => void;
        changeSections: () => void;
        changeInquiry: () => void;
        changeSortOrder: () => void;
        sortTableByColumn: (sort_type?: string, direction?: 'ASC' | 'DESC') => void;
        changeAnon: () => void;
        filterWithdrawnUpdate: () => void;
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
    const sort = window.Cookies.get('sort');
    window.Cookies.set('sort', sort === 'random' ? 'id' : 'random', { path: coursePath, expires: 365 });
    location.reload();
};

window.sortTableByColumn = (sort_type: string = 'id', direction: 'ASC' | 'DESC' = 'ASC') => {
    window.Cookies.set('sort', sort_type, { path: coursePath, expires: 365 });
    window.Cookies.set('direction', direction, { path: coursePath, expires: 365 });
};

window.changeAnon = () => {
    window.Cookies.set('anon_mode', $('#toggle-anon-students').is(':checked') ? 'on' : 'off', { path: coursePath, expires: 365 });
    location.reload();
};

window.filterWithdrawnUpdate = () => {
    const filterCheckbox = document.getElementById('toggle-filter-withdrawn') as HTMLInputElement;
    const withdrawnElements = $('[data-student="electronic-grade-withdrawn"]');

    if (filterCheckbox.checked) {
        withdrawnElements.hide();
        window.Cookies.set('filter_withdrawn_student', 'true', { path: coursePath, expires: 365 });
    }
    else {
        withdrawnElements.show();
        window.Cookies.set('filter_withdrawn_student', 'false', { path: coursePath, expires: 365 });
    }
    window.displaySuccessMessage('Withdrawn students filter updated successfully.');
};
