$(function () {
  console.log('panels-selector-modal.js file linked');

  // Single Panel mode
  let singlePanelCanvas = document.querySelector('#layout-option-1 #single-panel');
  let singlePanelCanvasCTX = singlePanelCanvas.getContext('2d');

  singlePanelCanvasCTX.fillStyle = 'aliceblue';
  singlePanelCanvasCTX.fillRect(0, 0, 350, 200);

  singlePanelCanvasCTX.fillStyle = '#6d91b5';
  singlePanelCanvasCTX.fillRect(5, 2, 288, 15);
  singlePanelCanvasCTX.fillRect(5, 20, 288, 120);

  // Two panel mode with equal heights on both sides
  let equalHeight2PanelCanvas = document.querySelector('#layout-option-2 #equal-height');
  let equalHeight2PanelCanvasCTX = equalHeight2PanelCanvas.getContext('2d');

  equalHeight2PanelCanvasCTX.fillStyle = 'aliceblue';
  equalHeight2PanelCanvasCTX.fillRect(0, 0, 350, 200);

  equalHeight2PanelCanvasCTX.fillStyle = '#6d91b5';
  equalHeight2PanelCanvasCTX.fillRect(5, 2, 288, 15);
  equalHeight2PanelCanvasCTX.fillRect(5, 20, 140, 120);
  equalHeight2PanelCanvasCTX.fillRect(153, 20, 140, 120);

  // Two panel mode with taller Left panel
  let tallLeft2PanelCanvas = document.querySelector('#layout-option-2 #tall-left');
  let tallLeft2PanelCanvasCTX = tallLeft2PanelCanvas.getContext('2d');

  tallLeft2PanelCanvasCTX.fillStyle = 'aliceblue';
  tallLeft2PanelCanvasCTX.fillRect(0, 0, 350, 200);

  tallLeft2PanelCanvasCTX.fillStyle = '#6d91b5';
  tallLeft2PanelCanvasCTX.fillRect(153, 2, 140, 15);
  tallLeft2PanelCanvasCTX.fillRect(0, 0, 145, 150);
  tallLeft2PanelCanvasCTX.fillRect(153, 20, 140, 120);

  // Three Panels with Equal heights and two in left side
  let equalTwoInLeftPanelCanvas = document.querySelector('#layout-option-3 #equal-two-in-left');
  let equalTwoInLeftPanelCanvasCTX = equalTwoInLeftPanelCanvas.getContext('2d');

  equalTwoInLeftPanelCanvasCTX.fillStyle = 'aliceblue';
  equalTwoInLeftPanelCanvasCTX.fillRect(0, 0, 350, 200);

  equalTwoInLeftPanelCanvasCTX.fillStyle = '#6d91b5';
  equalTwoInLeftPanelCanvasCTX.fillRect(5, 2, 288, 15);
  equalTwoInLeftPanelCanvasCTX.fillRect(5, 20, 145, 58);
  equalTwoInLeftPanelCanvasCTX.fillRect(5, 82, 145, 58);
  equalTwoInLeftPanelCanvasCTX.fillRect(153, 20, 140, 120);

  // Three Panels with Equal heights and two in right side
  let equalTwoInRightPanelCanvas = document.querySelector('#layout-option-3 #equal-two-in-right');
  let equalTwoInRightPanelCanvasCTX = equalTwoInRightPanelCanvas.getContext('2d');

  equalTwoInRightPanelCanvasCTX.fillStyle = 'aliceblue';
  equalTwoInRightPanelCanvasCTX.fillRect(0, 0, 350, 200);

  equalTwoInRightPanelCanvasCTX.fillStyle = '#6d91b5';
  equalTwoInRightPanelCanvasCTX.fillRect(5, 2, 288, 15);
  equalTwoInRightPanelCanvasCTX.fillRect(5, 20, 145, 120);
  equalTwoInRightPanelCanvasCTX.fillRect(153, 20, 140, 58);
  equalTwoInRightPanelCanvasCTX.fillRect(153, 82, 140, 58);

  // Three Panels with Equal heights and two in left side
  let tallLeftTwoInLeftPanelCanvas = document.querySelector('#layout-option-3 #tall-left-two-in-left');
  let tallLeftTwoInLeftPanelCanvasCTX = tallLeftTwoInLeftPanelCanvas.getContext('2d');

  tallLeftTwoInLeftPanelCanvasCTX.fillStyle = 'aliceblue';
  tallLeftTwoInLeftPanelCanvasCTX.fillRect(0, 0, 350, 200);

  tallLeftTwoInLeftPanelCanvasCTX.fillStyle = '#6d91b5';
  tallLeftTwoInLeftPanelCanvasCTX.fillRect(153, 2, 140, 15);
  tallLeftTwoInLeftPanelCanvasCTX.fillRect(0, 0, 145, 73);
  tallLeftTwoInLeftPanelCanvasCTX.fillRect(0, 77, 145, 73);
  tallLeftTwoInLeftPanelCanvasCTX.fillRect(153, 20, 140, 120);

  // Three Panels with Equal heights and two in right side
  let tallLeftTwoInRightPanelCanvas = document.querySelector('#layout-option-3 #tall-left-two-in-right');
  let tallLeftTwoInRightPanelCanvasCTX = tallLeftTwoInRightPanelCanvas.getContext('2d');

  tallLeftTwoInRightPanelCanvasCTX.fillStyle = 'aliceblue';
  tallLeftTwoInRightPanelCanvasCTX.fillRect(0, 0, 350, 200);

  tallLeftTwoInRightPanelCanvasCTX.fillStyle = '#6d91b5';
  tallLeftTwoInRightPanelCanvasCTX.fillRect(153, 2, 140, 15);
  tallLeftTwoInRightPanelCanvasCTX.fillRect(0, 0, 145, 150);
  tallLeftTwoInRightPanelCanvasCTX.fillRect(153, 20, 140, 58);
  tallLeftTwoInRightPanelCanvasCTX.fillRect(153, 82, 140, 58);

});
