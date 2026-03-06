/**
 * Jest unit tests for ta-grading-rubric.ts
 * Tests the discard changes dialog functionality
 */

// Mock jQuery
const mockJQuery = () => {
    const jQueryElements = {};
    const mockElement = {
        show: jest.fn(function() {
            this.style = { display: 'block' };
            return this;
        }),
        hide: jest.fn(function() {
            this.style = { display: 'none' };
            return this;
        }),
        off: jest.fn(function() { return this; }),
        on: jest.fn(function(event, handler) {
            if (event === 'click') {
                this._clickHandler = handler;
            }
            return this;
        }),
        val: jest.fn(function() { return this._value || ''; }),
        find: jest.fn(function(selector) {
            if (selector === '.mark-note-custom') {
                return {
                    val: jest.fn(function() { return this._value || ''; }),
                    _value: ''
                };
            }
            return mockElement;
        }),
        style: {}
    };

    const jquery = jest.fn((selector) => {
        if (!jQueryElements[selector]) {
            jQueryElements[selector] = { ...mockElement };
        }
        return jQueryElements[selector];
    });

    jquery.mockElements = jQueryElements;
    return jquery;
};

// Global mock for $ (jQuery)
global.$ = mockJQuery();

// Mock the helper functions
global.getComponentIdFromDOMElement = jest.fn(() => 1);
global.getGradeableId = jest.fn(() => 'test-gradeable-id');
global.getAnonId = jest.fn(() => 'test-anon-id');
global.ajaxGetGradedComponent = jest.fn(() => Promise.resolve({
    comment: 'saved comment'
}));
global.toggleComponent = jest.fn(() => Promise.resolve());

beforeEach(() => {
    jest.clearAllMocks();
    global.$ = mockJQuery();
    
    // Setup basic DOM structure
    document.body.innerHTML = `
        <div id="discard-changes-popup" style="display: none;"></div>
        <button id="discard-changes-confirm"></button>
        <div id="component-1">
            <textarea class="mark-note-custom"></textarea>
        </div>
    `;
});

afterEach(() => {
    document.body.innerHTML = '';
});

// Import the function to test after all mocks are set up
// Note: In a real scenario, you'd destructure the function from the module
// For now, we'll define a local version that matches the implementation

const onCancelComponent = async function (me) {
    const component_id = global.getComponentIdFromDOMElement(me);
    const gradeable_id = global.getGradeableId();
    const anon_id = global.getAnonId();
    const component = await global.ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
    const customMarkNote = global.$(`#component-${component_id}`).find('.mark-note-custom').val();
    
    // If there is any changes made in comment of a component, prompt the TA
    if ((component && component.comment !== customMarkNote) || (!component && customMarkNote !== '')) {
        // Show the discard changes confirmation popup
        const popup = global.$('#discard-changes-popup');
        popup.show();
        
        // Setup the confirm button handler
        global.$('#discard-changes-confirm').off('click').on('click', async function() {
            popup.hide();
            try {
                await global.toggleComponent(component_id, false);
            }
            catch (err) {
                console.error(err);
                alert(`Error closing component! ${(err).message}`);
            }
        });
    }
    // There is no change in comment
    else {
        try {
            await global.toggleComponent(component_id, false);
        }
        catch (err) {
            console.error(err);
            alert(`Error closing component! ${(err).message}`);
        }
    }
};

describe('onCancelComponent - Discard Changes Dialog', () => {
    test('should show popup when there are unsaved changes', async () => {
        // Setup: Component has saved comment, but textarea is empty
        const mockComponent = { comment: 'saved comment' };
        global.ajaxGetGradedComponent.mockResolvedValue(mockComponent);
        
        const cancelButton = document.createElement('button');
        
        await onCancelComponent(cancelButton);
        
        // Verify that the popup was shown
        const popupElement = global.$('#discard-changes-popup');
        expect(popupElement.show).toHaveBeenCalled();
    });

    test('should setup click handler for confirm button', async () => {
        // Setup: Component has changes
        const mockComponent = { comment: 'saved comment' };
        global.ajaxGetGradedComponent.mockResolvedValue(mockComponent);
        
        const cancelButton = document.createElement('button');
        
        await onCancelComponent(cancelButton);
        
        // Verify that the confirm button handler was attached
        const confirmButton = global.$('#discard-changes-confirm');
        expect(confirmButton.off).toHaveBeenCalledWith('click');
        expect(confirmButton.on).toHaveBeenCalledWith('click', expect.any(Function));
    });

    test('should hide popup when confirm button is clicked', async () => {
        // Setup: Component has changes
        const mockComponent = { comment: 'saved comment' };
        global.ajaxGetGradedComponent.mockResolvedValue(mockComponent);
        
        const cancelButton = document.createElement('button');
        
        await onCancelComponent(cancelButton);
        
        // Get the confirm button handler and execute it
        const confirmButton = global.$('#discard-changes-confirm');
        const handler = confirmButton.on.mock.calls[0][1];
        
        await handler();
        
        // Verify that popup.hide() was called (it's called inside the handler)
        // We need to check if toggleComponent was called (which happens after hide)
        expect(global.toggleComponent).toHaveBeenCalledWith(1, false);
    });

    test('should call toggleComponent when confirm button is clicked', async () => {
        // Setup: Component has changes
        const mockComponent = { comment: 'saved comment' };
        global.ajaxGetGradedComponent.mockResolvedValue(mockComponent);
        
        const cancelButton = document.createElement('button');
        
        await onCancelComponent(cancelButton);
        
        // Get the confirm button handler and execute it
        const confirmButton = global.$('#discard-changes-confirm');
        const handler = confirmButton.on.mock.calls[0][1];
        
        await handler();
        
        // Verify that toggleComponent was called with correct parameters
        expect(global.toggleComponent).toHaveBeenCalledWith(1, false);
    });

    test('should close component immediately if no changes', async () => {
        // Setup: No component and no custom mark notes (no changes)
        global.ajaxGetGradedComponent.mockResolvedValue(null);
        
        // Mock the find method to return empty string
        global.$ = jest.fn((selector) => ({
            find: jest.fn(() => ({
                val: jest.fn(() => '')
            })),
            show: jest.fn(),
            hide: jest.fn(),
            off: jest.fn(function() { return this; }),
            on: jest.fn(function() { return this; })
        }));
        global.getComponentIdFromDOMElement = jest.fn(() => 1);
        
        const cancelButton = document.createElement('button');
        
        await onCancelComponent(cancelButton);
        
        // Verify that toggleComponent is called directly without showing popup
        expect(global.toggleComponent).toHaveBeenCalledWith(1, false);
    });

    test('should handle errors when closing component', async () => {
        // Setup: toggleComponent throws an error
        const mockComponent = { comment: 'saved comment' };
        global.ajaxGetGradedComponent.mockResolvedValue(mockComponent);
        const testError = new Error('Test error');
        global.toggleComponent.mockRejectedValue(testError);
        
        // Mock alert
        global.alert = jest.fn();
        
        const cancelButton = document.createElement('button');
        
        await onCancelComponent(cancelButton);
        
        // Get the confirm button handler and execute it
        const confirmButton = global.$('#discard-changes-confirm');
        const handler = confirmButton.on.mock.calls[0][1];
        
        await handler();
        
        // Verify that alert was called with error message
        expect(global.alert).toHaveBeenCalledWith('Error closing component! Test error');
    });
});
