<?php

namespace app\views;

use app\libraries\GradeableType;
use app\models\Gradeable;
use app\views\AbstractView;
use app\models\gradeable\LateDays;

class LateDaysTableView extends AbstractView {
    public function showLateTable(LateDays $late_days, string $hightlight_gradeable) {
        $preferred_name = $late_days->getUser()->getDisplayFullName();
        $table_data = $this->core->getOutput()->renderTwigTemplate('LateDaysTablePlugin.twig', [
            'late_days' => $late_days,
            'highlight_gradeable' => $hightlight_gradeable,
        ]);
        $table_data = "<hr><h2>Late Day Usage by " . $preferred_name . " (" . $late_days->getUser()->getId() . ")</h2><br>" . $table_data;
        return $table_data;
    }

    public function showLateTablePage(LateDays $late_days) {
        $this->core->getOutput()->addBreadcrumb("My Late Days");
        return $this->core->getOutput()->renderTwigTemplate('LateDaysTable.twig', [
            'late_days' => $late_days
        ]);
    }
}

