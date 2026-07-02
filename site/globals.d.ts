import * as luxon from 'luxon';
import * as Twig from 'twig';
export { };

declare global {
    interface Window {
        csrfToken: string;
        $: JQueryStatic;
        Twig: typeof Twig;
        submitty: {
            render: (
                target: string | Element,
                type: 'component' | 'page',
                name: string,
                args?: Record<string, unknown>,
                events?: Record<string, string>,
            ) => Promise<void>;
        };
        removeMessagePopup: (key: number) => void;
        displayErrorMessage: (message: string) => void;
        displaySuccessMessage: (message: string) => void;
        displayWarningMessage: (message: string) => void;
        displayMessage: (message: string, type: 'error' | 'success' | 'warning') => void;
        luxon: typeof luxon;
    }
}
