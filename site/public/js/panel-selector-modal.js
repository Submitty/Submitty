$(function () {
  console.log('panels-selector-modal.js file linked');

  let equalHeight2PanelCanvas = document.querySelector('#layout-option-2 #equal-height');
  let equalHeight2PanelCanvasCTX = equalHeight2PanelCanvas.getContext('2d');

  equalHeight2PanelCanvasCTX.fillStyle = 'aliceblue';
  equalHeight2PanelCanvasCTX.fillRect(0, 0, 350, 200);

  equalHeight2PanelCanvasCTX.fillStyle = '#316498';
  equalHeight2PanelCanvasCTX.fillRect(5, 2, 288, 15);
  equalHeight2PanelCanvasCTX.fillRect(5, 20, 140, 120);
  equalHeight2PanelCanvasCTX.fillRect(153, 20, 140, 120);
});
