/* global markerjs3, markerjsUI, $, csrfToken */
/* exported initImageAnnotation, addAnnotations, saveAnnotations, clearAnnotations, viewAllAnnotations, downloadImage, cleanupAnnotationEditor */

var currentAnnotations = null;
var annotatedImage = null; // Store the annotated image data URL
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
                annotatedImage.src = event.detail.dataUrl; // Capture the annotated image data URL
                $("#annotation-status").text("Annotations modified (not saved)").css("color", "orange");
                
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
        
        // Restore original image if it was replaced with MarkerView
        const markerView = document.getElementById('annotation-marker-view');
        if (markerView && markerView.targetImage) {
            const originalImg = markerView.targetImage;
            
            // Restore original properties
            if (originalImg.dataset.originalId) {
                originalImg.id = originalImg.dataset.originalId;
                originalImg.className = originalImg.dataset.originalClass || '';
                
                // Restore original size properties
                if (originalImg.dataset.originalWidth) {
                    originalImg.style.width = originalImg.dataset.originalWidth;
                }
                if (originalImg.dataset.originalHeight) {
                    originalImg.style.height = originalImg.dataset.originalHeight;
                }
            }
            
            // Replace MarkerView back with original image
            markerView.parentElement.replaceChild(originalImg, markerView);
            
            // Update global reference
            targetImg = originalImg;
            annotatedImage = targetImg;
        }
        
        $("#annotation-status").text("Annotations cleared (not saved)").css("color", "red");
    }
}

function downloadImage() {
    if (!targetImg) {
        console.error("No image available for download");
        alert("Error: No image available for download");
        return;
    }

    if (annotatedImage && currentAnnotations && currentAnnotations.markers && currentAnnotations.markers.length > 0) {
        // Create download link with the pre-generated annotated image
        const link = document.createElement('a');
        link.href = annotatedImage.src;
        
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
        link.href = actualImg.src;
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
    
    // Restore original image if it was replaced with MarkerView
    const markerView = document.getElementById('annotation-marker-view');
    if (markerView && markerView.targetImage) {
        const originalImg = markerView.targetImage;
        
        // Restore original properties
        if (originalImg.dataset.originalId) {
            originalImg.id = originalImg.dataset.originalId;
            originalImg.className = originalImg.dataset.originalClass || '';
            
            // Restore original size properties
            if (originalImg.dataset.originalWidth) {
                originalImg.style.width = originalImg.dataset.originalWidth;
            }
            if (originalImg.dataset.originalHeight) {
                originalImg.style.height = originalImg.dataset.originalHeight;
            }
        }
        
        // Replace MarkerView back with original image
        markerView.parentElement.replaceChild(originalImg, markerView);
        
        // Update global reference
        targetImg = originalImg;
    }
}

// Make cleanup function available globally
window.cleanupAnnotationEditor = cleanupAnnotationEditor;

function renderAnnotationsOnImage() {
    if (!targetImg) {
        console.error("No target image provided to renderAnnotationsOnImage");
        $("#annotation-status").text("Error: No image available for rendering").css("color", "red");
        return;
    }
    
    if (!currentAnnotations) {
        return;
    }
    
    try {
        if (typeof markerjs3 === 'undefined' || !markerjs3.MarkerView) {
            console.error("markerjs3 MarkerView is not available");
            $("#annotation-status").text("Error: MarkerView not available").css("color", "red");
            return;
        }
        
        // Check if we already replaced the image with MarkerView
        const existingMarkerView = document.getElementById('annotation-marker-view');
        if (existingMarkerView) {
            return;
        }
        
        // Store original image properties before replacing
        if (!targetImg.dataset.originalId) {
            targetImg.dataset.originalId = targetImg.id;
            targetImg.dataset.originalSrc = targetImg.src;
            targetImg.dataset.originalAlt = targetImg.alt;
            targetImg.dataset.originalClass = targetImg.className;
            targetImg.dataset.originalWidth = targetImg.style.width || '';
            targetImg.dataset.originalHeight = targetImg.style.height || '';
        }
        
        // Create MarkerView instance
        const markerView = new markerjs3.MarkerView();
        markerView.id = 'annotation-marker-view';
        markerView.className = targetImg.className; // Copy original classes
        markerView.targetImage = targetImg;
        
        // Get computed style dimensions to ensure proper scaling
        const computedStyle = getComputedStyle(targetImg);
        const displayWidth = parseInt(computedStyle.width) || targetImg.width || targetImg.naturalWidth;
        const displayHeight = parseInt(computedStyle.height) || targetImg.height || targetImg.naturalHeight;
        
        // Set target dimensions to match the displayed image size
        markerView.targetWidth = displayWidth;
        markerView.targetHeight = displayHeight;
        
        // Set zoom level to 1 to show image at natural scale within the target dimensions
        markerView.zoomLevel = 1;
        
        // Apply the original image's size to MarkerView container
        markerView.style.width = displayWidth + 'px';
        markerView.style.height = displayHeight + 'px';
        
        // Copy any additional inline styles for width/height if they exist
        if (targetImg.style.width) {
            markerView.style.width = targetImg.style.width;
        }
        if (targetImg.style.height) {
            markerView.style.height = targetImg.style.height;
        }
        
        // Replace the image with MarkerView
        targetImg.parentElement.replaceChild(markerView, targetImg);
        
        // Show the annotations
        markerView.show(currentAnnotations);
        
        $("#annotation-status").text("Annotations rendered").css("color", "green");
        
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
        // Reset annotation data for new image
        if (annotatedImage === null) {
            annotatedImage = targetImg;
        }

        // Handle image load errors
        if (targetImg) {
            targetImg.onerror = function() {
                console.error('Failed to load image:', fPath);
                $("#image-error-message").text("Error loading image: " + fname).show();
            };
        }
        
        // Load existing annotations if available
        if (existingAnnotations && existingAnnotations.markers) {    
            currentAnnotations = existingAnnotations;
        }
        
        // Wait for image to load before rendering annotations
        if (targetImg && (targetImg.complete && targetImg.naturalHeight !== 0)) {
            // Image is already loaded
            renderAnnotationsOnImage(targetImg);
        } else if (targetImg) {
            // Wait for image to load
            const imageLoadHandler = function() {
                targetImg.removeEventListener('load', imageLoadHandler);
                renderAnnotationsOnImage(targetImg);
            };
            targetImg.addEventListener('load', imageLoadHandler);
        }
    });
}
