# PR Description for Issue #9018

## Title
[UI] Clarify rubric cancel dialog labels

## Description

### Summary
This PR improves the user experience of the rubric cancel confirmation dialog by replacing the generic browser `confirm()` dialog with a custom styled popup featuring clear, semantic button labels.

### Changes Made
- Replaced browser's native `confirm()` dialog with custom Twig popup template
- Created `site/app/templates/grading/electronic/DiscardChangesPopup.twig` for the custom popup
- Modified `site/ts/ta-grading-rubric.ts` to use the new popup instead of browser dialog
- Updated `site/app/views/grading/ElectronicGraderView.php` to render the new popup template
- Updated `site/app/controllers/grading/ElectronicGraderController.php` to include popup rendering

### Button Labels
**Before**:
- "OK" → confirms discard changes
- "Cancel" → closes dialog without action

**After**:
- "Keep Editing" → closes dialog, returns to editing
- "Discard Changes" → confirms and closes component without saving

### Benefits
1. **Improved Clarity**: Button labels now clearly indicate the action each button performs
2. **Better UX**: Custom styling consistent with rest of Submitty interface  
3. **Accessibility**: More specific confirmation message helps users understand consequences
4. **Maintainability**: Centralized Twig template makes future updates easier

### UI Screenshots

#### Before
The browser's native `confirm()` dialog with generic "OK" and "Cancel" buttons

#### After  
Custom styled popup with clear action-oriented button labels:
- Dialog title: "Discard Changes?"
- Message: "Are you sure you want to discard all changes to the student message?"
- Buttons: "Keep Editing" and "Discard Changes"

**Visual Reference**: See [RUBRIC_CANCEL_DIALOG_CHANGES.md](../../images/ui/RUBRIC_CANCEL_DIALOG_CHANGES.md) for detailed before/after comparison

### Tests Added
- Unit tests covering:
  - Popup display when changes exist
  - Click handler attachment to confirm button
  - Popup hiding after confirmation
  - Component closure with proper parameters
  - Error handling for failed closures  
  - Immediate closure when no changes exist

### Breaking Changes
None - this is purely a UI improvement with no functional changes

### Related Issues
Closes #9018

### Testing Instructions
1. Navigate to the TA Grading page
2. Open a grading component
3. Make changes to the student comments
4. Click the Cancel button
5. Verify the new styled popup appears with "Keep Editing" and "Discard Changes" buttons
6. Test both button actions to confirm they work correctly

### Checklist
- [x] Changes follow Submitty coding standards
- [x] No functional changes to grading logic
- [x] UI improvement only
- [x] Tests added for new popup functionality
- [x] All CI checks pass
- [x] Code coverage requirements met
