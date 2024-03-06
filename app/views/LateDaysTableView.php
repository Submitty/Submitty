<?php

namespace app\views;

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
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('latedaystableplugin.css');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate('LateDaysTable.twig', [
            'late_days' => $late_days,
        ]);
    }

    /**
     * @param LateDays $late_days
     * @return string
     */
    public function showLateTabletoInstructor(LateDays $late_days): string {
        $manage_url = $this->core->buildCourseUrl(['users']);
        $this->core->getOutput()->addBreadcrumb('Manage Students', $manage_url);
        $this->core->getOutput()->addBreadcrumb($late_days->getUser()->getDisplayFullName());
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('latedaystableplugin.css');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate('LateDaysTableGrader.twig', [
            'late_days' => $late_days,
        ]);
    }
}
