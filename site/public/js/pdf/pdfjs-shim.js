import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';
import * as pdfjsViewer from '/vendor/pdfjs/pdf_viewer.mjs';

// Maintain compatibility with code expecting pdfjs globals due to ES6 modules
window.pdfjsLib = pdfjsLib;
window.pdfjsViewer = pdfjsViewer;

// Register the valid worker through a blob URL for performance measures
const absoluteUrl = new URL('/vendor/pdfjs/pdf.worker.min.mjs', window.location.origin).href;
const src = `import '${absoluteUrl}';`;

const blob = new Blob([src], { type: 'application/javascript' });
const blobUrl = URL.createObjectURL(blob);

pdfjsLib.GlobalWorkerOptions.workerPort = new Worker(blobUrl, { type: 'module' });
