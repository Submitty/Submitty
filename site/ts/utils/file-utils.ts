// also update in 'FileUtils.php'
const SUBMISSION_META_FILES = ['.submit.notebook', '.submit.timestamp', '.submit.VCS_CHECKOUT',
    '.user_assignment_access.json', '.bulk_upload_data.json'];

// also update in 'FileUtils.php'
export function isSubmissionMetaFile(filename: string) {
    if (SUBMISSION_META_FILES.includes(filename)) {
        return true;
    }
    return filename.startsWith('.upload_page_') || filename.startsWith('.upload_version_');
}
