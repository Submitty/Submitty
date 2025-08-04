// Import utility functions
import { buildCourseUrl } from './utils/server';
import type { AnnotationState, MarkerView, Renderer} from '@markerjs/markerjs3';
import type { AnnotationEditor } from '@markerjs/markerjs-ui';

// Type declarations for external libraries
const markerjs3 = window.markerjs3;
const markerjsUI = window.markerjsUI;
const $ = window.$;
declare global {
    interface Window {
        initImageAnnotation(gId: string, uId: string, grId: string, fname: string, fPath: string, token: string, isStud: boolean, allAnnotations?: Record<string, AnnotationState | string>): void;
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
        this.globalAnnotationEditor = null;
    }
}

// Single instance to manage the current image annotation state
const annotationManager: AnnotationManager = new AnnotationManager();

const emptyState: AnnotationState = {
    width: annotationManager.originalImg?.naturalWidth || 600,
    height: annotationManager.originalImg?.naturalHeight || 800,
    markers: [],
};

function addAnnotations(): void {
    if (!annotationManager.originalImg) {
        console.error('No target image provided to addAnnotations');
        $('#annotation-status').text('Error: No image available for annotation').css('color', 'red');
        return;
    }

    if (!annotationManager.originalImg.complete) {
        console.warn('Image not yet loaded, cannot add annotations');
        $('#annotation-status').text('Error: Image not loaded yet').css('color', 'red');
        return;
    }

    try {
        // Check if markerjsUI is available
        if (typeof markerjsUI === 'undefined' || !markerjsUI.AnnotationEditor) {
            console.error('markerjsUI AnnotationEditor is not loaded');
            $('#annotation-status').text('Error: MarkerJS-UI library not loaded').css('color', 'red');
            return;
        }

        // Always cleanup and reset everything
        cleanupAnnotationEditor();

        // Remove existing editor wrapper completely
        const existingWrapper = document.getElementById('global-annotation-editor-wrapper');
        if (existingWrapper) {
            existingWrapper.remove();
        }

        // Create fresh annotation editor
        annotationManager.globalAnnotationEditor = new markerjsUI.AnnotationEditor();

        // Set up event listeners
        setupAnnotationEditor();

        // Configure editor for current image
        configureEditorForImage();

        // Create and show wrapper
        const editorWrapper = createEditorWrapper();
        editorWrapper.appendChild(annotationManager.globalAnnotationEditor);
        document.body.appendChild(editorWrapper);
        editorWrapper.style.display = 'flex';

        
    }
    catch (error) {
        console.error('Error opening annotation editor:', error);
        $('#annotation-status').text(`Error opening annotation editor: ${(error as Error).message}`).css('color', 'red');
    }
}

function setupAnnotationEditor(): void {
    if (!annotationManager.globalAnnotationEditor) {
        console.error('setupAnnotationEditor: globalAnnotationEditor is null');
        return;
    }

    // Set up event handlers for the annotation editor
    annotationManager.globalAnnotationEditor.addEventListener('editorsave', (event) => {
        const detail = event.detail;
        if (detail.state && detail.dataUrl) {
            annotationManager.currentAnnotations = detail.state;
            annotationManager.annotatedImageDataUrl = detail.dataUrl;
        }

        // Hide the annotation editor
        const editorWrapper = document.getElementById('global-annotation-editor-wrapper');
        if (editorWrapper) {
            editorWrapper.style.display = 'none';
        }
        $('#annotation-status').text('Annotations modified (not saved)').css('color', 'green');
        // Render the annotations on the image
        renderAnnotationsOnImage();
    });

    annotationManager.globalAnnotationEditor.addEventListener('editorclose', () => {
        // Hide the annotation editor
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

    // Configure editor settings
    annotationManager.globalAnnotationEditor.settings.renderOnSave = true;
    annotationManager.globalAnnotationEditor.settings.rendererSettings.naturalSize = true;
    annotationManager.globalAnnotationEditor.settings.rendererSettings.imageType = 'image/png';
    annotationManager.globalAnnotationEditor.settings.rendererSettings.imageQuality = 1;
    annotationManager.globalAnnotationEditor.settings.rendererSettings.markersOnly = false;
}

function createEditorWrapper(): HTMLElement {
    const editorWrapper = document.createElement('div');
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

    if (annotationManager.globalAnnotationEditor) {
        annotationManager.globalAnnotationEditor.style.cssText = `
            width: 90vw;
            height: 90vh;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        `;
    }

    return editorWrapper;
}

function configureEditorForImage(): void {
    if (!annotationManager.globalAnnotationEditor || !annotationManager.originalImg) {
        return;
    }

    // Clear editor state first
    annotationManager.globalAnnotationEditor.restoreState(emptyState);

    annotationManager.globalAnnotationEditor.targetImage = annotationManager.originalImg;

    // Load existing annotations if available
    if (annotationManager.currentAnnotations) {
        annotationManager.globalAnnotationEditor.restoreState(annotationManager.currentAnnotations);
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
        error: function (xhr: unknown, status: string, error: string) {
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
        const markerView = document.getElementById('annotation-marker-view') as MarkerView | null;
        if (markerView && 'targetImage' in markerView && markerView.targetImage) {
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
    // Clear the global annotation editor reference
    annotationManager.globalAnnotationEditor = null;

    // Hide and remove any existing annotation editor wrapper
    const editorWrapper: HTMLElement | null = document.getElementById('global-annotation-editor-wrapper');
    if (editorWrapper) {
        editorWrapper.style.display = 'none';
        // Remove it completely to ensure fresh start
        editorWrapper.remove();
    }

    // Restore original image if it was replaced with MarkerView
    const markerView = document.getElementById('annotation-marker-view') as MarkerView | null;
    if (markerView && 'targetImage' in markerView && markerView.targetImage) {
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

    if (!annotationManager.currentAnnotations || annotationManager.currentAnnotations.markers.length === 0) {
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
        const markerView = new markerjs3.MarkerView();
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
        const renderer = new markerjs3.Renderer();
        renderer.targetImage = annotationManager.originalImg;

        // Generate the annotated image dataURL using the current annotations
        const dataUrl: string = await renderer.rasterize(annotationManager.currentAnnotations);

        // Store the annotated image data URL
        annotationManager.annotatedImageDataUrl = dataUrl;
    }
    catch (error) {
        console.error('Error generating annotated image dataURL:', error);
    }
}

/*
When we want to render multiple annotations on top of each other we can use renderer to create an uneditable image with all of the annotations.
Implement this when we add options to show other grader's annotations.

function parseAnnotationState(rawData: AnnotationState | string, key?: string): AnnotationState | null {
    if (typeof rawData === 'string') {
        try {
            return JSON.parse(rawData) as AnnotationState;
        } catch (error) {
            console.warn(`Failed to parse annotations${key ? ` for key ${key}` : ''}:`, error);
            return null;
        }
    }
    return rawData;
}

// Possible refactor would be to renderAnnotationsOnImage using markerjs3 renderer, and combine the two functions.
async function rasterizeAnnotatedImage(uId: string, allAnnotations: Record<string, AnnotationState | string>): Promise<HTMLImageElement>{
    const renderer = new markerjs3.Renderer();
    const img = document.getElementById('annotatable-image') as HTMLImageElement;
    renderer.naturalSize = true;
    renderer.targetImage = img;
    let combinedAnnotations = Object.create(emptyState);
    combinedAnnotations.height = renderer.height; // These end up as 0 or undefined no matter what I try, revisit next time we try to implement this.
    combinedAnnotations.width = renderer.width;
    
    for (const key of Object.keys(allAnnotations)) {
        if (key === uId) {
            continue;
        }
        
        const annotationState = parseAnnotationState(allAnnotations[key], key);
        // Check if annotationState and markers exist
        if (annotationState && annotationState.markers && Array.isArray(annotationState.markers)) {
            combinedAnnotations.markers = combinedAnnotations.markers.concat(annotationState.markers);
        } else {
            console.warn(`No valid markers found for key ${key}:`, annotationState);
        }
    }
    console.log(combinedAnnotations);
    const dataUrl = await renderer.rasterize(combinedAnnotations);
    img.src = dataUrl;
    document.getElementById('annotatable-image')?.replaceWith(img);
    return img;
}
*/

function initImageAnnotation(gId: string, uId: string, grId: string, fname: string, fPath: string, token: string, isStud: boolean, allAnnotations?: Record<string, string>) {
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
        const existingAnnotations = JSON.parse(allAnnotations?.[grId] || JSON.stringify(emptyState)) as AnnotationState;
        /*
        Replace our image with our combined image/other user annotations.
        //const existingAnnotations = allAnnotations ? parseAnnotationState(allAnnotations[uId], uId) || emptyState : emptyState;
        if (allAnnotations && Object.keys(allAnnotations).length > 0) {
            rasterizeAnnotatedImage(uId, allAnnotations).then((src) => {
                annotationManager.originalImg = src;
            });
        } else {
            annotationManager.originalImg = document.getElementById('annotatable-image') as HTMLImageElement;
        }*/
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
        if (annotationManager.originalImg && (annotationManager.originalImg.complete && annotationManager.originalImg.naturalHeight !== 0) && existingAnnotations) {
            // Image is already loaded
            renderAnnotationsOnImage();
            // Generate annotated image dataURL if annotations exist
            if (annotationManager.currentAnnotations && annotationManager.currentAnnotations.markers && annotationManager.currentAnnotations.markers.length > 0) {
                void generateAnnotatedImageDataURL().catch(console.error);
            }
        }
        else if (annotationManager.originalImg && existingAnnotations) {
            // Wait for image to load
            const imageLoadHandler = function () {
                if (annotationManager.originalImg) {
                    annotationManager.originalImg.removeEventListener('load', imageLoadHandler);
                    renderAnnotationsOnImage();
                    // Generate annotated image dataURL if annotations exist
                    if (annotationManager.currentAnnotations && annotationManager.currentAnnotations.markers && annotationManager.currentAnnotations.markers.length > 0) {
                        void generateAnnotatedImageDataURL().catch(console.error);
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
