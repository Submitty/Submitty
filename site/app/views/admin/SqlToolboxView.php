<?php

declare(strict_types=1);

namespace app\views\admin;

use app\views\AbstractView;

class SqlToolboxView extends AbstractView {
    /**
     * @param array<string> $tables
     */
    public function showToolbox(array $sql_structure_data): string {
        $this->output->addInternalModuleJs('sql-toolbox.js');

        $this->output->addInternalCss('sql-toolbox.css');
        $this->output->addInternalCss('table.css');

        $this->output->addBreadcrumb('SQL Toolbox');

        return $this->output->renderTwigTemplate("Vue.twig", [
            "component" => "sqlToolboxPage",
            "args" => [
                "sqlStructureData" => $sql_structure_data
            ]
        ]);
    }
}
