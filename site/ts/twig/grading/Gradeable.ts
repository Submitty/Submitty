//
// This is needed to resolve conflicts between Chrome and other browsers
//   where Chrome can only do synchronous ajax calls on 'onbeforeunload'
//   and other browsers can only do synchronous ajax calls on 'onunload'
//
// Reference:
//    https://stackoverflow.com/questions/4945932/window-onbeforeunload-ajax-request-in-chrome

import { closeAllComponents } from '../../grading/rubric';
import { setAjaxUseAsync } from '../../grading/rubric-ajax';

//
let unloadRequestSent = false;

function unloadSave(): void {
    if (!unloadRequestSent) {
        unloadRequestSent = true;
        setAjaxUseAsync(false);
        closeAllComponents(true)
            .catch(() => {
                // Unable to save so try saving at a different time
                unloadRequestSent = false;
            });
    }
}
// Will work for Chrome
window.onbeforeunload = unloadSave;
// Will work for other browsers
window.onunload = unloadSave;
