export {};
declare global {
    interface Window {
        filter_overriden_grades: () => void;
        filter_bad_grades: () => void;
        filter_null_section: () => void;
        filter_withdrawn_students: () => void;
        sortTableByColumn: (sort_type?: string, direction?: 'ASC' | 'DESC') => void;
        updateSimpleGradingRowNumbersAndColors: () => void;
        updateElectronicGradingRowNumbersAndColors: () => void;
    }
}

const coursePath = document.body.dataset.coursePath ?? '';
const cookieArguments = { path: coursePath, expires: 365 };

window.filter_overriden_grades = () => {
    const override_status = window.Cookies.get('include_grade_override') ?? 'omit';
    window.Cookies.set('include_grade_override', override_status === 'omit' ? 'include' : 'omit', cookieArguments);
};

window.filter_bad_grades = () => {
    const bad_submissions_status = window.Cookies.get('include_bad_submissions') ?? 'omit';
    window.Cookies.set('include_bad_submissions', bad_submissions_status === 'omit' ? 'include' : 'omit', cookieArguments);
};

window.filter_null_section = () => {
    const null_section_status = window.Cookies.get('include_null_section') ?? 'omit';
    window.Cookies.set('include_null_section', null_section_status === 'omit' ? 'include' : 'omit', cookieArguments);
};

window.filter_withdrawn_students = () => {
    const withdrawn_students_visibility = window.Cookies.get('include_withdrawn_students') || 'omit';

    // includes electronic-grade-withdrawn and simple-grade-withdrawn
    // for the details and display pages respectively
    const withdrawn_student_rows = $('[data-student$="grade-withdrawn"]');

    if (withdrawn_students_visibility === 'include') {
        withdrawn_student_rows.hide();
        withdrawn_student_rows.addClass('hidden-withdrawn-student-row');
        window.Cookies.set('include_withdrawn_students', 'omit', cookieArguments);
    }
    else {
        withdrawn_student_rows.show();
        withdrawn_student_rows.removeClass('hidden-withdrawn-student-row');
        window.Cookies.set('include_withdrawn_students', 'include', cookieArguments);
    }
};

window.sortTableByColumn = (sort_type: string = 'id', direction: 'ASC' | 'DESC' = 'ASC') => {
    window.Cookies.set('sort', sort_type, cookieArguments);
    window.Cookies.set('direction', direction, cookieArguments);
    location.reload();
};

window.updateSimpleGradingRowNumbersAndColors = updateSimpleGradingRowNumbersAndColors as unknown as () => void;
window.updateElectronicGradingRowNumbersAndColors = updateElectronicGradingRowNumbersAndColors as unknown as () => void;
