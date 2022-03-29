
//These functions are defined in normal JS and can be imported into a spec file

/**
* Generate a 3 letter semester code e.g s21, f20 based on today's data
* This functions the same as the submitty python util's get_current_semester
*
* @returns {String}
*/
export function getCurrentSemester(){
    const today = new Date();
    const year = today.getFullYear().toString().slice(2,4);	//get last two digits
    const semester = ((today.getMonth() + 1) < 7) ? 's' : 'f';	//first half of year 'spring' rest is fall

    return semester + year;
}

/**
* Build a courseURL based on an array of 'parts', e.g [foo, bar] -> courses/s21/foo/bar
*
* @param {String[]} [parts=[]] array of parts to string together
* @param {Boolean} [include_base=false] whether to include the url base (e.g. http://localhost:1501/) or not
* @returns {String}
*/
export function buildUrl(parts = [], include_base = false){
    let url = '';
    if (include_base){
        url = `${Cypress.config('baseUrl')}/`;
    }

    return `${url}courses/${getCurrentSemester()}/${parts.join('/')}`;
}
