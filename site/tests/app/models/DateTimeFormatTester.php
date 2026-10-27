<?php

declare(strict_types=1);

namespace tests\app\models;

use app\exceptions\BadArgumentException;
use app\models\DateTimeFormat;
use tests\BaseUnitTest;

class DateTimeFormatTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testConstructWithValidSpecifier(): void {
        $format = new DateTimeFormat($this->core, 'MDY');
        $this->assertEquals('m/d/Y @ h:i A T', $format->getFormat('gradeable'));
    }

    public function testConstructWithInvalidSpecifierThrows(): void {
        $this->expectException(BadArgumentException::class);
        new DateTimeFormat($this->core, 'not_a_specifier');
    }

    public function testSetSpecifierChangesFormats(): void {
        $format = new DateTimeFormat($this->core, 'MDY');
        $format->setSpecifier('DMY');
        $this->assertEquals('d/m/Y @ h:i A T', $format->getFormat('gradeable'));
    }

    public function testSetSpecifierWithInvalidValueThrows(): void {
        $format = new DateTimeFormat($this->core, 'MDY');
        $this->expectException(BadArgumentException::class);
        $format->setSpecifier('bogus');
    }

    public function testGetFormatWithInvalidKeyThrows(): void {
        $format = new DateTimeFormat($this->core, 'YMD');
        $this->expectException(BadArgumentException::class);
        $format->getFormat('not_a_real_key');
    }

    public function testAllSpecifiersAndKeysReturnStrings(): void {
        foreach (DateTimeFormat::SPECIFIERS as $specifier) {
            $format = new DateTimeFormat($this->core, $specifier);
            foreach (DateTimeFormat::DATE_FORMATS_KEYS as $key) {
                $this->assertIsString($format->getFormat($key));
            }
        }
    }
}
