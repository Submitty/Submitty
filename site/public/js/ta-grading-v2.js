//Used to reset users cookies
let cookie_version = 1;

// Width of mobile and Tablet screens width
const MOBILE_WIDTH = 540;

// tracks if the current display screen is mobile
let isMobileView = false;

// Panel elements info to be used for layout designs
let panelElements = [
  { str: "autograding_results", icon: ".grading_toolbar .fa-list"},
  { str: "grading_rubric", icon: ".grading_toolbar .fa-edit"},
  { str: "submission_browser", icon: "grading_toolbar .fa-folder-open.icon-header"},
  { str: "solution_ta_notes", icon: "grading_toolbar .fa-check.icon-header"},
  { str: "student_info", icon: ".grading_toolbar .fa-user"},
  { str: "peer_info", icon: ".grading_toolbar .fa-users"},
  { str: "discussion_browser", icon: ".grading_toolbar .fa-comment-alt"},
  { str: "regrade_info", icon: ".grading_toolbar .grade_inquiry_icon"},
  { str: "notebook-view", icon: ".grading_toolbar .fas fa-book-open"}
];

// Tracks the layout of TA grading page
const taLayoutDet = {
  isTwoPanelsEnabled: false,
  isFullScreenMode: false,
  isFullLeftColumnMode: false,
  currentOpenPanel: panelElements[0].str,
  currentTwoPanels: {
    left: null,
    right: null,
  },
  leftPanelWidth: "50%",
  panelsBucket: {
    leftSelector : ".two-panel-item.two-panel-left",
      rightSelector : ".two-panel-item.two-panel-right",
      dragBarSelector: ".two-panel-drag-bar",
  },
};

// Grading Panel header width
let maxHeaderWidth = 0;
// Navigation Toolbar Panel header width
let maxNavbarWidth = 0;

// Only keep those panels which are available
function updateThePanelsElements(panelsAvailabilityObj) {
  // Attach the isAvailable to the panel elements to manage them
  panelElements = panelElements.filter((panel) => {
    return !!panelsAvailabilityObj[panel.str];
  });

}

$(function () {
  Object.assign(taLayoutDet, getSavedTaLayoutDetails());
  // Check initially if its the mobile screen view or not
  isMobileView = window.innerWidth <= MOBILE_WIDTH;
  initializeTaLayout();

  window.addEventListener('resize', () => {
    let wasMobileView = isMobileView;
    isMobileView = window.innerWidth <= MOBILE_WIDTH;
    // if the width is switched between smaller and bigger screens, re-initialize the layout
    if (wasMobileView !== isMobileView) {
      initializeTaLayout();
    }
  });

  // Progress bar value
  let value = $(".progressbar").val() ? $(".progressbar").val() : 0;
  $(".progress-value").html("<b>" + value + '%</b>');

  // panel position selector change event
  $(".grade-panel .panel-position-cont").change(function() {
    let panelSpanId = $(this).parent().attr('id');
    let position = $(this).val();
    if (panelSpanId) {
      const panelId = panelSpanId.split(/(_|-)btn/)[0]; 
      setPanelsVisibilities(panelId, null, position);
      $('select#' + panelId + '_select').hide();
    }
  });

  // Grading panel toggle buttons
  $(".grade-panel button").click(function () {
    const btnCont = $(this).parent();
    let panelSpanId = btnCont.attr('id');

    if (!panelSpanId) {
      return;
    }

    const panelId = panelSpanId.split(/(_|-)btn/)[0];
    const selectEle =  $('select#' + panelId + '_select');
    // Hide all select dropdown except the current one
    $('select.panel-position-cont').not(selectEle).hide();

    const isPanelOpen = $('#' + panelId).is(':visible') && btnCont.hasClass('active');
    // If panel is not in-view and two-panel-mode is enabled show the drop-down to select position,
    // otherwise just toggle it
    if (isPanelOpen || !(taLayoutDet.isTwoPanelsEnabled && !isMobileView)) {
      setPanelsVisibilities(panelId);
    } else {
      // removing previously selected option
      selectEle.val(0);
      selectEle.is(':visible') ? selectEle.hide() : selectEle.show();
    }
  });
  // Remove the select options which are open
  function hidePanelPositionSelect() {
    $('select.panel-position-cont').hide();
    document.removeEventListener('click', hidePanelPositionSelect);
  }
  // Check for the panels status initially
  adjustGradingPanelHeader();
  const resizeObserver = new ResizeObserver(() => {
      adjustGradingPanelHeader();
  });
  // calling it for the first time i.e initializing
  adjustGradingPanelHeader();
  resizeObserver.observe(document.getElementById('grading-panel-header'));

  // Dynamically resize the textarea height as per the provided content
  document.querySelectorAll('[id^=textbox-solution-]').forEach( textarea => {
    textarea.addEventListener('keyup', function () {
      setTimeout(function() {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
      },0);
    });
  });

});

// returns taLayoutDet object from LS, and if its not present returns empty object
function getSavedTaLayoutDetails() {
  const savedData = localStorage.getItem('taLayoutDetails');
  return savedData ? JSON.parse(savedData) : {};
}

function saveTaLayoutDetails() {
  localStorage.setItem("taLayoutDetails", JSON.stringify(taLayoutDet));
}

function initializeTwoPanelDrag () {
  // Select all the DOM elements for dragging in two-panel-mode
  const leftPanel = document.querySelector(taLayoutDet.panelsBucket.leftSelector);
  const rightPanel = document.querySelector(taLayoutDet.panelsBucket.rightSelector);
  const panelCont = leftPanel.parentElement;
  const dragbar = document.querySelector(taLayoutDet.panelsBucket.dragBarSelector);

  let xPos = 0, yPos = 0, leftPanelWidth = 0;

  // Width of left side
  const mouseDownHandler = function(e) {
    // Get the current mouse position
    xPos = e.clientX;
    yPos = e.clientY;
    leftPanelWidth = leftPanel.getBoundingClientRect().width;

    // Attach the listeners to `document`
    document.addEventListener("mousemove", mouseMoveHandler);
    document.addEventListener("mouseup", mouseUpHandler);
  };

  const mouseUpHandler = () => {
    // remove the dragging CSS props to go back to initial styling
    dragbar.style.removeProperty("cursor");
    document.body.style.removeProperty("cursor");
    document.body.style.removeProperty("user-select");
    document.body.style.removeProperty("pointer-events");
    dragbar.style.removeProperty("filter");

    // Remove the handlers of `mousemove` and `mouseup`
    document.removeEventListener("mousemove", mouseMoveHandler);
    document.removeEventListener("mouseup", mouseUpHandler);
  };

  const mouseMoveHandler = (e) => {
    const dx = e.clientX - xPos;
    const updateLeftPanelWidth = (leftPanelWidth + dx) * 100 / panelCont.getBoundingClientRect().width;
    leftPanel.style.width = `${updateLeftPanelWidth}%`;
    // save the updated width of left column
    taLayoutDet.leftPanelWidth = `${updateLeftPanelWidth}%`;
    saveTaLayoutDetails();

    // consistent mouse pointer during dragging
    document.body.style.cursor = "col-resize";
    // Disable text selection when dragging
    document.body.style.userSelect = "none";
    document.body.style.pointerEvents = "none";
    // Add blurry effect on drag-bar
    dragbar.style.filter = "blur(5px)";
  };
  dragbar.addEventListener("mousedown", mouseDownHandler);
  // update the width whenever left-cols are switched between normal and full-left-col
  updateLeftColsWidth();
  saveTaLayoutDetails();
}

function initializeTaLayout() {
  if (isMobileView) {
    resetTwoPanelLayout();
  }
  else if (taLayoutDet.isTwoPanelsEnabled) {
    toggleTwoPanelMode();
    // initialize the layout
    initializeTwoPanelDrag();
    if (taLayoutDet.isFullLeftColumnMode) {
      toggleFullLeftColumnMode();
    }
  }
  else {
    setPanelsVisibilities(taLayoutDet.currentOpenPanel);
  }
  if (taLayoutDet.isFullScreenMode) {
    toggleFullScreenMode();
  }
  updateLeftColsWidth();
}

// updates width of left columns (normal + full-left-col) with the last saved layout width
function updateLeftColsWidth() {
  const leftColumns = $(".two-panel-item.two-panel-left, .content-item.content-item-left");
  leftColumns.css({
    width: taLayoutDet.leftPanelWidth ? taLayoutDet.leftPanelWidth : "50%"
  });
}

/*
  Adjust buttons inside Grading panel header and shows only icons on smaller screens
 */
function adjustGradingPanelHeader () {
  const header = $('#grading-panel-header');
  const headerBox = $('.panel-header-box');
  const navBar = $('#bar_wrapper');
  const navBarBox = $('.navigation-box');

  if (maxHeaderWidth < headerBox.width()) {
    maxHeaderWidth = headerBox.width();
  }
  if (maxHeaderWidth > header.width()) {
    headerBox.addClass('smaller-header');
  } else {
    headerBox.removeClass('smaller-header');
  }
  // changes for the navigation toolbar buttons
  if (maxNavbarWidth < $('.grading_toolbar').width()) {
    maxNavbarWidth = $('.grading_toolbar').width();
  }
  if (maxNavbarWidth > navBar.width()) {
    navBarBox.addClass('smaller-navbar');
  } else {
    navBarBox.removeClass('smaller-navbar');
  }
  // On mobile display screen hide the two-panel-mode
  if (isMobileView) {
    // hide the buttons
    navBarBox.addClass('mobile-view');
  } else {
    navBarBox.removeClass('mobile-view');
  }
  // From the complete content remove the height occupied by navigation-bar and panel-header element
  // 6 is used for adding some space in the bottom
  document.querySelector('.panels-container').style.height = "calc(100% - " + (header.outerHeight() + navBar.outerHeight() +6) + "px)";
}

function onAjaxInit() {}

function readCookies(){

  let silent_edit_enabled = document.cookie.replace(/(?:(?:^|.*;\s*)silent_edit_enabled\s*\=\s*([^;]*).*$)|^.*$/, "$1") === 'true';

  let autoscroll = document.cookie.replace(/(?:(?:^|.*;\s*)autoscroll\s*\=\s*([^;]*).*$)|^.*$/, "$1");
  let opened_mark = document.cookie.replace(/(?:(?:^|.*;\s*)opened_mark\s*\=\s*([^;]*).*$)|^.*$/, "$1");
  let scroll_pixel = document.cookie.replace(/(?:(?:^|.*;\s*)scroll_pixel\s*\=\s*([^;]*).*$)|^.*$/, "$1");

  let testcases = document.cookie.replace(/(?:(?:^|.*;\s*)testcases\s*\=\s*([^;]*).*$)|^.*$/, "$1");

  let files = document.cookie.replace(/(?:(?:^|.*;\s*)files\s*\=\s*([^;]*).*$)|^.*$/, "$1");

  $('#silent-edit-id').prop('checked', silent_edit_enabled);

  onAjaxInit = function() {
    $('#title-'+opened_mark).click();
    if (scroll_pixel > 0) {
      document.getElementById('grading_rubric').scrollTop = scroll_pixel;
    }
  };

  if (autoscroll == "on") {
    let files_array = JSON.parse(files);
    files_array.forEach(function(element) {
      let file_path = element.split('#$SPLIT#$');
      let current = $('#file-container');
      for (let x = 0; x < file_path.length; x++) {
        current.children().each(function() {
          if (x == file_path.length - 1) {
            $(this).children('div[id^=file_viewer_]').each(function() {
              if ($(this)[0].dataset.file_name == file_path[x] && !$($(this)[0]).hasClass('open')) {
                openFrame($(this)[0].dataset.file_name, $(this)[0].dataset.file_url, $(this).attr('id').split('_')[2]);
              }
            });
            $(this).children('div[id^=div_viewer_]').each(function() {
              if ($(this)[0].dataset.file_name == file_path[x] && !$($(this)[0]).hasClass('open')) {
                openDiv($(this).attr('id').split('_')[2]);
              }
            });
          }
          else {
            $(this).children('div[id^=div_viewer_]').each(function() {
              if ($(this)[0].dataset.file_name == file_path[x]) {
                current = $(this);
                return false;
              }
            });
          }
        });
      }
    });
  }
  for(let x=0; x<testcases.length; x++){
    if(testcases[x]!=='[' && testcases[x]!==']')
      openAutoGrading(testcases[x]);
  }
}

function updateCookies(){
  document.cookie = "silent_edit_enabled=" + isSilentEditModeEnabled() + "; path=/;";
  let autoscroll = $('#autoscroll_id').is(":checked") ? "on" : "off";
  document.cookie = "autoscroll=" + autoscroll + "; path=/;";

  let files = [];
  $('#file-container').children().each(function() {
    $(this).children('div[id^=div_viewer_]').each(function() {
      files = files.concat(findAllOpenedFiles($(this), "", $(this)[0].dataset.file_name, [], true));
    });
  });
  files = JSON.stringify(files);
  document.cookie = "files=" + files + "; path=/;";

  document.cookie = "cookie_version=" + cookie_version + "; path=/;";
}

//-----------------------------------------------------------------------------
// Student navigation
function gotoMainPage() {

  let window_location = $("#main-page")[0].dataset.href

  if (getGradeableId() !== '') {
    closeAllComponents(true).then(function () {
      window.location = window_location;
    }).catch(function () {
      if (confirm("Could not save open component, go to main page anyway?")) {
        window.location = window_location;
      }
    });
  }
  else {
    window.location = window_location;
  }
}

function gotoPrevStudent(to_ungraded = false) {

  let selector;
  let window_location;

  if(to_ungraded === true) {
    selector = "#prev-ungraded-student";
    window_location = $(selector)[0].dataset.href;

    // Append extra get param
    window_location += '&component_id=' + getFirstOpenComponentId();

  }
  else {
    selector = "#prev-student";
    window_location = $(selector)[0].dataset.href
  }

  if (getGradeableId() !== '') {
    closeAllComponents(true).then(function () {
      window.location = window_location;
    }).catch(function () {
      if (confirm("Could not save open component, change student anyway?")) {
        window.location = window_location;
      }
    });
  }
  else {
    window.location = window_location;
  }
}

function gotoNextStudent(to_ungraded = false) {

  let selector;
  let window_location;

  if(to_ungraded === true) {
    selector = "#next-ungraded-student";
    window_location = $(selector)[0].dataset.href;

    // Append extra get param
    window_location += '&component_id=' + getFirstOpenComponentId();
  }
  else {
    selector = "#next-student";
    window_location = $(selector)[0].dataset.href
  }

  if (getGradeableId() !== '') {
    closeAllComponents(true).then(function () {
      window.location = window_location;
    }).catch(function () {
      if (confirm("Could not save open component, change student anyway?")) {
        window.location = window_location;
      }
    });
  }
  else {
    window.location = window_location;
  }
}
//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Student", code: "ArrowLeft"}, function() {
  gotoPrevStudent();
});
registerKeyHandler({name: "Next Student", code: "ArrowRight"}, function() {
  gotoNextStudent();
});

//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Ungraded Student", code: "Shift ArrowLeft"}, function() {
  gotoPrevStudent(true);
});
registerKeyHandler({name: "Next Ungraded Student", code: "Shift ArrowRight"}, function() {
  gotoNextStudent(true);
});

//-----------------------------------------------------------------------------
// Panel show/hide
//

function resetTwoPanelLayout() {
  // hide all the two-panel-mode related nodes
  $('.two-panel-cont').removeClass("active");
  $("#two-panel-mode-btn").removeClass("active");
  $("#two-panel-exchange-btn").removeClass("active");
  $("#full-left-column-btn").removeClass("visible");

  // Remove the full-left-column view (if it's currently present or is in-view) as it's meant for two-panel-mode only
  $(".content-item-left, .content-drag-bar, #full-left-column-btn").removeClass("active");
  $(".two-panel-item.two-panel-left, .two-panel-drag-bar").addClass("active");
  // reset other variables
  taLayoutDet.panelsBucket.leftSelector = ".two-panel-item.two-panel-left";
  taLayoutDet.panelsBucket.dragBarSelector = ".two-panel-drag-bar";

  const leftPanelId = taLayoutDet.currentTwoPanels.left;
  const rightPanelId = taLayoutDet.currentTwoPanels.right;
  //Now Fetch the panels from DOM
  const leftPanel = document.getElementById(leftPanelId);
  const rightPanel = document.getElementById(rightPanelId);

  if (rightPanel) {
    document.querySelector('.panels-container').append(rightPanel);
    taLayoutDet.currentOpenPanel = rightPanelId;
  }
  if (leftPanel) {
    document.querySelector('.panels-container').append(leftPanel);
    taLayoutDet.currentOpenPanel = leftPanelId;
  }
  // current open panel will be either left or right panel from two-panel-mode
  // passing forceVisible = true, otherwise this method will just toggle it and it will get hidden
  setPanelsVisibilities(taLayoutDet.currentOpenPanel, true);
  initializeTwoPanelDrag();
}

function checkForTwoPanelLayoutChange (isPanelAdded, panelId = null, panelPosition = null) {
  // update the global variable
  if (isPanelAdded) {
    taLayoutDet.currentTwoPanels[panelPosition] = panelId;
  } else {
    // panel is going to be removed from screen
    // check which one out of the left or right is going to be hidden
    if (taLayoutDet.currentTwoPanels.left === panelId ) {
      taLayoutDet.currentTwoPanels.left = null;
    }
    if (taLayoutDet.currentTwoPanels.right === panelId ) {
      taLayoutDet.currentTwoPanels.right = null;
    }
  }
  saveTaLayoutDetails();
}

// Keep only those panels which are part of the two panel layout
function setTwoPanelModeVisibilities () {
    panelElements.forEach((panel) => {
      let id_str = document.getElementById("#" + panel.str + "_btn") ? "#" + panel.str + "_btn" : "#" + panel.str + "-btn";

      if (taLayoutDet.currentTwoPanels.left === panel.str || taLayoutDet.currentTwoPanels.right === panel.str) {
        $("#" + panel.str).toggle(true);
        $(panel.icon).toggleClass('icon-selected', true);
        $(id_str).toggleClass('active', true);
      } else {
        $("#" + panel.str).toggle(false);
        $(panel.icon).toggleClass('icon-selected', false);
        $(id_str).toggleClass('active', false);
      }
    });
}

function setPanelsVisibilities (ele, forceVisible=null, position=null) {
  panelElements.forEach((panel) => {
    let id_str = document.getElementById("#" + panel.str + "_btn") ? "#" + panel.str + "_btn" : "#" + panel.str + "-btn";

    if (panel.str === ele) {
      const eleVisibility = forceVisible !== null ? forceVisible : !$("#" + panel.str).is(":visible");
      $("#" + panel.str).toggle(eleVisibility);
      $(panel.icon).toggleClass('icon-selected', eleVisibility);
      $(id_str).toggleClass('active', eleVisibility);

      if (taLayoutDet.isTwoPanelsEnabled && !isMobileView) {
        checkForTwoPanelLayoutChange(eleVisibility, panel.str, position);
      } else {
        // update the global variable
        taLayoutDet.currentOpenPanel = eleVisibility ? panel.str : null;
      }
    } else if ((taLayoutDet.isTwoPanelsEnabled && !isMobileView
      && taLayoutDet.currentTwoPanels.right !== panel.str
      && taLayoutDet.currentTwoPanels.left !== panel.str) || panel.str !== ele ) {
      //only hide those panels which are not given panel and not in taLayoutDet.currentTwoPanels if the twoPanelMode is enabled
      $("#" + panel.str).hide();
      $(panel.icon).removeClass('icon-selected');
      $(id_str).removeClass('active');
    }
  });
  // update the two-panels-layout if it's enabled
  if (taLayoutDet.isTwoPanelsEnabled && !isMobileView) {
    updateTwoPanelLayout();
  } else {
    saveTaLayoutDetails();
  }
}

function toggleFullScreenMode () {
  $("main#main").toggleClass('full-screen-mode');
  $("#fullscreen-btn-cont").toggleClass('active');
  taLayoutDet.isFullScreenMode = $("main#main").hasClass('full-screen-mode');
  saveTaLayoutDetails();
}

function toggleFullLeftColumnMode () {
  // toggle between the normal left and full left panel mode
  $(".content-item-left, .content-drag-bar, .two-panel-item.two-panel-left, .two-panel-drag-bar, #full-left-column-btn")
    .toggleClass("active");

  // Update the DOM selector for the left container
  let newLeftPanelBucketSelector, newDragBarSelector;
  if ($(".content-item-left").is(':visible')) {
    newLeftPanelBucketSelector = ".content-item-left";
    newDragBarSelector = ".content-drag-bar";
    taLayoutDet.isFullLeftColumnMode = true;
  } else {
    newLeftPanelBucketSelector = ".two-panel-item.two-panel-left";
    newDragBarSelector = ".two-panel-drag-bar";
    taLayoutDet.isFullLeftColumnMode = false;
  }
  // Move the children from previous left column to new "full sized" left column bucket
  const leftPanelBucket = document.querySelector(taLayoutDet.panelsBucket.leftSelector).childNodes;
  for(let idx = 0; idx < leftPanelBucket.length; idx++) {
    document.querySelector(newLeftPanelBucketSelector).append(leftPanelBucket[idx]);
  }
  taLayoutDet.panelsBucket.leftSelector = newLeftPanelBucketSelector;
  taLayoutDet.panelsBucket.dragBarSelector = newDragBarSelector;
  // update the dragging event for two panels
  initializeTwoPanelDrag();
}

function toggleTwoPanelMode() {
  const twoPanelCont = $('.two-panel-cont');
  taLayoutDet.isTwoPanelsEnabled = !twoPanelCont.is(":visible");

  if (taLayoutDet.isTwoPanelsEnabled && !isMobileView) {
    twoPanelCont.addClass("active");
    $("#two-panel-exchange-btn").addClass("active");
    $("#full-left-column-btn").addClass("visible");
    // If there is any panel opened just use that and fetch the next one for left side...
    if (taLayoutDet.currentOpenPanel && !(taLayoutDet.currentTwoPanels.left || taLayoutDet.currentOpenPanel.right)) {
      taLayoutDet.currentTwoPanels.left = taLayoutDet.currentOpenPanel;
      panelElements.every((panel, idx) => {
        if (taLayoutDet.currentTwoPanels.left === panel.str) {
          let nextIdx = (idx + 1) === panelElements.length ? 0 : idx + 1;
          taLayoutDet.currentTwoPanels.right = panelElements[nextIdx].str;
          return false;
        }
        return true;
      });

    } else if(!taLayoutDet.currentOpenPanel) {
      // if there is no currently opened panel fill the panels with the first two
      taLayoutDet.currentTwoPanels = {
        left: panelElements[0].str,
        right: panelElements[1].str
      };
    }
    updateTwoPanelLayout();
    $("#two-panel-mode-btn").addClass("active");
  } else {
    resetTwoPanelLayout();
    taLayoutDet.currentTwoPanels = {
      left: null,
      right: null
    };
  }
}

// Handles the DOM manipulation to update the two panel layout
function updateTwoPanelLayout () {
  // fetch the panels by their ids
  const leftPanel = document.getElementById(taLayoutDet.currentTwoPanels.left);
  const rightPanel = document.getElementById(taLayoutDet.currentTwoPanels.right);

  setTwoPanelModeVisibilities();
  for (const panelIdx in taLayoutDet.panelsBucket) {
    const panelCont = document.querySelector(taLayoutDet.panelsBucket[panelIdx]).childNodes;
    // Move all the panels from the left and right buckets to the main panels-container
    for (let idx = 0; idx < panelCont.length; idx++) {
      document.querySelector(".panels-container").append(panelCont[idx]);
    }
  }
  // finally append the latest panels to their respective buckets
  if (leftPanel) {
    document.querySelector(taLayoutDet.panelsBucket.leftSelector).append(leftPanel);
  }
  if (rightPanel) {
    document.querySelector(taLayoutDet.panelsBucket.rightSelector).append(rightPanel);
  }
  saveTaLayoutDetails();
}

// Exchanges positions of left and right panels
function exchangeTwoPanels () {
  if (taLayoutDet.currentTwoPanels.left && taLayoutDet.currentTwoPanels.right) {
    const leftPanel = taLayoutDet.currentTwoPanels.left;
    taLayoutDet.currentTwoPanels = {
      left: taLayoutDet.currentTwoPanels.right,
      right: leftPanel
    };
    updateTwoPanelLayout();
  } else {
      alert("Exchange works only when there are two panels...");
  }
}

// Key handler / shorthand for toggling in between panels
registerKeyHandler({name: "Toggle Autograding Panel", code: "KeyA"}, function() {
  toggleAutograding();
  updateCookies();
});
registerKeyHandler({name: "Toggle Rubric Panel", code: "KeyG"}, function() {
  toggleRubric();
  updateCookies();
});
registerKeyHandler({name: "Toggle Submissions Panel", code: "KeyO"}, function() {
  toggleSubmissions();
  updateCookies();
});
registerKeyHandler({name: "Toggle Student Information Panel", code: "KeyS"}, function() {
  toggleInfo();
  updateCookies();
});
registerKeyHandler({name: "Toggle Grade Inquiry Panel", code: "KeyX"}, function() {
  toggleRegrade();
  updateCookies();
});
registerKeyHandler({name: "Toggle Discussion Panel", code: "KeyD"}, function() {
  toggleDiscussion();
  updateCookies();
});
registerKeyHandler({name: "Toggle Discussion Panel", code: "KeyP"}, function() {
  togglePeer();
  updateCookies();
});
//-----------------------------------------------------------------------------
// Show/hide components

registerKeyHandler({name: "Open Next Component", code: 'ArrowDown'}, function(e) {
  let openComponentId = getFirstOpenComponentId();
  let numComponents = getComponentCount();

  // Note: we use the 'toggle' functions instead of the 'open' functions
  //  Since the 'open' functions don't close any components
  if (isOverallCommentOpen()) {
    // Overall comment is open, so just close it
    closeOverallComment(true);
  }
  else if (openComponentId === NO_COMPONENT_ID) {
    // No component is open, so open the first one
    let componentId = getComponentIdByOrder(0);
    toggleComponent(componentId, true).then(function () {
      scrollToComponent(componentId);
    });
  }
  else if (openComponentId === getComponentIdByOrder(numComponents - 1)) {
    // Last component is open, so open the general comment
    toggleOverallComment(true).then(function () {
      scrollToOverallComment();
    });
  }
  else {
    // Any other case, open the next one
    let nextComponentId = getNextComponentId(openComponentId);
    toggleComponent(nextComponentId, true).then(function () {
      scrollToComponent(nextComponentId);
    });
  }
  e.preventDefault();
});

registerKeyHandler({name: "Open Previous Component", code: 'ArrowUp'}, function(e) {
  let openComponentId = getFirstOpenComponentId();
  let numComponents = getComponentCount();

  // Note: we use the 'toggle' functions instead of the 'open' functions
  //  Since the 'open' functions don't close any components
  if (isOverallCommentOpen()) {
    // Overall comment open, so open the last component
    let componentId = getComponentIdByOrder(numComponents - 1);
    toggleComponent(componentId, true).then(function () {
      scrollToComponent(componentId);
    });
  }
  else if (openComponentId === NO_COMPONENT_ID) {
    // No Component is open, so open the overall comment
    toggleOverallComment(true).then(function () {
      scrollToOverallComment();
    });
  }
  else if (openComponentId === getComponentIdByOrder(0)) {
    // First component is open, so close it
    closeAllComponents(true);
  }
  else {
    // Any other case, open the previous one
    let prevComponentId = getPrevComponentId(openComponentId);
    toggleComponent(prevComponentId, true).then(function () {
      scrollToComponent(prevComponentId);
    });
  }
  e.preventDefault();
});

//-----------------------------------------------------------------------------
// Misc rubric options
registerKeyHandler({name: "Toggle Rubric Edit Mode", code: "KeyE"}, function() {
  let editBox = $("#edit-mode-enabled");
  editBox.prop("checked", !editBox.prop("checked"));
  onToggleEditMode();
  updateCookies();
});

//-----------------------------------------------------------------------------
// Selecting marks

registerKeyHandler({name: "Select Full/No Credit Mark", code: 'Digit0', locked: true}, function() {
  checkOpenComponentMark(0);
});
registerKeyHandler({name: "Select Mark 1", code: 'Digit1', locked: true}, function() {
  checkOpenComponentMark(1);
});
registerKeyHandler({name: "Select Mark 2", code: 'Digit2', locked: true}, function() {
  checkOpenComponentMark(2);
});
registerKeyHandler({name: "Select Mark 3", code: 'Digit3', locked: true}, function() {
  checkOpenComponentMark(3);
});
registerKeyHandler({name: "Select Mark 4", code: 'Digit4', locked: true}, function() {
  checkOpenComponentMark(4);
});
registerKeyHandler({name: "Select Mark 5", code: 'Digit5', locked: true}, function() {
  checkOpenComponentMark(5);
});
registerKeyHandler({name: "Select Mark 6", code: 'Digit6', locked: true}, function() {
  checkOpenComponentMark(6);
});
registerKeyHandler({name: "Select Mark 7", code: 'Digit7', locked: true}, function() {
  checkOpenComponentMark(7);
});
registerKeyHandler({name: "Select Mark 8", code: 'Digit8', locked: true}, function() {
  checkOpenComponentMark(8);
});
registerKeyHandler({name: "Select Mark 9", code: 'Digit9', locked: true}, function() {
  checkOpenComponentMark(9);
});

function checkOpenComponentMark(index) {
  let component_id = getFirstOpenComponentId();
  if (component_id !== NO_COMPONENT_ID) {
    let mark_id = getMarkIdFromOrder(component_id, index);
    //TODO: Custom mark id is zero as well, should use something unique
    if (mark_id === CUSTOM_MARK_ID || mark_id === 0) {
      return;
    }
    toggleCommonMark(component_id, mark_id)
      .catch(function (err) {
        console.error(err);
        alert('Error toggling mark! ' + err.message);
      });
  }
}


// expand all files in Submissions and Results section
function openAll(click_class, class_modifier) {
  $("."+click_class + class_modifier).each(function(){
    // Check that the file is not a PDF before clicking on it
    let innerText = Object.values($(this))[0].innerText;
    if (innerText.slice(-4) !== ".pdf") {
      $(this).click();
    }
  });
}
function updateValue(obj, option1, option2) {
  // Switches the value of an element between option 1 and two
  obj.text(function(i, oldText){
    if(oldText.indexOf(option1) >= 0){
      newText = oldText.replace(option1, option2);
    }
    else {
      newText = oldText.replace(option2, option1);
    }
    return newText;
  });

}
function openAutoGrading(num){
  $('#tc_' + num).click();
  if($('#testcase_' + num)[0]!=null){
    $('#testcase_' + num)[0].style.display="block";
  }
}
// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
  // show all divs whose id starts with testcase_
  let clickable_divs  = $("[id^='tc_']");

  for(let i = 0; i < clickable_divs.length; i++){
    let clickable_div = clickable_divs[i];
    let num = clickable_div.id.split("_")[1];
    let content_div = $('#testcase_' + num);
    if(content_div.css("display") == "none"){
      clickable_div.click();
    }
  }
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
  // hide all divs whose id starts with testcase_
  $("[id^='testcase_']").hide();
  $("[id^='details_tc_']").find("span").hide();
  $("[id^='details_tc_']").find(".loading-tools-show").show();
}


function openDiv(num) {
  let elem = $('#div_viewer_' + num);
  if (elem.hasClass('open')) {
    elem.hide();
    elem.removeClass('open');
    $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
  }
  else {
    elem.show();
    elem.addClass('open');
    $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
  }
  return false;
}

// delta in this function is the incremental step of points, currently hardcoded to 0.5pts
function validateInput(id, question_total, delta){
  let ele = $('#' + id);
  if(isNaN(parseFloat(ele.val())) || ele.val() == ""){
    ele.val("");
    return;
  }
  if(ele.val() < 0 && parseFloat(question_total) > 0) {
    ele.val( 0 );
  }
  if(ele.val() > 0 && parseFloat(question_total) < 0) {
    ele.val( 0 );
  }
  if(ele.val() < parseFloat(question_total) && parseFloat(question_total) < 0) {
    ele.val(question_total);
  }
  if(ele.val() > parseFloat(question_total) && parseFloat(question_total) > 0) {
    ele.val(question_total);
  }
  if(ele.val() % delta != 0) {
    ele.val( Math.round(ele.val() / delta) * delta );
  }
}

// autoresize the comment box
function autoResizeComment(e){
  e.target.style.height ="";
  e.target.style.height = e.target.scrollHeight + "px";
}

function hideIfEmpty(element) {
  $(element).each(function() {
    if ($(this).hasClass("empty")) {
      $(this).hide();
    }
  });
}

function findOpenTestcases() {
  let testcase_num = [];
  let current_testcase;
  $(".box").each(function() {
    current_testcase = $(this).find('div[id^=testcase_]');
    if (typeof current_testcase[0] !== 'undefined'){
      if (current_testcase[0].style.display != 'none' ) {
        testcase_num.push(parseInt(current_testcase.attr('id').split('_')[1]));
      }
    }
  });
  return testcase_num;
}

//finds all the open files and folder and stores them in stored_paths
function findAllOpenedFiles(elem, current_path, path, stored_paths, first) {
  if (first === true) {
    current_path += path;
    if ($(elem)[0].classList.contains('open')) {
      stored_paths.push(path);
    }
    else {
      return [];
    }
  }
  else {
    current_path += "#$SPLIT#$" + path;
  }

  $(elem).children().each(function() {
    $(this).children('div[id^=file_viewer_]').each(function() {
      if ($(this)[0].classList.contains('shown')) {
        stored_paths.push((current_path + "#$SPLIT#$" + $(this)[0].dataset.file_name));
      }
    });

  });

  $(elem).children().each(function() {
    $(this).children('div[id^=div_viewer_]').each(function() {
      if ($(this)[0].classList.contains('open')) {
        stored_paths.push((current_path + "#$SPLIT#$" + $(this)[0].dataset.file_name));
        stored_paths = findAllOpenedFiles($(this), current_path, $(this)[0].dataset.file_name, stored_paths, false);
      }
    });
  });

  return stored_paths;
}

// Returns Non anonymized path for the submitted files by student
function getNonAnonPath(path, anon_submitter_id, user_ids){
  let nonAnonPath = "";
  let pathPieces = path.split("/");
  for (i = 1; i < pathPieces.length; i++) {
    // for non-anonymized-file-path, get the user-name from anon_submitter_id (if anonymized)
    if(i === 9){
      nonAnonPath += "/" + user_ids[anon_submitter_id];
    }
    else{
      nonAnonPath += "/" + pathPieces[i];
    }
  }
  return nonAnonPath;
}

function changeCurrentPeer(){
  let peer = $('#edit-peer-select').val();
  $('.edit-peer-components-block').hide();
  $('#edit-peer-components-form-'+peer).show();
}

function clearPeerMarks(submitter_id, gradeable_id, csrf_token){
  let peer_id = $("#edit-peer-select").val();
  let url = buildCourseUrl(['gradeable', gradeable_id, 'grading', 'clear_peer_marks']);
  $.ajax({
    url,
    data: {
      csrf_token,
      peer_id,
      submitter_id
    },
    type: "POST",
    success: function(data) {
      console.log("Successfully deleted peer marks");
      window.location.reload(true);
    },
    error: function(e) {
      console.log("Failed to delete");
    }
  });
}

function newEditPeerComponentsForm() {
  $('.popup-form').css('display', 'none');
  let form = $("#edit-peer-components-form");
  form.css("display", "block");
  captureTabInModal("edit-peer-components-form");
}
