/* exported attachCollapsiblePanel */
/**
 * Gives collapsible panels with attached handler on windows' resize event by default
 * @param panelHeadSel querySelector for the panel's header
 * @param breakPoint collapsible panels work only below this breakPoint value
 * @param animateDurInMs animation duration in milli-secs
 * @param headActiveClass className which will be added on active headers
 */
function attachCollapsiblePanel (panelHeadSel, breakPoint, headActiveClass, animateDurInMs = 600) {
    // Setting up variables
    let isCollapsibleDisabled = true;
    function handleCollapsiblePanel () {
        if (window.innerWidth < breakPoint && isCollapsibleDisabled) {
            // Add a listener on a head
            $(panelHeadSel).click(function () {
                $(this).toggleClass(headActiveClass);
                $(this).next().slideToggle({
                    duration: animateDurInMs,
                });
            });
            isCollapsibleDisabled = false;
        }
        else if (window.innerWidth > breakPoint && !isCollapsibleDisabled) {
            // clear the listener from the header
            $(panelHeadSel).off('click');
            // Make all the panels visible
            $(panelHeadSel).each(function() {
                if ($(this).next().is( ':hidden' )) {
                    $(this).next().slideDown('slow');
                    $(this).addClass(headActiveClass);
                }
            });
            isCollapsibleDisabled = true;
        }
    }
    // Check for the panels status initially
    handleCollapsiblePanel();
    // Finally, attach the handler on resize event of window
    window.addEventListener('resize', handleCollapsiblePanel);
}

