import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';
import * as pdfjsViewer from '/vendor/pdfjs/pdf_viewer.mjs';

// Maintain compatibility with code expecting pdfjs globals due to ES6 modules
window.pdfjsLib = pdfjsLib;
window.pdfjsViewer = pdfjsViewer;
