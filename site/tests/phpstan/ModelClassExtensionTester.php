<?php

declare(strict_types = 1);

namespace tests\phpstan;

use app\models\Button;
use PHPStan\Testing\TestCase;

class ModelClassExtensionTester extends TestCase {
    public function methodDataProvider() {
        return [
            [Button::class, 'getOnclick', true],
            [Button::class, 'setOnclick', true],
            [Button::class, 'isOnclick', true],
            [Button::class, 'getAriaLabel', true],
            [Button::class, 'setAriaLabel', true],
            [Button::class, 'isOnclick', true],
            [Button::class, 'getFoo', false],
            [Button::class, 'setFoo', false],
            [Button::class, 'isFoo', false],
            [Button::class, 'getFooBar', false],
            [Button::class, 'setFooBar', false],
            [Button::class, 'isFooBar', false]
        ];
    }

    /**
     * @dataProvider methodDataProvider
     */
    public function testHasMethod(string $class, string $method, bool $expected): void {
        $broker = $this->createBroker();
        $extension = new ModelClassExtension();
        $reflection = $broker->getClass($class);
        $this->assertSame($expected, $extension->hasMethod($reflection, $method));
    }

    /**
     * @dataProvider methodDataProvider
     */
    public function testGetMethod(string $class, string $method, bool $expected): void {
        $broker = $this->createBroker();
        $extension = new ModelClassExtension();
        $reflection = $broker->getClass($class);
        $this->assertSame($method, $extension->getMethod($reflection, $method)->getName());
    }
}
