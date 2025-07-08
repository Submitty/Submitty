import { closeAllComponents, getFirstOpenComponentId, getGradeableId, NO_COMPONENT_ID } from '../../../ts/ta-grading-rubric';

function waitForAllAjaxToComplete(callback: { (): void }) {
    const checkAjax = () => {
        if ($.active > 0) {
            setTimeout(checkAjax, 100);
        }
        else {
            callback();
        }
    };
    checkAjax();
}

export function gotoMainPage() {
    const window_location = $('#main-page')[0].dataset.href!;

    if (getGradeableId() !== '') {
        closeAllComponents(true)
            .then(() => {
                waitForAllAjaxToComplete(() => {
                    window.location.href = window_location;
                });
            })
            .catch(() => {
                if (
                    confirm(
                        'Could not save open component, go to main page anyway?',
                    )
                ) {
                    window.location.href = window_location;
                }
            });
    }
    else {
        window.location.href = window_location;
    }
};

export function gotoPrevStudent() {
    let filter;
    const navigate_assigned_students_only
        = localStorage.getItem(
            'general-setting-navigate-assigned-students-only',
        ) !== 'false';

    const inquiry_status = window.Cookies.get('inquiry_status');
    if (inquiry_status === 'on') {
        filter = 'active-inquiry';
    }
    else {
        if (
            localStorage.getItem('general-setting-arrow-function')
            !== 'active-inquiry'
        ) {
            filter
                = localStorage.getItem('general-setting-arrow-function')
                    || 'default';
        }
        else {
            filter = 'default';
        }
    }
    const selector = '#prev-student';
    let window_location = `${$(selector)[0].dataset.href}&filter=${filter}`;

    switch (filter) {
        case 'ungraded':
            window_location += `&component_id=${getFirstOpenComponentId()}`;
            break;
        case 'itempool':
            window_location += `&component_id=${getFirstOpenComponentId(true)}`;
            break;
        case 'ungraded-itempool':
            // TODO: ???
            // eslint-disable-next-line no-var
            var component_id = getFirstOpenComponentId(true);
            if (component_id === NO_COMPONENT_ID) {
                component_id = getFirstOpenComponentId();
            }
            break;
        case 'inquiry':
            window_location += `&component_id=${getFirstOpenComponentId()}`;
            break;
        case 'active-inquiry':
            window_location += `&component_id=${getFirstOpenComponentId()}`;
            break;
    }

    if (!navigate_assigned_students_only) {
        window_location += '&navigate_assigned_students_only=false';
    }

    if (getGradeableId() !== '') {
        closeAllComponents(true)
            .then(() => {
                waitForAllAjaxToComplete(() => {
                    window.location.href = window_location;
                });
            })
            .catch(() => {
                if (
                    confirm(
                        'Could not save open component, change student anyway?',
                    )
                ) {
                    window.location.href = window_location;
                }
            });
    }
    else {
        window.location.href = window_location;
    }
};

export function gotoNextStudent() {
    let filter;
    const navigate_assigned_students_only
        = localStorage.getItem(
            'general-setting-navigate-assigned-students-only',
        ) !== 'false';

    const inquiry_status = window.Cookies.get('inquiry_status');
    if (inquiry_status === 'on') {
        filter = 'active-inquiry';
    }
    else {
        if (
            localStorage.getItem('general-setting-arrow-function')
            !== 'active-inquiry'
        ) {
            filter
                = localStorage.getItem('general-setting-arrow-function')
                    || 'default';
        }
        else {
            filter = 'default';
        }
    }
    const selector = '#next-student';
    let window_location = `${$(selector)[0].dataset.href}&filter=${filter}`;

    switch (filter) {
        case 'ungraded':
            window_location += `&component_id=${getFirstOpenComponentId()}`;
            break;
        case 'itempool':
            window_location += `&component_id=${getFirstOpenComponentId(true)}`;
            break;
        case 'ungraded-itempool':
            // TODO: ???
            // eslint-disable-next-line no-var
            var component_id = getFirstOpenComponentId(true);
            if (component_id === NO_COMPONENT_ID) {
                component_id = getFirstOpenComponentId();
            }
            break;
        case 'inquiry':
            window_location += `&component_id=${getFirstOpenComponentId()}`;
            break;
        case 'active-inquiry':
            window_location += `&component_id=${getFirstOpenComponentId()}`;
            break;
    }

    if (!navigate_assigned_students_only) {
        window_location += '&navigate_assigned_students_only=false';
    }

    if (getGradeableId() !== '') {
        closeAllComponents(true)
            .then(() => {
                waitForAllAjaxToComplete(() => {
                    window.location.href = window_location;
                });
            })
            .catch(() => {
                if (
                    confirm(
                        'Could not save open component, change student anyway?',
                    )
                ) {
                    window.location.href = window_location;
                }
            });
    }
    else {
        window.location.href = window_location;
    }
}
