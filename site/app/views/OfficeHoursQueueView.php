<?php

namespace app\views;

use app\models\User;
use app\models\OfficeHoursQueueViewer;

class OfficeHoursQueueView extends AbstractView {
    public function showTheQueue($viewer) {
        $this->core->getOutput()->addBreadcrumb("Office Hours Queue");
        $this->core->getOutput()->renderTwigOutput("OfficeHoursQueue.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'viewer' => $viewer,
            'base_url' => $this->core->buildCourseUrl().'/office_hours_queue/'
        ]);
    }

    // public function showQueueStudent($oh_queue) {
    //     $this->core->getOutput()->addBreadcrumb("Office Hours Queue");
    //     $this->core->getOutput()->renderTwigOutput("OfficeHoursQueueStudent.twig", [
    //     'csrf_token' => $this->core->getCsrfToken(),
    //     'add_url' => $this->core->buildCourseUrl(["office_hours_queue/add"]),
    //     'remove_url' => $this->core->buildCourseUrl(["office_hours_queue/remove"]),
    //     'oh_queue' => $oh_queue,
    //     'queue_open' => $this->core->getQueries()->isQueueOpen()
    //     ]);
    // }
    //
    // public function showQueueInstructor($oh_queue) {
    //     $this->core->getOutput()->addBreadcrumb("Office Hours Queue");
    //     $this->core->getOutput()->renderTwigOutput("OfficeHoursQueueInstructor.twig", [
    //     'csrf_token' => $this->core->getCsrfToken(),
    //     'entries' => $oh_queue->getEntries(),
    //     'entries_helped' => $oh_queue->getEntriesHelped(),
    //     'num_in_queue' => count($oh_queue->getEntries()),
    //     'queue_open' => $oh_queue->isQueueOpen(),
    //     'code' => $oh_queue->getCode(),
    //     'new_code_url' => $this->core->buildCourseUrl(["office_hours_queue/code"]),
    //     'toggle_open_url' => $this->core->buildCourseUrl(["office_hours_queue/toggle"]),
    //     'empty_queue_url' => $this->core->buildCourseUrl(["office_hours_queue/empty"]),
    //     'remove_url' => $this->core->buildCourseUrl(["office_hours_queue/remove"]),
    //     'start_help_url' => $this->core->buildCourseUrl(["office_hours_queue/startHelp"]),
    //     'finish_help_url' => $this->core->buildCourseUrl(["office_hours_queue/finishHelp"])
    //     ]);
    // }
}
