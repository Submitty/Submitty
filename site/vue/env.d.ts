/// <reference types="vite/client" />

declare module '*.vue' {
    import { DefineComponent } from 'vue';
    const Component: DefineComponent;
    export default Component;
}

import * as luxon from 'luxon';
declare global {
    interface Window {
        luxon: typeof luxon;
    }
}
