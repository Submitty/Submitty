<?php

declare(strict_types=1);

namespace tests\app\libraries;

use app\libraries\GradeableType;

class GradeableTypeTester extends \PHPUnit\Framework\TestCase {
    public static function typeDataProvider(): array {
        return [
            [GradeableType::ELECTRONIC_FILE, "Electronic File"],
            [GradeableType::CHECKPOINTS, "Checkpoints"],
            [GradeableType::NUMERIC_TEXT, "Numeric"]
        ];
    }

    /**
     * @dataProvider typeDataProvider
     */
    public function testTypeToString(int $type_const, string $type_string): void {
        $this->assertEquals($type_string, GradeableType::typeToString($type_const));
    }

    public function testInvalidType(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid specified type');
        GradeableType::typeToString(-1);
    }

    public static function stringDataProvider(): array {
        return [
            ["Electronic File", GradeableType::ELECTRONIC_FILE],
            ["Checkpoints", GradeableType::CHECKPOINTS],
            ["Numeric", GradeableType::NUMERIC_TEXT]
        ];
    }

    /**
     * @dataProvider stringDataProvider
     */
    public function testStringToType(string $type_string, int $type_const): void {
        $this->assertEquals($type_const, GradeableType::stringToType($type_string));
    }

    public function testInvalidString(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type');
        GradeableType::stringToType('invalid');
    }
}
