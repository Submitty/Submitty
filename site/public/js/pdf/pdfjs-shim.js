import * as pdfjsWorker from "/vendor/pdfjs/pdf.worker.min.mjs";
import * as pdfjsLib from "/vendor/pdfjs/pdf.min.mjs";
import * as pdfjsViewer from "/vendor/pdfjs/pdf_viewer.mjs";

window.pdfjsLib = pdfjsLib;
window.pdfjsViewer = pdfjsViewer;
window.pdfjsLib.GlobalWorkerOptions.workerSrc = "/vendor/pdfjs/pdf.worker.min.mjs";

console.log(window.pdfjsLib);
console.log(window.pdfjsViewer);
console.log(pdfjsWorker);
console.log(window.pdfjsLib.GlobalWorkerOptions.workerSrc);