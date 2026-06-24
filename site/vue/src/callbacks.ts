window.handleStatusBannerColorChange = function(color: string) {
    document.body.style.background = color;
};

declare global {
    interface Window {
        handleStatusBannerColorChange(color: string): void;
    }
}
