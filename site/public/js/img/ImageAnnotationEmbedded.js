/* global markerjsUI, $, csrfToken */
/* exported initImageAnnotation, addAnnotations, saveAnnotations, clearAnnotations, viewAllAnnotations, downloadImage */

let currentAnnotations = null;
let targetImg = null;
let gradeableId = '';
let userId = '';
let graderId = '';
let filename = '';
let filePath = '';
let csrfToken = '';
let isStudent = false;

function buildCourseUrl(parts = []) {
    return `${document.body.dataset.courseUrl}/${parts.join('/')}`;
}

function addAnnotations() {
    if (targetImg && targetImg.complete) {
        try {
            // Check if markerjsUI is available
            if (typeof markerjsUI === 'undefined' || !markerjsUI.AnnotationEditor) {
                console.error("markerjsUI AnnotationEditor is not loaded");
                $("#annotation-status").text("Error: MarkerJS-UI library not loaded").css("color", "red");
                return;
            }
            
            // Create annotation editor using the markerjs-ui AnnotationEditor class
            const annotationEditor = new markerjsUI.AnnotationEditor();
            annotationEditor.targetImage = targetImg;
            
            // Set up event handlers for the annotation editor
            annotationEditor.addEventListener('editorsave', function(event) {
                currentAnnotations = event.detail.state;
                if (event.detail.dataUrl) {
                    targetImg.src = event.detail.dataUrl;
                }
                $("#annotation-status").text("Annotations modified (not saved)").css("color", "orange");
                console.log("Annotations saved with editor");
                
                // Remove the annotation editor from the DOM
                const editorWrapper = document.getElementById('annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.remove();
                }
            });
            
            annotationEditor.addEventListener('editorclose', function(event) {
                // Remove the annotation editor from the DOM
                const editorWrapper = document.getElementById('annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.remove();
                }
                $("#annotation-status").text("Annotation editor closed").css("color", "blue");
            });
            
            // Load existing annotations if available
            if (currentAnnotations) {
                annotationEditor.restoreState(currentAnnotations);
            }
            
            // Configure editor settings
            annotationEditor.settings = {
                renderOnSave: true,
                rendererSettings: {
                    naturalSize: false,
                    imageType: "image/png",
                    imageQuality: 1,
                    markersOnly: false
                }
            };
            
            // Add the annotation editor to the page
            const editorWrapper = document.createElement('div');
            editorWrapper.id = 'annotation-editor-wrapper';
            editorWrapper.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                z-index: 10000;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                justify-content: center;
                align-items: center;
            `;
            
            annotationEditor.style.cssText = `
                width: 90vw;
                height: 90vh;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            `;
            
            editorWrapper.appendChild(annotationEditor);
            document.body.appendChild(editorWrapper);
            
            console.log("MarkerJS-UI annotation editor created and configured");
            $("#annotation-status").text("Annotation editor opened").css("color", "blue");
            
        } catch (error) {
            console.error("Error opening annotation editor:", error);
            $("#annotation-status").text("Error opening annotation editor: " + error.message).css("color", "red");
        }
    }
}

function saveAnnotations() {
    if (currentAnnotations) {
        const annotationData = {
            user_id: userId,
            grader_id: graderId,
            filename: filename,
            file_path: filePath,
            annotations: JSON.stringify(currentAnnotations),
            csrf_token: csrfToken
        };
        
        $.ajax({
            url: buildCourseUrl(['gradeable', gradeableId, 'img', 'annotations']),
            type: "POST",
            data: annotationData,
            dataType: "json",
            success: function(response) {
                if (response.status === "success") {
                    $("#annotation-status").text("Annotations saved successfully!").css("color", "green");
                } else {
                    $("#annotation-status").text("Error saving annotations: " + (response.message || "Unknown error")).css("color", "red");
                }
            },
            error: function(xhr, status, error) {
                $("#annotation-status").text("Error saving annotations: " + error).css("color", "red");
            }
        });
    } else {
        alert("No annotations to save. Please create annotations first.");
    }
}

function clearAnnotations() {
    if (confirm("Are you sure you want to clear all annotations?")) {
        currentAnnotations = null;
        
        // Close any open annotation editor
        const editorWrapper = document.getElementById('annotation-editor-wrapper');
        if (editorWrapper) {
            editorWrapper.remove();
        }
        
        // Reset image to original
        const originalSrc = targetImg.src.split("?")[0] + "?" + targetImg.src.split("?")[1];
        targetImg.src = originalSrc;
        $("#annotation-status").text("Annotations cleared (not saved)").css("color", "red");
    }
}

function viewAllAnnotations() {
    const allAnnotations = JSON.parse($("#all-annotations").val() || "{}");
    showAllAnnotations(allAnnotations);
}

function downloadImage() {
    const link = document.createElement('a');
    link.href = targetImg.src;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function renderAnnotationsOnImage() {
    if (currentAnnotations && targetImg) {
        try {
            // Check if markerjsUI is available
            if (typeof markerjsUI === 'undefined' || !markerjsUI.AnnotationViewer) {
                console.error("markerjsUI AnnotationViewer is not available");
                $("#annotation-status").text("Error: Annotation viewer not available").css("color", "red");
                return;
            }
            
            // Create annotation viewer using the markerjs-ui AnnotationViewer class
            const annotationViewer = new markerjsUI.AnnotationViewer();
            annotationViewer.targetImage = targetImg;
            
            // Display the annotations in the viewer
            annotationViewer.show(currentAnnotations);
            
            // Replace the image container content temporarily
            const imageContainer = targetImg.parentElement;
            if (imageContainer) {
                // Store original content
                const originalContent = imageContainer.innerHTML;
                
                // Replace with viewer
                imageContainer.innerHTML = '';
                annotationViewer.style.width = '100%';
                annotationViewer.style.height = '400px'; // Adjust as needed
                imageContainer.appendChild(annotationViewer);
                
                // Store reference to restore later if needed
                targetImg._originalContainer = originalContent;
            }
            
            $("#annotation-status").text("Annotations loaded").css("color", "green");
            
        } catch (error) {
            console.error("Error creating annotation viewer:", error);
            $("#annotation-status").text("Error creating annotation viewer: " + error.message).css("color", "red");
        }
    }
}

function showAllAnnotations(allAnnotations) {
    let html = '<div style="padding: 10px;"><h4>All Annotations for this Image:</h4>';
    
    if (Object.keys(allAnnotations).length === 0) {
        html += '<p>No annotations found for this image.</p>';
    } else {
        for (const [grader, annotations] of Object.entries(allAnnotations)) {
            html += '<div style="margin-bottom: 10px; padding: 5px; border: 1px solid #ccc;"><strong>Grader: ' + grader + '</strong></div>';
        }
    }
    
    html += '</div>';
    
    // Create a modal or popup to show all annotations
    if ($("#annotation-modal").length === 0) {
        $("body").append('<div id="annotation-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;"><div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; max-width: 80%; max-height: 80%; overflow: auto;"><button id="close-annotation-modal" style="float: right; margin-bottom: 10px;">&times;</button><div id="annotation-modal-content"></div></div></div>');
        
        $("#close-annotation-modal").on("click", function() {
            $("#annotation-modal").hide();
        });
    }
    
    $("#annotation-modal-content").html(html);
    $("#annotation-modal").show();
}

function initImageAnnotation() {
    // Initialize variables from DOM
    targetImg = document.getElementById("annotatable-image");
    gradeableId = $("#gradeable-id").val();
    userId = $("#user-id").val();
    graderId = $("#grader-id").val();
    filename = $("#filename").val();
    filePath = $("#file-path").val();
    csrfToken = $("#csrf-token").val();
    isStudent = $("#is-student").val() === 'true';
    
    // Check if markerjsUI is available
    console.log("markerjsUI available:", typeof markerjsUI);
    if (typeof markerjsUI !== 'undefined') {
        console.log("markerjsUI.AnnotationEditor available:", typeof markerjsUI.AnnotationEditor);
        console.log("markerjsUI.AnnotationViewer available:", typeof markerjsUI.AnnotationViewer);
    } else {
        console.error("markerjsUI is not defined - ensure markerjs3.js and markerjs-ui.umd.cjs are loaded in correct order");
    }
    
    // Load existing annotations
    const existingAnnotations = $("#existing-annotations").val();
    if (existingAnnotations && existingAnnotations !== "[]") {
        try {
            currentAnnotations = JSON.parse(existingAnnotations);
            renderAnnotationsOnImage();
        } catch (e) {
            console.error("Error loading existing annotations:", e);
        }
    }
    
    // Update the add annotations button to open the markerjs-ui editor
    $("#add-annotations-btn").off('click').on("click", addAnnotations);
    
    // Bind other event handlers
    $("#save-annotations-btn").on("click", saveAnnotations);
    $("#clear-annotations-btn").on("click", clearAnnotations);
    $("#view-all-annotations-btn").on("click", viewAllAnnotations);
    $("#download-image-btn").on("click", downloadImage);
    
    // Handle image load errors
    if (targetImg) {
        targetImg.onerror = function() {
            console.error('Failed to load image:', filePath);
            $("#image-error-message").text("Error loading image: " + filename).show();
        };
    }
}

// Initialize when document is ready
$(document).ready(function() {
    initImageAnnotation();
});
