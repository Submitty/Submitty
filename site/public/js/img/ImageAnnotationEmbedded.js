/* global markerjs3, markerjsUI, $, csrfToken */
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
    if (!targetImg) {
        console.error("No target image provided to addAnnotations");
        $("#annotation-status").text("Error: No image available for annotation").css("color", "red");
        return;
    }
    
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
                $("#annotation-status").text("Annotations modified (not saved)").css("color", "orange");
                console.log("Annotations saved with editor");
                
                // Hide the annotation editor instead of removing it
                const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.style.display = 'none';
                }
                
                // Render the annotations on the image
                renderAnnotationsOnImage(targetImg);
            });
            
            globalAnnotationEditor.addEventListener('editorclose', function(event) {
                // Hide the annotation editor instead of removing it
                const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.style.display = 'none';
                }
                $("#annotation-status").text("Annotation editor closed").css("color", "blue");
                
                // Render the annotations on the image if any exist
                if (currentAnnotations) {
                    renderAnnotationsOnImage(targetImg);
                }
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
        currentAnnotations = [];
        
        // Hide any open annotation editor instead of removing it
        const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
        if (editorWrapper) {
            editorWrapper.style.display = 'none';
        }
        
        // Remove annotation overlay
        const annotationOverlay = document.getElementById('annotation-overlay');
        if (annotationOverlay) {
            annotationOverlay.remove();
        }
        
        $("#annotation-status").text("Annotations cleared (not saved)").css("color", "red");
    }
}

function downloadImage(targetImg) {
    if (!targetImg) {
        console.error("No target image provided to downloadImage");
        alert("Error: No image available for download");
        return;
    }
    
    // Check if there's an annotation overlay
    const annotationOverlay = document.getElementById('annotation-overlay');
    
    if (annotationOverlay) {
        // If there are annotations, create a composite image for download
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = targetImg.naturalWidth;
        canvas.height = targetImg.naturalHeight;
        
        // Draw the original image first
        ctx.drawImage(targetImg, 0, 0);
        
        // Draw the annotations on top
        ctx.drawImage(annotationOverlay, 0, 0);
        
        // Create download link with composite image
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        
        // Add suffix to filename for annotated version
        const nameParts = filename.split('.');
        let downloadFilename;
        if (nameParts.length > 1) {
            const extension = nameParts.pop();
            downloadFilename = nameParts.join('.') + '_annotated.' + extension;
        } else {
            downloadFilename = filename + '_annotated';
        }
        
        link.download = downloadFilename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        // No annotations, download original image
        const link = document.createElement('a');
        link.href = targetImg.src;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function cleanupAnnotationEditor() {
    // Hide the global annotation editor when switching to a different image
    const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
    if (editorWrapper) {
        editorWrapper.style.display = 'none';
    }
    
    // Remove annotation overlay
    const annotationOverlay = document.getElementById('annotation-overlay');
    if (annotationOverlay) {
        annotationOverlay.remove();
    }
}

// Make cleanup function available globally
window.cleanupAnnotationEditor = cleanupAnnotationEditor;

function renderAnnotationsOnImage(targetImg) {
    if (!targetImg) {
        console.error("No target image provided to renderAnnotationsOnImage");
        $("#annotation-status").text("Error: No image available for rendering").css("color", "red");
        return;
    }
    
    if (!currentAnnotations) {
        console.log("No annotations to render");
        return;
    }
    
    console.log("Rendering annotations on image using Renderer");
    console.log("Current annotations:", currentAnnotations);
    console.log("Annotation structure:", {
        hasMarkers: currentAnnotations && currentAnnotations.markers,
        markersLength: currentAnnotations && currentAnnotations.markers ? currentAnnotations.markers.length : 0,
        annotationType: typeof currentAnnotations
    });
    
    try {
        
        // Create renderer and render annotations
        const renderer = new markerjs3.Renderer();
        renderer.targetImage = targetImg;
        renderer.naturalSize = true;
        renderer.markersOnly = true; // Only render annotations since we're overlaying
        renderer.imageType = 'image/png';
        renderer.imageQuality = 1;
        
        renderer.rasterize(currentAnnotations).then((dataUrl) => {

            // Remove any existing annotation overlay
            const existingOverlay = document.getElementById('annotation-overlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            // Create an overlay image element for the annotations
            const annotationOverlay = document.createElement('img');
            annotationOverlay.id = 'annotation-overlay';
            annotationOverlay.src = dataUrl;
            
            // Style the overlay to position it exactly over the original image
            annotationOverlay.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 10;
            `;
            
            // Ensure the target image container has relative positioning
            const container = targetImg.parentElement;
            if (container && window.getComputedStyle(container).position === 'static') {
                container.style.position = 'relative';
            }
            
            // Add the overlay after the target image
            targetImg.insertAdjacentElement('afterend', annotationOverlay);
            
            // Store reference for cleanup
            annotationOverlay._originalImage = targetImg;
            
            $("#annotation-status").text("Annotations rendered").css("color", "green");
            console.log("Annotations overlaid successfully");
            
        }).catch((error) => {
            console.error("Error rendering annotations:", error);
            $("#annotation-status").text("Error rendering annotations: " + error.message).css("color", "red");
        });
        
    } catch (error) {
        console.error("Error in renderAnnotationsOnImage:", error);
        $("#annotation-status").text("Error rendering annotations: " + error.message).css("color", "red");
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
        
        // Handle image load errors
        if (targetImg) {
            targetImg.onerror = function() {
                console.error('Failed to load image:', fPath);
                $("#image-error-message").text("Error loading image: " + fname).show();
            };
        }
        
        // Load existing annotations if available
        console.log(existingAnnotations);
        if (existingAnnotations && existingAnnotations.markers) {
            try {
                console.log('Loading current annotations');
                
                currentAnnotations = existingAnnotations;
                console.log(currentAnnotations);
                renderAnnotationsOnImage(targetImg);
            } catch (e) {
                console.error("Error loading existing annotations:", e);
            }
        }
        
        console.log("Image annotation system initialized with parameters");
    });
}
