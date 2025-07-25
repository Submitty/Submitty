export {};

declare global {
    interface Window {
        isValidFileName: typeof isValidFileName;
        isValidFilePath: typeof isValidFilePath;
    }
}

function isValidFileName(file_name: string) {
    const disallowedCharRegex = /[\\'"/<>]/;
    const invalidCharMatch = file_name.match(disallowedCharRegex);
    if (invalidCharMatch) {
        const invalidChar = invalidCharMatch[0];
        alert(`ERROR! The filename ${file_name} contains an invalid character: ${invalidChar}. Please use a valid filename.`);
        return false;
    }
    if (file_name.length === 0) {
        alert('ERROR! The file name cannot be empty.');
        return false;
    }
    return true;
}

function isValidFilePath(file_path: string) {
    const disallowedCharRegex = /[\\'"<>]|\/\//;
    const invalidCharMatch = file_path.match(disallowedCharRegex);
    if (invalidCharMatch) {
        const invalidChar = invalidCharMatch[0];
        alert(`ERROR! The file path ${file_path} contains an invalid character: ${invalidChar}. Please use a valid file path.`);
        return false;
    }
    if (file_path.length === 0) {
        alert('ERROR! The file path cannot be empty.');
        return false;
    }
    return true;
}

window.isValidFileName = isValidFileName;
window.isValidFilePath = isValidFilePath;