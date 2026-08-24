export function updateTermLength() {
    const startInput = document.getElementById('start-date');
    const endInput = document.getElementById('end-date');
    const message = document.getElementById('term-length-msg');
    const submitBtn = document.getElementById('add-term-submit');

    if (!startInput || !endInput || !message) {
        return;
    }

    if (!startInput.value || !endInput.value) {
        message.textContent = '';
        return;
    }

    const start = new Date(startInput.value);
    const end = new Date(endInput.value);
    const days = Math.round((end - start) / (1000 * 60 * 60 * 24));

    if (days < 0) {
        message.textContent = 'Warning: End date is before start date.';
        message.style.color = '#d9534f';
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    }
    else if (days > 360) {
        message.textContent = `Warning: This term is ${days} days long, which exceeds the 360 day maximum.`;
        message.style.color = '#d9534f';
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    }
    else {
        message.textContent = `Term length: ${days} day${days === 1 ? '' : 's'}`;
        message.style.color = '';
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
}

export function init() {
    const startInput = document.getElementById('start-date');
    const endInput = document.getElementById('end-date');

    if (startInput) {
        startInput.addEventListener('change', updateTermLength);
    }
    if (endInput) {
        endInput.addEventListener('change', updateTermLength);
    }
}

document.addEventListener('DOMContentLoaded', () => init());
