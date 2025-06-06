<?php

declare(strict_types=1);

namespace app\views\admin;

use app\views\AbstractView;

class SqlToolboxView extends AbstractView {
    /**
     * @param array<string> $sql_structure_data
     * @param array<string> $user_queries
     */
    public function showToolbox(array $sql_structure_data, array $user_queries): string {
        $this->output->addInternalCss('sql-toolbox.css');
        $this->output->addInternalCss('table.css');

        $this->output->addBreadcrumb('SQL Toolbox');

        return $this->output->renderTwigTemplate("admin/SqlToolbox.twig", [
            "sql_structure_data" => $sql_structure_data,
            "user_queries" => $user_queries
        ]);
    }
}
