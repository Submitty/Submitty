<?php

namespace tests\phpstan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

class ModelClassExtension implements MethodsClassReflectionExtension {
    public function hasMethod(ClassReflection $reflection, string $method_name): bool {
        if ($reflection->hasNativeMethod($method_name)) {
            return true;
        }
        if (!str_starts_with($reflection->getName(), 'app\\models')) {
            return false;
        }

        $method_name = preg_replace_callback('/^(get|set|is)([A-Z])/', function ($match) {
            return strtolower($match[2]);
        }, $method_name);
        $method_name = preg_replace_callback('/([A-Z])/', function ($match) {
            return '_' . strtolower($match[0]);
        }, $method_name);
        $phpDoc = $reflection->getResolvedPhpDoc();
        if ($phpDoc === null || !array_key_exists($method_name, $phpDoc->getMethodTags())) {
            return false;
        }
        return $reflection->hasProperty($method_name);
    }

    public function getMethod(ClassReflection $reflection, string $method_name): MethodReflection {
        return new SubmittyMagicMethodReflection($method_name, $reflection);
    }
}
