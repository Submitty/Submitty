<?php

declare(strict_types=1);

namespace app\models;

use app\libraries\Core;

class NavButton extends Button {
    public function __construct(Core $core, array $details) {
        $details['class'] = 'nav-row';
        if (!isset($details['id'])) {
            $details['id'] = 'nav-sidebar-' . str_replace(' ', '-', strtolower($details['title']));
        }
        parent::__construct($core, $details);
    }
}
