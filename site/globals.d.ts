import * as luxon from 'luxon';
import * as Twig from 'twig';
export { };

declare global {
    interface Window {
        csrfToken: string;
        $: JQueryStatic;
        Twig: typeof Twig;
        luxon: typeof luxon;

    }
}
