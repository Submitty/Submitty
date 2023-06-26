export function isValidFileName(file_name: string) {
    if (file_name.indexOf('\'') !== -1 || file_name.indexOf('"') !== -1) {
        alert(`ERROR! You may not use quotes in your filename: ${file_name}`);
        return false;
    }
    else if (file_name.indexOf('\\') !== -1 || file_name.indexOf('/') !== -1) {
        alert(`ERROR! You may not use a slash in your filename: ${file_name}`);
        return false;
    }
    else if (file_name.indexOf('<') !== -1 || file_name.indexOf('>') !== -1) {
        alert(`ERROR! You may not use angle brackets in your filename: ${file_name}`);
        return false;
    }
    return true;
}