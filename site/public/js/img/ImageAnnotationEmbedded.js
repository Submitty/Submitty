/* global markerjsUI, $, csrfToken */
/* exported initImageAnnotation, addAnnotations, saveAnnotations, clearAnnotations, viewAllAnnotations, downloadImage, cleanupAnnotationEditor */

var currentAnnotations = null;
var targetImg = null;
var gradeableId = '';
var userId = '';
var graderId = '';
var filename = '';
var filePath = '';
var csrfToken = '';
var isStudent = false;

// Global annotation editor instance - shared across all image annotations
var globalAnnotationEditor = null;

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
            
            // Check if we already have an annotation editor wrapper
            let editorWrapper = document.getElementById('global-annotation-editor-wrapper');
            
            if (editorWrapper) {
                // Editor already exists, just show it and update the target image
                editorWrapper.style.display = 'flex';
                
                if (globalAnnotationEditor) {
                    globalAnnotationEditor.targetImage = targetImg;
                    
                    // Load existing annotations if available
                    if (currentAnnotations) {
                        globalAnnotationEditor.restoreState(currentAnnotations);
                    } else {
                        // Clear previous annotations if no current ones
                        globalAnnotationEditor.restoreState({});
                    }
                }
                
                $("#annotation-status").text("Annotation editor opened").css("color", "blue");
                return;
            }
            
            // Create new annotation editor since none exists
            globalAnnotationEditor = new markerjsUI.AnnotationEditor();
            globalAnnotationEditor.targetImage = targetImg;
            
            // Set up event handlers for the annotation editor
            globalAnnotationEditor.addEventListener('editorsave', function(event) {
                currentAnnotations = event.detail.state;
                if (event.detail.dataUrl) {
                    targetImg.src = event.detail.dataUrl;
                }
                $("#annotation-status").text("Annotations modified (not saved)").css("color", "orange");
                console.log("Annotations saved with editor");
                
                // Hide the annotation editor instead of removing it
                const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.style.display = 'none';
                }
            });
            
            globalAnnotationEditor.addEventListener('editorclose', function(event) {
                // Hide the annotation editor instead of removing it
                const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.style.display = 'none';
                }
                $("#annotation-status").text("Annotation editor closed").css("color", "blue");
            });
            
            // Load existing annotations if available
            if (currentAnnotations) {
                globalAnnotationEditor.restoreState(currentAnnotations);
            }
            
            // Configure editor settings
            globalAnnotationEditor.settings = {
                renderOnSave: true,
                rendererSettings: {
                    naturalSize: false,
                    imageType: "image/png",
                    imageQuality: 1,
                    markersOnly: false
                }
            };
            
            // Add the annotation editor to the page
            editorWrapper = document.createElement('div');
            editorWrapper.id = 'global-annotation-editor-wrapper';
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
            
            globalAnnotationEditor.style.cssText = `
                width: 90vw;
                height: 90vh;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            `;
            
            editorWrapper.appendChild(globalAnnotationEditor);
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
        
        // Hide any open annotation editor instead of removing it
        const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
        if (editorWrapper) {
            editorWrapper.style.display = 'none';
        }
        
        // Reset image to original
        const originalSrc = targetImg.src.split("?")[0] + "?" + targetImg.src.split("?")[1];
        targetImg.src = originalSrc;
        $("#annotation-status").text("Annotations cleared (not saved)").css("color", "red");
    }
}

function downloadImage() {
    const link = document.createElement('a');
    link.href = targetImg.src;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function cleanupAnnotationEditor() {
    // Hide the global annotation editor when switching to a different image
    const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
    if (editorWrapper) {
        editorWrapper.style.display = 'none';
    }
}

// Make cleanup function available globally
window.cleanupAnnotationEditor = cleanupAnnotationEditor;

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

function initImageAnnotation(gId, uId, grId, fname, fPath, token, isStud, existingAnnotations) {
    // Clean up any existing annotation editor before initializing a new image
    cleanupAnnotationEditor();
    
    // Set global variables from parameters
    gradeableId = gId;
    userId = uId;
    graderId = grId;
    filename = fname;
    filePath = fPath;
    csrfToken = token;
    isStudent = isStud;
    
    // Wait for DOM to be ready
    $(document).ready(function() {
        // Get the target image element
        targetImg = document.getElementById("annotatable-image");
        
        // Check if markerjsUI is available
        console.log("markerjsUI available:", typeof markerjsUI);
        if (typeof markerjsUI !== 'undefined') {
            console.log("markerjsUI.AnnotationEditor available:", typeof markerjsUI.AnnotationEditor);
            console.log("markerjsUI.AnnotationViewer available:", typeof markerjsUI.AnnotationViewer);
        } else {
            console.error("markerjsUI is not defined - ensure markerjs3.js and markerjs-ui.umd.js are loaded in correct order");
        }
        
        // Handle image load errors
        if (targetImg) {
            targetImg.onerror = function() {
                console.error('Failed to load image:', fPath);
                $("#image-error-message").text("Error loading image: " + fname).show();
            };
        }
        
        // Load existing annotations if available
        if (existingAnnotations && existingAnnotations.length > 0) {
            try {
                currentAnnotations = existingAnnotations;
                renderAnnotationsOnImage();
            } catch (e) {
                console.error("Error loading existing annotations:", e);
            }
        }
        
        console.log("Image annotation system initialized with parameters");
    });
}
