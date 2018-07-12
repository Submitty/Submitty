## UI

`UI` is the object that enables user interactions for management of annotations in the browser

__Table of Contents__

- [addEventListener()](#addeventlistener)
- [removeEventListener()](#removeeventlistener)
- [disableEdit()](#disableedit)
- [enableEdit()](#enableedit)
- [disablePen()](#disablepen)
- [enablePen()](#enablepen)
- [setPen()](#setpen)
- [disablePoint()](#disablepoint)
- [enablePoint()](#enablepoint)
- [disableRect()](#disablerect)
- [enableRect()](#enablerect)
- [disableText()](#disabletext)
- [enableText()](#enabletext)
- [setText()](#settext)

---

### `addEventListener()`
Adds an event handler to handle a specific type of event

__Syntax__

```js
UI.addEventListener(type, handler)
```

__Parameters__

| parameter | description |
|---|---|
| `type` | The type of event that will be subscribed to |
| `handler` | The function that will handle the event |

Types of events:

- annotation:blur
- annotation:click
- annotation:add
- annotation:edit
- annotation:delete
- comment:add
- comment:delete


### `removeEventListener()`
Removes an event handler from handling a specific type of event

__Syntax__

```js
UI.removeEventListener(type, handler)
```

__Parameters__

| parameter | description |
|---|---|
| `type` | The type of event that will be unsubscribed from  |
| `handler` | The function that handled the event |


### `disableEdit()`
Disables the ability to edit annotations from the UI

__Syntax__

```js
UI.disableEdit()
```


### `enableEdit()`
Enables the ability to edit annoations from the UI

__Syntax__

```js
UI.enableEdit()
```


### `disablePen()`
Disables the ability to draw with the pen in the UI

__Syntax__

```js
UI.disablePen()
```


### `enablePen()`
Enables the ability to draw with the pen in the UI

__Syntax__

```js
UI.enablePen()
```


### `setPen()`
Sets the size and color of the pen

__Syntax__

```js
UI.setPen([size[, color]])
```

__Parameters__

| parameter | description |
|---|---|
| `size` | The size of the pen (defaults to 12) |
| `color` | The color of the pen (defaults to "000000") |


### `disablePoint()`
Disables the ability to create a point annotation from the UI

__Syntax__

```js
UI.disablePoint()
```


### `enablePoint()`
Enables the ability to create a point annotation from the UI

__Syntax__

```js
UI.enablePoint()
```


### `disableRect()`
Disables the ability to create a rectangular annotation from the UI

__Syntax__

```js
UI.disableRect()
```


### `enableRect()`
Enables the ability to create a rectangular annotation from the UI

__Syntax__

```js
UI.enableRect(type)
```

__Parameters__

| parameter | description |
|---|---|
| `type` | The type of rectangle (one of area, highlight, or strikeout) |


### `disableText()`
Disables the ability to enter free form text from the UI

__Syntax__

```js
UI.disableText()
```


### `enableText()`
Enables the ability to enter free form text from the UI

__Syntax__

```js
UI.enableText()
```


### `setText()`
Sets the size and color of the text

__Syntax__

```js
UI.setText([size[, color]])
```

__Parameters__

| parameter | description |
|---|---|
| `size` | The size of the text (defaults to 12) |
| `color` | The color of the text (defaults to "000000") |


