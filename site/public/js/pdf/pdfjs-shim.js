import * as pdfjsLib from "../../vendor/pdfjs/pdf.mjs";
import * as pdfjsViewer from "../../vendor/pdfjs/pdf_viewer.mjs";

// Class with static method and safe constructor
class DefaultTextLayerFactoryCompat {
    constructor(options = {}) {
        // Provide defaults for required options
        const safeOptions = {
            highlighter: null,
            accessibilityManager: null,
            ...options,
        };

        console.log(safeOptions);
    }

    // Instance method for legacy code
    createTextLayerBuilder(container, pageIndex, viewport, enhanceTextSelection, ...rest) {
        // Delegate to the static method
        return DefaultTextLayerFactoryCompat.createTextLayerBuilder(container, pageIndex, viewport, enhanceTextSelection, ...rest);
    }

    // Static method for other usages
    static createTextLayerBuilder(container, pageIndex, viewport, enhanceTextSelection, ...rest) {
        return new pdfjsViewer.TextLayerBuilder({
            container,
            pageIndex,
            viewport,
            enhanceTextSelection,
            highlighter: null,
            accessibilityManager: null,
            setTextContent: () => {},
            ...rest,
        });
    }
}

// Build a plain object for window.pdfjsViewer
const viewerGlobals = { ...pdfjsViewer };
viewerGlobals.DefaultTextLayerFactory = DefaultTextLayerFactoryCompat;

// Patch the global
window.pdfjsLib = pdfjsLib;
window.pdfjsViewer = viewerGlobals;
window.DefaultTextLayerFactory = DefaultTextLayerFactoryCompat;
window.createTextLayerBuilder =
    DefaultTextLayerFactoryCompat.createTextLayerBuilder;
window.pdfjsViewer.DefaultTextLayerFactory = DefaultTextLayerFactoryCompat;
window.pdfjsViewer.createTextLayerBuilder =
    DefaultTextLayerFactoryCompat.createTextLayerBuilder;

window.pdfjsLib.GlobalWorkerOptions.workerSrc = "/vendor/pdfjs/pdf.worker.mjs";

console.log(
    "pdfjsViewer.DefaultTextLayerFactory",
    window.pdfjsViewer.DefaultTextLayerFactory,
);
console.log(
    "pdfjsViewer.TextLayerBuilder",
    window.pdfjsViewer.TextLayerBuilder,
);
