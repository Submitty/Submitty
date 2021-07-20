<?php

namespace app\models;

use app\libraries\Core;

class SuperuserEmail extends Email {
    public function __construct(Core $core, array $details = []) {
        parent::__construct($core, $details);
        $this->setSubject($this->formatSubject($details["subject"]));
    }
    private function formatSubject(string $subject): string {
        $course = $this->core->getConfig()->getCourse();
        return "[Submitty Admin Announcement]: " . $subject;
    }
}
