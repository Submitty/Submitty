/* exported togglePanelSelectorModal */
$(() => {
    // Draggable Popup box
    $('#panels-selector-modal').draggable();

    // Single Panel mode
    const singlePanelCanvas = document.querySelector('#layout-option-1 #single-panel');
    const singlePanelCanvasCTX = singlePanelCanvas.getContext('2d');

    singlePanelCanvasCTX.fillStyle = 'aliceblue';
    singlePanelCanvasCTX.fillRect(0, 0, 350, 200);

    singlePanelCanvasCTX.fillStyle = '#6d91b5';
    singlePanelCanvasCTX.fillRect(5, 2, 288, 15);
    singlePanelCanvasCTX.fillRect(5, 20, 288, 120);

    // Two panel mode with equal heights on both sides
    const equalHeight2PanelCanvas = document.querySelector('#layout-option-2 #equal-height');
    const equalHeight2PanelCanvasCTX = equalHeight2PanelCanvas.getContext('2d');

    equalHeight2PanelCanvasCTX.fillStyle = 'aliceblue';
    equalHeight2PanelCanvasCTX.fillRect(0, 0, 350, 200);

    equalHeight2PanelCanvasCTX.fillStyle = '#6d91b5';
    equalHeight2PanelCanvasCTX.fillRect(5, 2, 288, 15);
    equalHeight2PanelCanvasCTX.fillRect(5, 20, 140, 120);
    equalHeight2PanelCanvasCTX.fillRect(153, 20, 140, 120);

    // Two panel mode with taller Left panel
    const tallLeft2PanelCanvas = document.querySelector('#layout-option-2 #tall-left');
    const tallLeft2PanelCanvasCTX = tallLeft2PanelCanvas.getContext('2d');

    tallLeft2PanelCanvasCTX.fillStyle = 'aliceblue';
    tallLeft2PanelCanvasCTX.fillRect(0, 0, 350, 200);

    tallLeft2PanelCanvasCTX.fillStyle = '#6d91b5';
    tallLeft2PanelCanvasCTX.fillRect(153, 2, 140, 15);
    tallLeft2PanelCanvasCTX.fillRect(0, 0, 145, 150);
    tallLeft2PanelCanvasCTX.fillRect(153, 20, 140, 120);

    // Three Panels with Equal heights and two in left side
    const equalTwoInLeftPanelCanvas = document.querySelector('#layout-option-3 #equal-two-in-left');
    const equalTwoInLeftPanelCanvasCTX = equalTwoInLeftPanelCanvas.getContext('2d');

    equalTwoInLeftPanelCanvasCTX.fillStyle = 'aliceblue';
    equalTwoInLeftPanelCanvasCTX.fillRect(0, 0, 350, 200);

    equalTwoInLeftPanelCanvasCTX.fillStyle = '#6d91b5';
    equalTwoInLeftPanelCanvasCTX.fillRect(5, 2, 288, 15);
    equalTwoInLeftPanelCanvasCTX.fillRect(5, 20, 145, 58);
    equalTwoInLeftPanelCanvasCTX.fillRect(5, 82, 145, 58);
    equalTwoInLeftPanelCanvasCTX.fillRect(153, 20, 140, 120);

    // Three Panels with Equal heights and two in right side
    const equalTwoInRightPanelCanvas = document.querySelector('#layout-option-3 #equal-two-in-right');
    const equalTwoInRightPanelCanvasCTX = equalTwoInRightPanelCanvas.getContext('2d');

    equalTwoInRightPanelCanvasCTX.fillStyle = 'aliceblue';
    equalTwoInRightPanelCanvasCTX.fillRect(0, 0, 350, 200);

    equalTwoInRightPanelCanvasCTX.fillStyle = '#6d91b5';
    equalTwoInRightPanelCanvasCTX.fillRect(5, 2, 288, 15);
    equalTwoInRightPanelCanvasCTX.fillRect(5, 20, 145, 120);
    equalTwoInRightPanelCanvasCTX.fillRect(153, 20, 140, 58);
    equalTwoInRightPanelCanvasCTX.fillRect(153, 82, 140, 58);

    // Three Panels with taller Left panel and two in left side
    const tallLeftTwoInLeftPanelCanvas = document.querySelector('#layout-option-3 #tall-left-two-in-left');
    const tallLeftTwoInLeftPanelCanvasCTX = tallLeftTwoInLeftPanelCanvas.getContext('2d');

    tallLeftTwoInLeftPanelCanvasCTX.fillStyle = 'aliceblue';
    tallLeftTwoInLeftPanelCanvasCTX.fillRect(0, 0, 350, 200);

    tallLeftTwoInLeftPanelCanvasCTX.fillStyle = '#6d91b5';
    tallLeftTwoInLeftPanelCanvasCTX.fillRect(153, 2, 140, 15);
    tallLeftTwoInLeftPanelCanvasCTX.fillRect(0, 0, 145, 73);
    tallLeftTwoInLeftPanelCanvasCTX.fillRect(0, 77, 145, 73);
    tallLeftTwoInLeftPanelCanvasCTX.fillRect(153, 20, 140, 120);

    // Three Panels with taller Left panel and two in right side
    const tallLeftTwoInRightPanelCanvas = document.querySelector('#layout-option-3 #tall-left-two-in-right');
    const tallLeftTwoInRightPanelCanvasCTX = tallLeftTwoInRightPanelCanvas.getContext('2d');

    tallLeftTwoInRightPanelCanvasCTX.fillStyle = 'aliceblue';
    tallLeftTwoInRightPanelCanvasCTX.fillRect(0, 0, 350, 200);

    tallLeftTwoInRightPanelCanvasCTX.fillStyle = '#6d91b5';
    tallLeftTwoInRightPanelCanvasCTX.fillRect(153, 2, 140, 15);
    tallLeftTwoInRightPanelCanvasCTX.fillRect(0, 0, 145, 150);
    tallLeftTwoInRightPanelCanvasCTX.fillRect(153, 20, 140, 58);
    tallLeftTwoInRightPanelCanvasCTX.fillRect(153, 82, 140, 58);

    // Four Panels with equal heights
    const equalFourPanelCanvas = document.querySelector('#layout-option-4 #equal-four-panel');
    const equalFourPanelCanvasCTX = equalFourPanelCanvas.getContext('2d');

    equalFourPanelCanvasCTX.fillStyle = 'aliceblue';
    equalFourPanelCanvasCTX.fillRect(0, 0, 350, 200);

    equalFourPanelCanvasCTX.fillStyle = '#6d91b5';
    equalFourPanelCanvasCTX.fillRect(5, 2, 288, 15);

    equalFourPanelCanvasCTX.fillRect(5, 20, 145, 58);
    equalFourPanelCanvasCTX.fillRect(5, 82, 145, 58);

    equalFourPanelCanvasCTX.fillRect(153, 20, 140, 58);
    equalFourPanelCanvasCTX.fillRect(153, 82, 140, 58);

    // Four Panels with taller left panel
    const tallLeftFourPanelCanvas = document.querySelector('#layout-option-4 #tall-left-four-panel');
    const tallLeftFourPanelCanvasCTX = tallLeftFourPanelCanvas.getContext('2d');

    tallLeftFourPanelCanvasCTX.fillStyle = 'aliceblue';
    tallLeftFourPanelCanvasCTX.fillRect(0, 0, 350, 200);

    tallLeftFourPanelCanvasCTX.fillStyle = '#6d91b5';
    tallLeftFourPanelCanvasCTX.fillRect(153, 2, 140, 15);

    tallLeftFourPanelCanvasCTX.fillRect(0, 0, 145, 73);
    tallLeftFourPanelCanvasCTX.fillRect(0, 77, 145, 73);

    tallLeftFourPanelCanvasCTX.fillRect(153, 20, 140, 58);
    tallLeftFourPanelCanvasCTX.fillRect(153, 82, 140, 58);

});

function togglePanelSelectorModal(show) {
    if (show) {
        $('#panels-selector-modal').removeClass('hide');
    }
    else {
        $('#panels-selector-modal').addClass('hide');
    }
}
