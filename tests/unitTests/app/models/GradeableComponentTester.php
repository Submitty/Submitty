<?php

namespace unitTests\app\models;

use app\models\GradeableComponent;

class GradeableComponentTester extends \PHPUnit_Framework_TestCase {
    public function testGradeableComponent() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_max_value' => 100,
            'gc_is_text' => false,
            'gc_is_extra_credit' => false,
            'gc_order' => 1,
            'gcd_score' => 10,
            'gcd_component_comment' => 'Comment about gradeable'
        );

        $component = new GradeableComponent($details);
        $expected = array(
            'id' => 'test',
            'title' => 'Test Component',
            'ta_comment' => 'Comment to TA',
            'student_comment' => 'Comment to Student',
            'max_value' => 100,
            'is_text' => false,
            'is_extra_credit' => false,
            'order' => 1,
            'score' => 10,
            'comment' => 'Comment about gradeable'
        );
        $actual = $component->toArray();
        ksort($expected);
        ksort($actual);
        $this->assertEquals($expected, $actual);
        $this->assertEquals($expected['id'], $component->getId());
        $this->assertEquals($expected['title'], $component->getTitle());
        $this->assertEquals($expected['ta_comment'], $component->getTAComment());
        $this->assertEquals($expected['student_comment'], $component->getStudentComment());
        $this->assertEquals($expected['max_value'], $component->getMaxValue());
        $this->assertFalse($component->isText());
        $this->assertFalse($component->isExtraCredit());
        $this->assertEquals($expected['order'], $component->getOrder());
        $this->assertEquals($expected['score'], $component->getScore());
        $this->assertEquals($expected['comment'], $component->getComment());
    }

    public function testGradeableComponentBadScore() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_max_value' => 100,
            'gc_is_text' => false,
            'gc_is_extra_credit' => false,
            'gc_order' => 1,
            'gcd_score' => 1000,
            'gcd_component_comment' => 'Comment about gradeable'
        );
        $component = new GradeableComponent($details);
        $this->assertEquals(100, $component->getScore());
    }

    public function testNullDataRow() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_max_value' => 100,
            'gc_is_text' => false,
            'gc_is_extra_credit' => false,
            'gc_order' => 1,
            'gcd_score' => null,
            'gcd_component_comment' => null
        );
        $component = new GradeableComponent($details);
        $this->assertEquals(0, $component->getScore());
        $this->assertEquals("", $component->getComment());
    }
}