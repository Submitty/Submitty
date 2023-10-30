<?php

namespace app\libraries;

use app\libraries\Core;

/**
 * Class SelectWidgets
 *
 * Adds the necessary CSS and Js to use Select2 widgets.
 */
class SelectWidgets {
    /**
     * Adds the necessary CSS and JS to use a Select2 widget.
     * Select2 widgets allow you to preselect from options as well as
     * type your own option.
     *
     * @param Core $core The core of the Submitty application we will add CSS and Js to.
     * @return void
     */
    public static function addSelect2WidgetCSSAndJs(Core $core): Void {
        $core->getOutput()->addVendorJs(FileUtils::joinPaths('select2', 'js', 'select2.min.js'));
        $core->getOutput()->addVendorCss(FileUtils::joinPaths('select2', 'css', 'select2.min.css'));
        $core->getOutput()->addVendorCss(FileUtils::joinPaths(
            'select2',
            'bootstrap5-theme',
            'select2-bootstrap-5-theme.min.css'
        ));
        $core->getOutput()->addInternalCss("select-widgets.css");
        $core->getOutput()->addInternalJs("select-widgets.js");
    }
}
