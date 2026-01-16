/* global */

// Withdrawn filter checkbox should remain the same on reload
window.addEventListener('DOMContentLoaded', () => {
    const withdrawnFilterBox = document.getElementById('toggle-filter-withdrawn');
    const withdrawnFilterElements = $('[data-student="electronic-grade-withdrawn"]');
    const withdrawnFilterStatus = window.Cookies.get('include_withdrawn_students') || 'omit';

    if (withdrawnFilterBox) {
        if (withdrawnFilterStatus === 'include') {
            withdrawnFilterBox.checked = false;
            withdrawnFilterElements.show();
        }
        else {
            withdrawnFilterBox.checked = true;
            withdrawnFilterElements.hide();
        }
    }
    const silentEditBox = document.getElementById('silent-edit-id');

    if (silentEditBox) {
        const silentEditStatus = window.Cookies.get('silent_edit_enabled') || 'false';

        silentEditBox.checked = silentEditStatus === 'true';
    }
    const autoscrollBox = document.getElementById('autoscroll_id');

    if (autoscrollBox) {
        const autoscrollStatus = window.Cookies.get('autoscroll') || 'off';

        autoscrollBox.checked = autoscrollStatus === 'on';
    }
    const viewSectionsBox = document.getElementById('toggle-view-sections');

    if (viewSectionsBox) {
        const viewSectionsStatus = window.Cookies.get('view');

        viewSectionsBox.checked = (viewSectionsStatus === 'assigned' || viewSectionsStatus === undefined);
    }
    const inquiryOnlyBox = document.getElementById('toggle-inquiry-only');

    if (inquiryOnlyBox) {
        const inquiryStatus = window.Cookies.get('inquiry_status');

        inquiryOnlyBox.checked = (inquiryStatus === 'on');
    }
    const randomizeOrderBox = document.getElementById('toggle-random-order');

    if (randomizeOrderBox) {
        const sortStatus = window.Cookies.get('sort');

        randomizeOrderBox.checked = (sortStatus === 'random');
    }
    const anonStudentsBox = document.getElementById('toggle-anon-students');

    if (anonStudentsBox) {
        const anonStatus = window.Cookies.get('anon_mode');

        anonStudentsBox.checked = (anonStatus === 'on');
    }
    window.updateElectronicGradingRowNumbersAndColors();
    // Remove table-striped to prevent CSS conflicts with JS-set colors
    $('table').removeClass('table-striped');
});
