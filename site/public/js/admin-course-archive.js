/**
 * Course Archive/Unarchive Manager
 * Handles archiving and unarchiving courses via checkbox toggle
 */

function confirmArchiveToggle(element) {
    const isArchiving = element.checked;
    const action = isArchiving ? 'archive' : 'unarchive';
    
    if (isArchiving) {
        // Confirming archive
        if (!confirm('Are you sure you want to archive this course? This action will hide the course from the course list and prevent new submissions.')) {
            element.checked = false;
            return false;
        }
    } else {
        // Confirming unarchive
        if (!confirm('Are you sure you want to unarchive this course? Make sure your disk/partition has sufficient space for potential file uploads.')) {
            element.checked = true;
            return false;
        }
    }

    // Send the archive/unarchive request
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);

    const endpoint = isArchiving ? 'archive' : 'unarchive';
    const url = buildCourseUrl(['config', endpoint]);

    fetch(url, {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(response => response.text().then(text => ({ status: response.status, body: text })))
    .then(data => {
        try {
            const json = JSON.parse(data.body);
            if (data.status === 200 || (json.data && json.data.message)) {
                alert(json.data?.message || json.message || `Course ${action}d successfully`);
                location.reload();
            } else {
                throw new Error(json.message || `Failed to ${action} course`);
            }
        } catch (e) {
            alert(`Error: ${data.body || e.message}`);
            // Revert the checkbox on error
            element.checked = !element.checked;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(`Failed to ${action} course: ${error.message}`);
        // Revert the checkbox on error
        element.checked = !element.checked;
    });

    return false; // Prevent default checkbox behavior until confirmed
}
