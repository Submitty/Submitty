<?php

declare(strict_types=1);

namespace app\views\admin;

use app\views\AbstractView;

class SqlToolboxView extends AbstractView {
    public function showToolbox(): string {
        $this->output->addInternalJs('sql-toolbox.js');

        $this->output->addInternalCss('sql-toolbox.css');
        $this->output->addInternalCss('table.css');

        $this->output->addBreadcrumb('SQL Toolbox');

        return $this->output->renderTwigTemplate(
            "admin/SqlToolbox.twig"
        );
    }
}
