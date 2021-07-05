<?php

namespace app\models;

use app\libraries\Core;
use app\models\Email;

class SuperuserEmail extends Email {
    public function __construct(Core $core, array $details = []) {
        parent::__construct($core, $details);
    }
    private function formatSubject(string $subject): string {
        $course = $this->core->getConfig()->getCourse();
        return "[Submitty Admin Announcement]: " . $subject;
    }
}
