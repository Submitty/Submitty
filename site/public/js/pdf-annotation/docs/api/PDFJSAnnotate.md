## PDFJSAnnotate

`PDFJSAnnotate` is the object that will be imported into your project. It contains all the other functions and objects that you will be working with.

__Table of Contents__

- [render()](#render)
- [getAnnotations()](#getannotations)
- [setStoreAdapter()](#setstoreadapter)
- [getStoreAdapter()](#getstoreadapter)
- [StoreAdapter](#storeadapter)
- [LocalStoreAdapter](#localstoreadapter)
- [UI](#ui)

---

### `render()`
This is the main entry point into `PDFJSAnnotate`. It is used to render annotation data to an `SVGElement`.

__Syntax__

```js
let promise = PDFJSAnnotate.render(svg, viewport, annotations)
```

__Parameters__

| parameter | description |
|---|---|
| `svg` | The SVG node that the annotations should be rendered to |
| `viewport` | The viewport data that is returned from `PDFJS.getDocument(documentId).getPage(pageNumber).getViewPort(scale, rotation)` |
| `annotations` | The annotation data that is returned from `PDFJSAnnotation.getAnnotations(documentId, pageNumber)` |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `SVGElement`
- rejected: `Error`

### `getAnnotations()`
This is a helper for fetching annotations.

See [StoreAdapter.getAnnotations()](https://github.com/mzabriskie/pdf-annotate.js/blob/master/docs/api/StoreAdapter.md#getannotations).

### `setStoreAdapter()`
Sets the implementation of the `StoreAdapter` to be used by `PDFJSAnnotate`.

__Syntax__

```js
PDFJSAnnotate.setStoreAdapter(adapter)
```

__Parameters__

| parameter | description |
|---|---|
| `adapter` | The StoreAdapter implementation to be used. |

See [StoreAdapter](https://github.com/mzabriskie/pdf-annotate.js/blob/master/docs/api/StoreAdapter.md).

### `getStoreAdapter()`
Gets the implementation of `StoreAdapter` being used by `PDFJSAnnotate`.

__Syntax__

```js
let adapter = PDFJSAnnotate.getStoreAdapter()
```

__Returns__

`StoreAdapter`

### `StoreAdapter`
An abstract class that describes how `PDFJSAnnotate` communicates with your backend.

See [StoreAdapter](https://github.com/mzabriskie/pdf-annotate.js/blob/master/docs/api/StoreAdapter.md).

### LocalStoreAdapter
An implementation of `StoreAdapter` that uses `localStorage` as the backend. This is useful for prototyping or testing.

__Usage__

```js
PDFJSAnnotate.setStoreAdapter(new PDFJSAnnotate.LocalStoreAdapter())
```

### `UI`
This object contains helper functions for managing UI interactions for creating, editing, and deleting annotations.

See [UI](https://github.com/mzabriskie/pdf-annotate.js/blob/master/docs/api/UI.md).
