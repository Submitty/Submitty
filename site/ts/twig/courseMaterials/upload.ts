export {};

declare global {
    interface Window {
        isValidFileName: typeof isValidFileName;
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
    return true;
}

window.isValidFileName = isValidFileName;
