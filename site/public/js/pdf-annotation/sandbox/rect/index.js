import PDFJSAnnotate from '../../';
import mockViewport from '../mockViewport';

const { UI } = PDFJSAnnotate;
const page1 = document.querySelector('#pageContainer1 .annotationLayer');
const page2 = document.querySelector('#pageContainer2 .annotationLayer');
const DOCUMENT_ID = window.location.pathname.replace(/\/$/, '');

PDFJSAnnotate.setStoreAdapter(new PDFJSAnnotate.LocalStoreAdapter());

// Get the annotations
Promise.all([
    PDFJSAnnotate.getAnnotations(DOCUMENT_ID, 1),
    PDFJSAnnotate.getAnnotations(DOCUMENT_ID, 2)
  ]).then(([ann1, ann2]) => {
    PDFJSAnnotate.render(page1, mockViewport(page1), ann1);
    PDFJSAnnotate.render(page2, mockViewport(page2), ann2);
  });

// Rect stuff
(function () {
  let tooltype = localStorage.getItem(`${DOCUMENT_ID}/tooltype`) || 'area';
  if (tooltype) {
    setActiveToolbarItem(tooltype, document.querySelector(`.toolbar button[data-tooltype=${tooltype}]`));
  }

  function setActiveToolbarItem(type, button) {
    let active = document.querySelector('.toolbar button.active');
    if (active) {
      active.classList.remove('active');
    }
    if (button) {
      button.classList.add('active');
    }
    if (tooltype !== type) {
      localStorage.setItem(`${DOCUMENT_ID}/tooltype`, type);
    }
    tooltype = type;
    
    UI.enableRect(type);
  }

  function handleToolbarClick(e) {
    if (e.target.nodeName === 'BUTTON') {
      setActiveToolbarItem(e.target.getAttribute('data-tooltype'), e.target);
    }
  }

  function handleClearClick(e) {
    if (confirm('Are you sure you want to throw your work away?')) {
      localStorage.removeItem(`${DOCUMENT_ID}/annotations`);
      page1.innerHTML = '';
      page2.innerHTML = '';
    }
  }

  document.querySelector('.toolbar').addEventListener('click', handleToolbarClick);
  document.querySelector('.toolbar .clear').addEventListener('click', handleClearClick);
})();
