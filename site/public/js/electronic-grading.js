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
    window.updateElectronicGradingRowNumbersAndColors();
    // Remove table-striped to prevent CSS conflicts with JS-set colors
    $('table').removeClass('table-striped');
});

function registerHandlers() {
    if (!window.submitty?.registerHandler) {
        setTimeout(registerHandlers, 10);
        return;
    }
    window.submitty.registerHandler('handleStatusBannerColorChange', (color) => {
        const gradingContainer = document.getElementById('electronic-gradeable-container');
        if (gradingContainer) {
            gradingContainer.style.background = color;
        }
        document.querySelectorAll('.rubric_panel').forEach((el) => {
            el.style.background = color;
        });
    });
}

registerHandlers();
