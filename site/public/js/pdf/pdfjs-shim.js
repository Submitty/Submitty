import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';

// Register the valid PDF worker through a blob URL for performance measures
const absoluteUrl = new URL('/vendor/pdfjs/pdf.worker.min.mjs', window.location.origin).href;
const src = `import '${absoluteUrl}';`;

const blob = new Blob([src], { type: 'application/javascript' });
const blobUrl = URL.createObjectURL(blob);

pdfjsLib.GlobalWorkerOptions.workerPort = new Worker(blobUrl, { type: 'module' });
