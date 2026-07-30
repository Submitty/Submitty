const MAIN_SELECTOR = 'main#main';
const FULL_SCREEN_CLASS = 'full-screen-mode';

export function setFullScreenMode(on: boolean): void {
    document.querySelector(MAIN_SELECTOR)?.classList.toggle(FULL_SCREEN_CLASS, on);
}

export function isFullScreenMode(): boolean {
    return document.querySelector(MAIN_SELECTOR)?.classList.contains(FULL_SCREEN_CLASS) ?? false;
}
