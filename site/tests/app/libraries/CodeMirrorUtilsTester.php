<?php

namespace tests\app\libraries;

use app\libraries\CodeMirrorUtils;

class CodeMirrorUtilsTester extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider codeMirrorModeDataProvider
     */
    public function testGetCodeMirrorMode(?string $type, string $expected): void {
        $this->assertSame($expected, CodeMirrorUtils::getCodeMirrorMode($type));
    }

    public function codeMirrorModeDataProvider(): array {
        return [
            ['c', 'text/x-csrc'],
            ['c++', 'text/x-c++src'],
            ['cpp', 'text/x-c++src'],
            ['h', 'text/x-c++src'],
            ['hpp', 'text/x-c++src'],
            ['haskell', 'text/x-haskell'],
            ['haskell-literate', 'text/x-literate-haskell'],
            ['c#', 'text/x-csharp'],
            ['objective-c', 'text/x-objectivec'],
            ['java', 'text/x-java'],
            ['scala', 'text/x-scala'],
            ['matlab', 'text/x-octave'],
            ['node', 'text/javascript'],
            ['nodejs', 'text/javascript'],
            ['javascript', 'text/javascript'],
            ['js', 'text/javascript'],
            ['typescript', 'text/typescript'],
            ['json', 'application/json'],
            ['python', 'text/x-python'],
            ['oz', 'text/x-oz'],
            ['sql', 'text/x-sql'],
            ['mysql', 'text/x-mysql'],
            ['pgsql', 'text/x-pgsql'],
            ['postgres', 'text/x-pgsql'],
            ['postgresql', 'text/x-pgsql'],
            ['scheme', 'text/x-scheme'],
            ['sh', 'text/x-sh'],
            ['bash', 'text/x-sh'],
            ['txt', 'text/plain'],
            ['invalid', 'text/plain'],
            [null, 'text/plain']
        ];
    }
}
