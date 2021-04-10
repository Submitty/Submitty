<?php

namespace app\views\admin;

use app\views\AbstractView;

class SqlToolboxView extends AbstractView {
    public function showToolbox() {
        $this->output->addInternalJs('sql-toolbox.js');

        $this->output->addInternalCss('sql-toolbox.css');
        $this->output->addInternalCss('table.css');

        return $this->output->renderTwigTemplate(
            "admin/SqlToolbox.twig"
        );
    }
}
