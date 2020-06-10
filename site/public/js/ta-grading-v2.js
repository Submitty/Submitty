//Used to reset users cookies
let cookie_version = 1;

// Panel elements info to be used for layout designs
let panelElements = [
  { str: "autograding_results", icon: ".grading_toolbar .fa-list"},
  { str: "grading_rubric", icon: ".grading_toolbar .fa-edit"},
  { str: "submission_browser", icon: "grading_toolbar .fa-folder-open.icon-header"},
  { str: "student_info", icon: ".grading_toolbar .fa-user"},
  { str: "regrade_info", icon: ".grading_toolbar .grade_inquiry_icon"},
  { str: "discussion_browser", icon: ".grading_toolbar .fa-comment-alt"},
  { str: "peer_info", icon: ".grading_toolbar .fa-users"}
];

let currentOpenPanel = null;
let currentTwoPanels = {
  left: null,
  right: null
};

let isTwoPanelsEnabled = false; // update this with localstorage for persistence ?

//Check if cookie version is/is not the same as the current version
let versionMatch = false;
//Set positions and visibility of configurable ui elements
$(function() {
  //bring regrade panel to the front if grade inquiry is pending
  if ($(".fa-exclamation")[0]) {
    if (!isRegradeVisible())
      toggleRegrade();
    $('#regrade_info').css({'z-index':'40'});
  }
  updateCookies();
  let progressbar = $(".progressbar"),
    value = progressbar.val();
  $(".progress-value").html("<b>" + value + '%</b>');
});

function createCookie(name,value,seconds)  {
  let expires = "";
  if(seconds) {
    let date = new Date();
    date.setTime(date.getTime()+(seconds*1000));
    expires = "; expires="+date.toGMTString();
  }

  document.cookie = name+"="+value+expires+"; path=/";
}

function eraseCookie(name) {
  createCookie(name,"",-3600);
}

function deleteCookies(){
  $.each(document.cookie.split(/; */), function(){
    let cookie = this.split("=");
    if(!cookie[1] || cookie[1] == 'undefined'){
      document.cookie = cookie[0] + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
      document.cookie = "cookie_version=-1; path=/;";
    }
  });
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

function setTwoPanelModeVisibilities () {
    panelElements.forEach((panel) => {
      if (currentTwoPanels.left === panel.str || currentTwoPanels.right === panel.str) {
        $("#" + panel.str).toggle(true);
        $(panel.icon).toggleClass('icon-selected', true);
        $("#" + panel.str + "_btn").toggleClass('active', true);
        console.log(panel.str);
      } else {
        $("#" + panel.str).toggle(false);
        $(panel.icon).toggleClass('icon-selected', false);
        $("#" + panel.str + "_btn").toggleClass('active', false);
      }
    })
}

function setPanelsVisiblilities (ele) {
  panelElements.forEach((panel) => {
    //only hide those panels which are not given panel and not in recentTwoPanel array
    if (panel.str !== ele && currentTwoPanels.right !== panel.str && currentTwoPanels.left !== panel.str) {
      $("#" + panel.str).hide();
      $(panel.icon).removeClass('icon-selected');
      $("#" + panel.str + "_btn").removeClass('active');
    } else if (panel.str === ele) {
      const eleVisibility = !$("#" + panel.str).is(":visible");
      $("#" + panel.str).toggle(eleVisibility);
      $(panel.icon).toggleClass('icon-selected', eleVisibility);
      $("#" + panel.str + "_btn").toggleClass('active', eleVisibility);
      debugger;
      if (isTwoPanelsEnabled) {
        if (eleVisibility) {
          // panel is going to be added on the screen
          if ((currentTwoPanels.left && currentTwoPanels.right) || !currentTwoPanels.left && currentTwoPanels.right) {
            currentTwoPanels.left = currentTwoPanels.right;
            currentTwoPanels.right = panel.str;
          } else if (currentTwoPanels.left) {
            currentTwoPanels.right = panel.str;
          } else {
            currentTwoPanels.left = panel.str;
          }
        } else {
          // panel is going to be removed from screen
          // check one out of the left and right is going to be hidden
          let positionOfPanel = undefined;
          if (currentTwoPanels.left === panel.str ) {
            positionOfPanel = "left" ;
          }
          if (currentTwoPanels.right === panel.str ) {
            positionOfPanel = "right" ;
          }

          if (!positionOfPanel) {
            // How come this happen ???
          } else if (positionOfPanel === "left") {
            currentTwoPanels.left = currentTwoPanels.right;
            currentTwoPanels.right = null;
          } else {
            currentTwoPanels.right = null;
          }
        }
      }
    }
  });

  if (isTwoPanelsEnabled) {
    updateTwoPanelLayout();
  }
}

function toggleAutograding() {
  setPanelsVisiblilities("autograding_results");
}

function toggleRubric() {
  setPanelsVisiblilities("grading_rubric");
}

function toggleSubmissions() {
  setPanelsVisiblilities("submission_browser");
}

function toggleInfo() {
  setPanelsVisiblilities("student_info");
}
function toggleRegrade() {
  setPanelsVisiblilities("regrade_info");
}

function toggleDiscussion() {
  setPanelsVisiblilities("discussion_browser");
}

function togglePeer() {
  setPanelsVisiblilities("peer_info");
}

function toggleFullScreenMode () {
  $("main#main").toggleClass('full-screen-mode');
  $("#fullscreen-btn-cont").toggleClass('active');
}

function toggleTwoPanelMode() {
  const twoPanelCont = $('.two-panel-cont');
  isTwoPanelsEnabled = !twoPanelCont.is(":visible");

  if (isTwoPanelsEnabled) {
    twoPanelCont.addClass("active");
    // if there is no recently opened panels fill it with the first two
    if (!currentTwoPanels.left && !currentTwoPanels.right) {
      console.log("There is nothing in here");
      currentTwoPanels = {
        left: panelElements[0].str,
        right: panelElements[1].str
      };
    } else if (!currentTwoPanels.right) {
      panelElements.every((panel, idx) => {
        if (currentTwoPanels.left === panel.str) {
          let nextIdx = (idx + 1) === panelElements.length ? 0 : idx + 1;
          currentTwoPanels.right = panelElements[nextIdx].str;
          return false;
        }
        return true;
      });
    }
    updateTwoPanelLayout();
    $("#two-panel-mode-btn").addClass("active");
  } else {
    twoPanelCont.removeClass("active");
    if ((currentTwoPanels.left && currentTwoPanels.right) || (!currentTwoPanels.left && currentTwoPanels.right)){
      document.querySelector('.panels-container').append(document.getElementById(currentTwoPanels.right));
    } else if (currentTwoPanels.left) {
      document.querySelector('.panels-container').append(document.getElementById(currentTwoPanels.left));
    }
    isTwoPanelsEnabled = false;
    currentTwoPanels = {
      left: null,
      right: null
    };
    $("#two-panel-mode-btn").removeClass("active");
  }
}

function updateTwoPanelLayout() {
  // fetch the panels by their ids
  const leftPanel = document.getElementById(currentTwoPanels.left);
  const rightPanel = document.getElementById(currentTwoPanels.right);

  setTwoPanelModeVisibilities();

  document.querySelector(".two-panel-cont .two-panel-left").appendChild(leftPanel);
  document.querySelector(".two-panel-cont .two-panel-right").appendChild(rightPanel);
}

/*

  Use a parent div with classes left and right
  but in this case if you would toggle one of the panel how to maintain the position of other one

  Use a predefined left and right divs and make them visible only in two panel mode
  how to inject panels in these divs
 */

function resetModules() {
  deleteCookies();
  updateCookies();
}

registerKeyHandler({name: "Reset Panel Positions", code: "KeyR"}, function() {
  resetModules();
  updateCookies();
});
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
