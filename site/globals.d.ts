import * as luxon from 'luxon';
import * as Twig from 'twig';
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
    }
}
