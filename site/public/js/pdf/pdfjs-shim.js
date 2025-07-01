import * as pdfjsLib from "/vendor/pdfjs/pdf.min.mjs";
import * as pdfjsViewer from "/vendor/pdfjs/pdf_viewer.mjs";

// Manually set the pdfjsLib and pdfjsViewer global variables
window.pdfjsLib = pdfjsLib;
window.pdfjsViewer = pdfjsViewer;

// Register the worker through a blob URL to avoid CORS issues
const absoluteUrl = new URL('/vendor/pdfjs/pdf.worker.min.mjs', window.location.origin).href;
const src = `import '${absoluteUrl}';`;

const blob = new Blob([src], { type: 'application/javascript' });
const blobUrl = URL.createObjectURL(blob);

pdfjsLib.GlobalWorkerOptions.workerSrc = blobUrl;