function handleCourseCreationSubmit(event) {
    
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    if (!formData.get('group_name') || formData.get('group_name').trim() === '') {
        const title = formData.get('course_title') || 'default_group';
        const formattedGroup = title.toLowerCase().replace(/[^a-z0-9_]/g, '_');
        formData.set('group_name', formattedGroup || 'default_group');
    }

    if (formData.getAll('csrf_token').length > 1) {
        const primaryToken = formData.get('csrf_token');
        formData.delete('csrf_token');
        formData.set('csrf_token', primaryToken);
    }

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        console.log("AJAX Response Received:", result);

        if (result.status === 'success') {
            
            const courseData = result.data?.data || result.data;

            
            if (typeof pollCourseCreationStatus === 'function') {
                pollCourseCreationStatus(courseData);
            } else {
                console.log("Course creation started successfully:", courseData);
            }
        } else {
            alert(`Course Creation Failed: ${result.message}`);
        }
    })
    .catch(error => {
        console.error("AJAX Course Creation Error:", error);
        alert("An error occurred while creating the course. Check the console for details.");
    });
}
document.addEventListener('DOMContentLoaded', () => {
    const courseForm = document.querySelector('#create-course-form, form[action*="create"]');
    if (courseForm) {
        courseForm.addEventListener('submit', handleCourseCreationSubmit);
    }
});