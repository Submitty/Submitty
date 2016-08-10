<?php

namespace e2e\submission;

use tests\e2e\BaseTestCase;

class StudentSubmissionTester extends BaseTestCase {
    protected $user_id = "student";
    protected $password = "student";
    protected $test_url = "http://192.168.56.104";
    
    public function testUpload() {
        $this->assertTrue(true);
    }
    
}