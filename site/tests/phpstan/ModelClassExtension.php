<?php

namespace tests\phpstan;

use app\libraries\Utils;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Dummy\DummyMethodReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

class ModelClassExtension implements MethodsClassReflectionExtension {
    public function hasMethod(ClassReflection $reflection, string $method_name): bool {
        if (!Utils::startsWith($reflection->getName(), 'app\\models')) {
            return false;
        }

        $method_name = preg_replace_callback('/^(get|set|is)([A-Z])/', function ($match) {
            return strtolower($match[2]);
        }, $method_name);
        $method_name = preg_replace_callback('/([A-Z])/', function ($match) {
            return '_'.strtolower($match[0]);
        }, $method_name);
        return $reflection->hasProperty($method_name);
    }

    public function getMethod(ClassReflection $reflection, string $method_name): MethodReflection {
        return new DummyMethodReflection($method_name);
    }
}
