# UI Change Documentation: Rubric Cancel Dialog

## Overview
This document describes the UI improvement made to the rubric cancel confirmation dialog in the Submitty TA Grading interface.

## Before
**Dialog Type**: Browser's native `confirm()` dialog
**Button Labels**: 
  - OK (confirms discard)
  - Cancel (closes dialog)

**Issues**:
- Generic button labels don't clearly indicate what action will be taken
- Native browser dialog has limited styling control

## After  
**Dialog Type**: Custom styled Twig popup template
**Button Labels**:
  - "Keep Editing" (closes dialog, returns to editing)
  - "Discard Changes" (confirms discard action)

**Improvements**:
- Clear semantic button labels explain exactly what each action does
- Consistent styling with rest of the Submitty interface
- Better accessibility and user experience
- More specific confirmation message: "Are you sure you want to discard all changes to the student message?"

## Implementation Details
- Template: `site/app/templates/grading/electronic/DiscardChangesPopup.twig`
- Handler: `window.onCancelComponent()` in `site/ts/ta-grading-rubric.ts`
- Functionality: Popup is shown when user clicks cancel on a component with unsaved changes

## User Flow
1. User opens a grading component and makes changes to the comments
2. User clicks the "Cancel" button to close the component
3. If there are unsaved changes, the confirmation popup appears
4. User can now:
   - Click "Keep Editing" to continue editing
   - Click "Discard Changes" to close without saving

## Testing
The changes are covered by unit tests in `site/tests/ts/ta-grading-rubric.spec.js`:
- Verifies popup appears when there are unsaved changes
- Verifies click handler is properly attached to confirm button
- Verifies popup is hidden after confirmation
- Verifies component is closed via `toggleComponent()` function
- Verifies error handling when closure fails
- Verifies immediate closure when no changes exist
