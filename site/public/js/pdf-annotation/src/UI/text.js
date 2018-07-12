import PDFJSAnnotate from '../PDFJSAnnotate';
import { appendChild } from '../render/appendChild';
import {
  BORDER_COLOR,
  findSVGAtPoint,
  getMetadata,
  convertToSvgPoint
} from './utils';

let _enabled = false;
let input;
let _textSize;
let _textColor;

/**
 * Handle document.mouseup event
 *
 * @param {Event} e The DOM event to handle
 */
function handleDocumentMouseup(e) {
  if (input || !findSVGAtPoint(e.clientX, e.clientY)) {
    return;
  }

  input = document.createElement('input');
  input.setAttribute('id', 'pdf-annotate-text-input');
  input.setAttribute('placeholder', 'Enter text');
  input.style.border = `3px solid ${BORDER_COLOR}`;
  input.style.borderRadius = '3px';
  input.style.position = 'absolute';
  input.style.top = `${e.clientY}px`;
  input.style.left = `${e.clientX}px`;
  input.style.fontSize = `${_textSize}px`;

  input.addEventListener('blur', handleInputBlur);
  input.addEventListener('keyup', handleInputKeyup);

  document.body.appendChild(input);
  input.focus();
}

/**
 * Handle input.blur event
 */
function handleInputBlur() {
  saveText();
}

/**
 * Handle input.keyup event
 *
 * @param {Event} e The DOM event to handle
 */
function handleInputKeyup(e) {
  if (e.keyCode === 27) {
    closeInput();
  } else if (e.keyCode === 13) {
    saveText();
  }
}

/**
 * Save a text annotation from input
 */
function saveText() {
  if (input.value.trim().length > 0) {
    let clientX = parseInt(input.style.left, 10);
    let clientY = parseInt(input.style.top, 10);
    let svg = findSVGAtPoint(clientX, clientY);
    if (!svg) {
      return;
    }
    let height = _textSize;
    let { documentId, pageNumber, viewport } = getMetadata(svg);
    let scale = 1 / viewport.scale;
    let rect = svg.getBoundingClientRect();
    let pt = convertToSvgPoint([
      clientX - rect.left, 
      clientY -  rect.top + height], svg, viewport);
    let annotation = {
        type: 'textbox',
        size: _textSize * scale,
        color: _textColor,
        content: input.value.trim(),
        x: pt[0],
        y: pt[1],
        rotation: -viewport.rotation
    }

    PDFJSAnnotate.getStoreAdapter().addAnnotation(documentId, pageNumber, annotation)
      .then((annotation) => {
        appendChild(svg, annotation);
      });
  }
  
  closeInput();
}

/**
 * Close the input
 */
function closeInput() {
  if (input) {
    input.removeEventListener('blur', handleInputBlur);
    input.removeEventListener('keyup', handleInputKeyup);
    document.body.removeChild(input);
    input = null;
  }
}

/**
 * Set the text attributes
 *
 * @param {Number} textSize The size of the text
 * @param {String} textColor The color of the text
 */
export function setText(textSize = 12, textColor = '000000') {
  _textSize = parseInt(textSize, 10);
  _textColor = textColor;
}


/**
 * Enable text behavior
 */
export function enableText() {
  if (_enabled) { return; }

  _enabled = true;
  document.addEventListener('mouseup', handleDocumentMouseup);
}


/**
 * Disable text behavior
 */
export function disableText() {
  if (!_enabled) { return; }

  _enabled = false;
  document.removeEventListener('mouseup', handleDocumentMouseup);
}

