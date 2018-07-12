# pdf-annotate.js

[![build status](https://img.shields.io/travis/mzabriskie/pdf-annotate.js.svg?style=flat-square)](https://travis-ci.org/mzabriskie/pdf-annotate.js)
[![code coverage](https://img.shields.io/coveralls/mzabriskie/pdf-annotate.js.svg?style=flat-square)](https://coveralls.io/r/mzabriskie/pdf-annotate.js)

Annotation layer for [pdf.js](https://github.com/mozilla/pdf.js)

## Objectives

- Provide a low level annotation layer for [pdf.js](https://github.com/mozilla/pdf.js).
- Optional high level UI for managing annotations.
- Agnostic of backend, just supply your own `StoreAdapter` to fetch/store data.
- Prescribe annotation format.

## Example

```js
import __pdfjs from 'pdfjs-dist/build/pdf';
import PDFJSAnnotate from 'pdfjs-annotate';
import MyStoreAdapter from './myStoreAdapter';

const { UI } = PDFJSAnnotate;
const VIEWER = document.getElementById('viewer');
const RENDER_OPTIONS = {
  documentId: 'MyPDF.pdf',
  pdfDocument: null,
  scale: 1,
  rotate: 0
};

PDFJS.workerSrc = 'pdf.worker.js';
PDFJSAnnotate.setStoreAdapter(MyStoreAdapter);

PDFJS.getDocument(RENDER_OPTIONS.documentId).then((pdf) => {
  RENDER_OPTIONS.pdfDocument = pdf;
  VIEWER.appendChild(UI.createPage(1));
  UI.renderPage(1, RENDER_OPTIONS);
});
```

See more [examples](https://github.com/mzabriskie/pdf-annotate.js/tree/master/examples).

## Documentation

[View the docs](https://github.com/mzabriskie/pdf-annotate.js/tree/master/docs).

## Developing

```bash
# clone the repo
$ git clone https://github.com/mzabriskie/pdf-annotate.js.git
$ cd pdf-annotate.js

# intall dependencies
$ npm install

# start example server
$ npm start
$ open http://127.0.0.1:8080

# run tests
$ npm test
```

