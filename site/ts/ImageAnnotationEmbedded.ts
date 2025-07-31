// Import utility functions
import { buildCourseUrl } from './utils/server';
import type { AnnotationState } from '@markerjs/markerjs3';
import type { AnnotationEditor, AnnotationEditorSettings } from '@markerjs/markerjs-ui';

// Type declarations for external libraries
const markerjs3 = window.markerjs3;
const markerjsUI = window.markerjsUI;
const $ = window.$;
const csrfToken = window.csrfToken;
declare global {
    interface Window {
        initImageAnnotation(gId: string, uId: string, grId: string, fname: string, fPath: string, token: string, isStud: boolean, existingAnnotations?: AnnotationState): void;
        addAnnotations(): void;
        saveAnnotations(): void;
        clearAnnotations(): void;
        downloadImage(): void;
        cleanupAnnotationEditor(): void;
    }
}

// Interface for API response
interface ApiResponse {
    status: string;
    message?: string;
}

// Interface for annotation data
interface AnnotationData {
    user_id: string;
    grader_id: string;
    filename: string;
    file_path: string;
    annotations: string;
    csrf_token: string;
}

// Image annotation manager class to encapsulate all state
class AnnotationManager {
    currentAnnotations: AnnotationState | null;
    originalImg: HTMLImageElement | null;
    annotatedImageDataUrl: string | null;
    gradeableId: string;
    userId: string;
    graderId: string;
    filename: string;
    filePath: string;
    csrfToken: string;
    isStudent: boolean;
    globalAnnotationEditor: AnnotationEditor | null;

    constructor() {
        this.currentAnnotations = null;
        this.originalImg = null;
        this.annotatedImageDataUrl = null;
        this.gradeableId = '';
        this.userId = '';
        this.graderId = '';
        this.filename = '';
        this.filePath = '';
        this.csrfToken = '';
        this.isStudent = false;
        this.globalAnnotationEditor = null;
    }

    reset(): void {
        this.currentAnnotations = null;
        this.originalImg = null;
        this.annotatedImageDataUrl = null;
        this.gradeableId = '';
        this.userId = '';
        this.graderId = '';
        this.filename = '';
        this.filePath = '';
        this.csrfToken = '';
        this.isStudent = false;
    }
}

const emptyAnnotations: AnnotationState = {
    width: 600,
    height: 800,
    markers: [],
};

// Single instance to manage the current image annotation state
const annotationManager: AnnotationManager = new AnnotationManager();

function addAnnotations(): void {
    if (!annotationManager.originalImg) {
        console.error('No target image provided to addAnnotations');
        $('#annotation-status').text('Error: No image available for annotation').css('color', 'red');
        return;
    }

    if (annotationManager.originalImg && annotationManager.originalImg.complete) {
        try {
            // Check if markerjsUI is available
            if (typeof markerjsUI === 'undefined' || !markerjsUI.AnnotationEditor) {
                console.error('markerjsUI AnnotationEditor is not loaded');
                $('#annotation-status').text('Error: MarkerJS-UI library not loaded').css('color', 'red');
                return;
            }

            // Check if we already have an annotation editor wrapper
            let editorWrapper: HTMLElement | null = document.getElementById('global-annotation-editor-wrapper');

            if (editorWrapper) {
                // Editor already exists, just show it and update the target image
                editorWrapper.style.display = 'flex';

                if (annotationManager.globalAnnotationEditor) {
                    annotationManager.globalAnnotationEditor.targetImage = annotationManager.originalImg;

                    // Load existing annotations if available
                    if (annotationManager.currentAnnotations) {
                        annotationManager.globalAnnotationEditor.restoreState(annotationManager.currentAnnotations);
                    }
                    else {
                        // Clear previous annotations if no current ones
                        emptyAnnotations.width = annotationManager.originalImg.naturalWidth || 600;
                        emptyAnnotations.height = annotationManager.originalImg.naturalHeight || 800;
                        annotationManager.globalAnnotationEditor.restoreState(emptyAnnotations);
                    }
                }

                $('#annotation-status').text('Annotation editor opened').css('color', 'blue');
                return;
            }

            // Create new annotation editor since none exists
            annotationManager.globalAnnotationEditor = new markerjsUI.AnnotationEditor();
            annotationManager.globalAnnotationEditor.targetImage = annotationManager.originalImg;

            // Set up event handlers for the annotation editor
            annotationManager.globalAnnotationEditor.addEventListener('editorsave', (event: any) => {
                annotationManager.currentAnnotations = event.detail.state;
                annotationManager.annotatedImageDataUrl = event.detail.dataUrl;
                $('#annotation-status').text('Annotations modified (not saved)').css('color', 'orange');

                // Hide the annotation editor instead of removing it
                const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.style.display = 'none';
                }

                // Render the annotations on the image
                renderAnnotationsOnImage();
            });

            annotationManager.globalAnnotationEditor.addEventListener('editorclose', (event: any) => {
                // Hide the annotation editor instead of removing it
                const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
                if (editorWrapper) {
                    editorWrapper.style.display = 'none';
                }
                $('#annotation-status').text('Annotation editor closed').css('color', 'blue');

                // Render the annotations on the image if any exist
                if (annotationManager.currentAnnotations) {
                    renderAnnotationsOnImage();
                }
            });

            // Load existing annotations if available
            if (annotationManager.currentAnnotations) {
                annotationManager.globalAnnotationEditor.restoreState(annotationManager.currentAnnotations);
            }

            // Configure editor settings
            annotationManager.globalAnnotationEditor.settings.renderOnSave = true;
            annotationManager.globalAnnotationEditor.settings.rendererSettings.naturalSize = false;
            annotationManager.globalAnnotationEditor.settings.rendererSettings.imageType = 'image/png';
            annotationManager.globalAnnotationEditor.settings.rendererSettings.imageQuality = 1;
            annotationManager.globalAnnotationEditor.settings.rendererSettings.markersOnly = false;

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

            annotationManager.globalAnnotationEditor.style.cssText = `
                width: 90vw;
                height: 90vh;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            `;

            editorWrapper.appendChild(annotationManager.globalAnnotationEditor);
            document.body.appendChild(editorWrapper);

            $('#annotation-status').text('Annotation editor opened').css('color', 'blue');
        }
        catch (error) {
            console.error('Error opening annotation editor:', error);
            $('#annotation-status').text(`Error opening annotation editor: ${(error as Error).message}`).css('color', 'red');
        }
    }
}

function saveAnnotations(): void {
    const annotationData: AnnotationData = {
        user_id: annotationManager.userId,
        grader_id: annotationManager.graderId,
        filename: annotationManager.filename,
        file_path: annotationManager.filePath,
        annotations: JSON.stringify(annotationManager.currentAnnotations),
        csrf_token: annotationManager.csrfToken,
    };

    $.ajax({
        url: buildCourseUrl(['gradeable', annotationManager.gradeableId, 'img', 'annotations']),
        type: 'POST',
        data: annotationData,
        dataType: 'json',
        success: function (response: ApiResponse) {
            if (response.status === 'success') {
                $('#annotation-status').text('Annotations saved successfully!').css('color', 'green');
            }
            else {
                $('#annotation-status').text(`Error saving annotations: ${response.message || 'Unknown error'}`).css('color', 'red');
            }
        },
        error: function (xhr: any, status: string, error: string) {
            $('#annotation-status').text(`Error saving annotations: ${error}`).css('color', 'red');
        },
    });
}

function clearAnnotations(): void {
    if (confirm('Are you sure you want to clear all annotations?')) {
        annotationManager.currentAnnotations = null;
        annotationManager.annotatedImageDataUrl = null;

        // Hide any open annotation editor instead of removing it
        const editorWrapper: HTMLElement | null = document.getElementById('global-annotation-editor-wrapper');
        if (editorWrapper) {
            editorWrapper.style.display = 'none';
        }

        // Restore original image if it was replaced with MarkerView
        const markerView: any = document.getElementById('annotation-marker-view');
        if (markerView && markerView.targetImage) {
            const originalImgElement: HTMLImageElement = markerView.targetImage;

            // Restore original properties
            if (originalImgElement.dataset.originalId) {
                originalImgElement.id = originalImgElement.dataset.originalId;
                originalImgElement.className = originalImgElement.dataset.originalClass || '';

                // Restore original size properties
                if (originalImgElement.dataset.originalWidth) {
                    originalImgElement.style.width = originalImgElement.dataset.originalWidth;
                }
                if (originalImgElement.dataset.originalHeight) {
                    originalImgElement.style.height = originalImgElement.dataset.originalHeight;
                }
            }

            // Replace MarkerView back with original image
            if (markerView.parentElement) {
                markerView.parentElement.replaceChild(originalImgElement, markerView);
            }

            // Update manager reference
            annotationManager.originalImg = originalImgElement;
        }

        $('#annotation-status').text('Annotations cleared (not saved)').css('color', 'red');
    }
}

function downloadImage(): void {
    if (!annotationManager.originalImg) {
        console.error('No image available for download');
        alert('Error: No image available for download');
        return;
    }

    if (annotationManager.annotatedImageDataUrl && annotationManager.currentAnnotations && annotationManager.currentAnnotations.markers && annotationManager.currentAnnotations.markers.length > 0) {
        // Create download link with the pre-generated annotated image
        const link: HTMLAnchorElement = document.createElement('a');
        link.href = annotationManager.annotatedImageDataUrl;

        // Add suffix to filename for annotated version
        const nameParts: string[] = annotationManager.filename.split('.');
        let downloadFilename: string;
        if (nameParts.length > 1) {
            const extension: string = nameParts.pop()!;
            downloadFilename = `${nameParts.join('.')}_annotated.${extension}`;
        }
        else {
            downloadFilename = `${annotationManager.filename}_annotated`;
        }

        link.download = downloadFilename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    else {
        // No annotations, download original image
        const link: HTMLAnchorElement = document.createElement('a');
        link.href = annotationManager.originalImg.src;
        link.download = annotationManager.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function cleanupAnnotationEditor(): void {
    // Hide the global annotation editor when switching to a different image
    const editorWrapper: HTMLElement | null = document.getElementById('global-annotation-editor-wrapper');
    if (editorWrapper) {
        editorWrapper.style.display = 'none';
    }

    // Restore original image if it was replaced with MarkerView
    const markerView: any = document.getElementById('annotation-marker-view');
    if (markerView && markerView.targetImage) {
        const originalImgElement: HTMLImageElement = markerView.targetImage;

        // Restore original properties
        if (originalImgElement.dataset.originalId) {
            originalImgElement.id = originalImgElement.dataset.originalId;
            originalImgElement.className = originalImgElement.dataset.originalClass || '';

            // Restore original size properties
            if (originalImgElement.dataset.originalWidth) {
                originalImgElement.style.width = originalImgElement.dataset.originalWidth;
            }
            if (originalImgElement.dataset.originalHeight) {
                originalImgElement.style.height = originalImgElement.dataset.originalHeight;
            }
        }

        // Replace MarkerView back with original image
        if (markerView.parentElement) {
            markerView.parentElement.replaceChild(originalImgElement, markerView);
        }

        // Update manager reference
        annotationManager.originalImg = originalImgElement;
    }
}

function renderAnnotationsOnImage(): void {
    if (!annotationManager.originalImg) {
        console.error('No target image provided to renderAnnotationsOnImage');
        $('#annotation-status').text('Error: No image available for rendering').css('color', 'red');
        return;
    }

    if (!annotationManager.currentAnnotations) {
        return;
    }

    try {
        // Check if we already replaced the image with MarkerView
        const existingMarkerView: HTMLElement | null = document.getElementById('annotation-marker-view');
        if (existingMarkerView) {
            return;
        }

        // Store original image properties before replacing
        if (!annotationManager.originalImg.dataset.originalId) {
            annotationManager.originalImg.dataset.originalId = annotationManager.originalImg.id;
            annotationManager.originalImg.dataset.originalSrc = annotationManager.originalImg.src;
            annotationManager.originalImg.dataset.originalAlt = annotationManager.originalImg.alt;
            annotationManager.originalImg.dataset.originalClass = annotationManager.originalImg.className;
            annotationManager.originalImg.dataset.originalWidth = annotationManager.originalImg.style.width || '';
            annotationManager.originalImg.dataset.originalHeight = annotationManager.originalImg.style.height || '';
        }

        // Create MarkerView instance
        const markerView: any = new markerjs3.MarkerView();
        markerView.id = 'annotation-marker-view';
        markerView.className = annotationManager.originalImg.className; // Copy original classes
        markerView.targetImage = annotationManager.originalImg;

        // Get computed style dimensions to ensure proper scaling
        const computedStyle: CSSStyleDeclaration = getComputedStyle(annotationManager.originalImg);
        const displayWidth: number = parseInt(computedStyle.width) || annotationManager.originalImg.width || annotationManager.originalImg.naturalWidth;
        const displayHeight: number = parseInt(computedStyle.height) || annotationManager.originalImg.height || annotationManager.originalImg.naturalHeight;

        // Set target dimensions to match the displayed image size
        markerView.targetWidth = displayWidth;
        markerView.targetHeight = displayHeight;

        // Set zoom level to 1 to show image at natural scale within the target dimensions
        markerView.zoomLevel = 1;

        // Apply the original image's size to MarkerView container
        markerView.style.width = `${displayWidth}px`;
        markerView.style.height = `${displayHeight}px`;

        // Copy any additional inline styles for width/height if they exist
        if (annotationManager.originalImg.style.width) {
            markerView.style.width = annotationManager.originalImg.style.width;
        }
        if (annotationManager.originalImg.style.height) {
            markerView.style.height = annotationManager.originalImg.style.height;
        }

        // Replace the image with MarkerView
        if (annotationManager.originalImg.parentElement) {
            annotationManager.originalImg.parentElement.replaceChild(markerView, annotationManager.originalImg);
        }

        // Show the annotations
        markerView.show(annotationManager.currentAnnotations);

        $('#annotation-status').text('Annotations rendered').css('color', 'green');
    }
    catch (error) {
        console.error('Error in renderAnnotationsOnImage:', error);
        $('#annotation-status').text(`Error rendering annotations: ${(error as Error).message}`).css('color', 'red');
    }
}

async function generateAnnotatedImageDataURL(): Promise<void> {
    if (!annotationManager.originalImg || !annotationManager.currentAnnotations) {
        return;
    }

    try {
        // Create a Renderer instance to generate the annotated image
        const renderer: any = new markerjs3.Renderer();
        renderer.targetImage = annotationManager.originalImg;

        // Generate the annotated image dataURL using the current annotations
        const dataUrl: string = await renderer.rasterize(annotationManager.currentAnnotations);

        // Store the annotated image data URL
        annotationManager.annotatedImageDataUrl = dataUrl;
        console.log('Annotated image dataURL generated');
    }
    catch (error) {
        console.error('Error generating annotated image dataURL:', error);
    }
}

function initImageAnnotation(gId: string, uId: string, grId: string, fname: string, fPath: string, token: string, isStud: boolean, existingAnnotations?: AnnotationState): void {
    // Clean up any existing annotation editor before initializing a new image
    cleanupAnnotationEditor();

    // Reset the annotation manager for the new image
    annotationManager.reset();

    // Set variables from parameters
    annotationManager.gradeableId = gId;
    annotationManager.userId = uId;
    annotationManager.graderId = grId;
    annotationManager.filename = fname;
    annotationManager.filePath = fPath;
    annotationManager.csrfToken = token;
    annotationManager.isStudent = isStud;

    // Wait for DOM to be ready
    $(document).ready(() => {
        // Get the original image element
        console.log(existingAnnotations);
        annotationManager.originalImg = document.getElementById('annotatable-image') as HTMLImageElement;

        // Handle image load errors
        if (annotationManager.originalImg) {
            annotationManager.originalImg.onerror = function () {
                console.error('Failed to load image:', fPath);
                $('#image-error-message').text(`Error loading image: ${fname}`).show();
            };
        }

        // Load existing annotations if available
        if (existingAnnotations && existingAnnotations.markers) {
            annotationManager.currentAnnotations = existingAnnotations;
        }

        // Wait for image to load before rendering annotations
        if (annotationManager.originalImg && (annotationManager.originalImg.complete && annotationManager.originalImg.naturalHeight !== 0)) {
            // Image is already loaded
            renderAnnotationsOnImage();
            // Generate annotated image dataURL if annotations exist
            if (annotationManager.currentAnnotations && annotationManager.currentAnnotations.markers && annotationManager.currentAnnotations.markers.length > 0) {
                generateAnnotatedImageDataURL().catch(console.error);
            }
        }
        else if (annotationManager.originalImg) {
            // Wait for image to load
            const imageLoadHandler = async function () {
                if (annotationManager.originalImg) {
                    annotationManager.originalImg.removeEventListener('load', imageLoadHandler);
                    renderAnnotationsOnImage();
                    // Generate annotated image dataURL if annotations exist
                    if (annotationManager.currentAnnotations && annotationManager.currentAnnotations.markers && annotationManager.currentAnnotations.markers.length > 0) {
                        try {
                            await generateAnnotatedImageDataURL();
                        }
                        catch (error) {
                            console.error('Error generating annotated image on load:', error);
                        }
                    }
                }
            };
            annotationManager.originalImg.addEventListener('load', imageLoadHandler);
        }
    });
}

// Make all functions available globally
window.initImageAnnotation = initImageAnnotation;
window.addAnnotations = addAnnotations;
window.saveAnnotations = saveAnnotations;
window.clearAnnotations = clearAnnotations;
window.downloadImage = downloadImage;
window.cleanupAnnotationEditor = cleanupAnnotationEditor;
