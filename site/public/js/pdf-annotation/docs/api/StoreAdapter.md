## StoreAdapter

The `StoreAdapter` is an abstract class that will need to be implemented for fetching annotation data. An unimplemented instance of `StoreAdapter` is used as the default adapter. Any call to an umimplemented adapter will result in an `Error` being thrown.

__Usage__

```js
let MyStoreAdapter = new PDFJSAnnotate.StoreAdapter({
  getAnnotations(documentId, pageNumber) {/* ... */},

  getAnnotation(documentId, annotationId) {/* ... */},

  addAnnotation(documentId, pageNumber, annotation) {/* ... */},

  editAnnotation(documentId, pageNumber, annotation) {/* ... */},

  deleteAnnotation(documentId, annotationId) {/* ... */},
  
  addComment(documentId, annotationId, content) {/* ... */},

  deleteComment(documentId, commentId) {/* ... */}
});
```

__Table of Contents__

- [getAnnotations()](#getannotations)
- [getAnnotation()](#getannotation)
- [addAnnotation()](#addannotation)
- [editAnnotation()](#editannotation)
- [deleteAnnotation()](#deleteannotation)
- [addComment()](#addcomment)
- [deleteComment()](#deletecomment)

---

### `getAnnotations()`
Get all the annotations for a specific page within a document

__Syntax__

```js
let promise = adapter.getAnnotations(documentId, pageNumber)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `pageNumber` | The page number within the document |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Object`
- rejected: `Error`

The fulfilled object will contain the following properties:

| property | description |
|---|---|
| `documentId` | `String` The ID of the document |
| `pageNumer` | `Number` The page number within the document |
| `annotations` | `Array` The annotations for the page |

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().getAnnotations('example.pdf', 1)
  .then((data) => {
    console.log(data.documentId); // "example.pdf"
    console.log(data.pageNumber); // 1
    console.log(data.annotations); // Array
  }, (error) => {
    console.log(error.message);
  });
```

### `getAnnotation()`
Get a specific annotation

__Syntax__

```js
let promise = adapter.getAnnotation(documentId, annotationId)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `annotationId` | The ID of the annotation |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Object` The annotation
- rejected: `Error`

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().getAnnotation(
    'example.pdf',
    'ef158e68-c54c-4c4d-b10c-7bc8c0c7fe7c'
  ).then((annotation) => {
    console.log(annotation); // Object
  }, (error) => {
    console.log(error.message);
  });
```


### `addAnnotation()`
Add an annotation to a document

__Syntax__

```js
let promise = adapter.addAnnotation(documentId, pageNumber, annotation)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `pageNumber` | The page number within the document |
| `annotation` | The JSON definition for the annotation |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Object` The newly added annotation
- rejected: `Error`

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().addAnnotation(
    'example.pdf',
    1,
    {
      type: 'area',
      width: 100,
      height: 50,
      x: 75,
      y: 75
    }
  ).then((annotation) => {
    console.log(annotation); // Object
  }, (error) => {
    console.log(error.message);
  });
```


### `editAnnotation()`
Edit an annotation

__Syntax__

```js
let promise = adapter.editAnnotation(documentId, pageNumber, annotation)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `pageNumber` | The page number within the document |
| `annotation` | The JSON definition for the annotation |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Object` The updated annotation
- rejected: `Error`

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().editAnnotation(
    'example.pdf',
    1,
    {
      uuid: 'ef158e68-c54c-4c4d-b10c-7bc8c0c7fe7c',
      type: 'area',
      width: 100,
      height: 50,
      x: 250,
      y: 100
    }
  ).then((annotation) => {
    console.log(annotation); // Object
  }, (error) => {
    console.log(error.message);
  });
```


### `deleteAnnotation()`
Delete an annotation

__Syntax__

```js
let promise = adapter.deleteAnnotation(documentId, annotationId)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `annotationId` | The ID of the annotation |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Boolean`
- rejected: `Error`

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().deleteAnnotation(
    'example.pdf',
    'ef158e68-c54c-4c4d-b10c-7bc8c0c7fe7c'
  ).then(() => {
    console.log('deleted');
  }, (error) => {
    console.log(error.message);
  });
```

### `addComment()`
Add a comment to an annotation

__Syntax__

```js
let promise = adapter.addComment(documentId, annotationId, content)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `annotationId` | The ID of the annotation |
| `content` | The content of the comment |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Object` The newly added comment
- rejected: `Error`

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().addComment(
    'example.pdf',
    'ef158e68-c54c-4c4d-b10c-7bc8c0c7fe7c',
    'Hello world!'
  ).then((comment) => {
    console.log(comment); // Object
  }, (error) => {
    console.log(error.message);
  });
```


### `deleteComment()`
Delete a comment

__Syntax__

```js
let promise = adapter.deleteComment(documentId, commentId)
```

__Parameters__

| parameter | description |
|---|---|
| `documentId` | The ID of the document |
| `commentId` | The ID of the comment |

__Returns__

`Promise`

A settled Promise will be either:

- fulfilled: `Boolean`
- rejected: `Error`

__Usage__

```js
PDFJSAnnotate.getStoreAdapter().deleteComment(
    'example.pdf',
    '8ce957c4-90fa-475b-bd5c-ae9d5ab7c0ae'
  ).then(() => {
    console.log('deleted');
  }, (error) => {
    console.log(error.message);
  });
```


