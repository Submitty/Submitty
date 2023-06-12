function isValidFileName(file_name: string) {
    if (file_name.indexOf("'") != -1 || file_name.indexOf("\"") != -1) {
        alert("ERROR! You may not use quotes in your filename: " + file_name);
        return false;
    } 
    else if (file_name.indexOf("\\") != -1 || file_name.indexOf("/") != -1) {
        alert("ERROR! You may not use a slash in your filename: " + file_name);
        return false;
    }
    else if (file_name.indexOf("<") != -1 || file_name.indexOf(">") != -1) {
        alert("ERROR! You may not use angle brackets in your filename: " + file_name);
        return false;
    }
    return true;
}


function shouldReplaceFileIfDup(file_name: string, target_path: string, expand_zip: string) {
    var k = fileExists(target_path + "/" + file_name, 1);
    if ( k[0] == 1 )
    {
        var skip_confirmation = false;
        if (expand_zip == 'on') {
            var extension = getFileExtension(file_name);
            if (extension.toLowerCase() == "zip") {
                skip_confirmation = true; // skip the zip if there is conflict when in expand zip choice.
            }
        }
        // don't want to replace, so skip
        if(!skip_confirmation && !confirm("Note: " + file_name + " already exists. Do you want to replace it?")){
            return false;
        }
    }
    return true;
}




