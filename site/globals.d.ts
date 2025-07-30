import * as luxon from 'luxon';
import * as Twig from 'twig';
import * as markerjs3 from '@markerjs/markerjs3'
import * as markerjsUI from '@markerjs/markerjs-ui'
export { };

declare global {
    interface Window {
        csrfToken: string;
        $: JQueryStatic;
        Twig: typeof Twig;
        removeMessagePopup: (key: number) => void;
        displayErrorMessage: (message: string) => void;
        displaySuccessMessage: (message: string) => void;
        displayWarningMessage: (message: string) => void;
        displayMessage: (message: string, type: 'error' | 'success' | 'warning') => void;
        luxon: typeof luxon;
        markerjs3: typeof markerjs3;
        markerjsUI: typeof markerjsUI;
    }
}
