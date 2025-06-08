<?php

namespace tests\phpstan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

class SubmittyMagicMethodReflection implements MethodReflection {
    private string $method_name;

    private ClassReflection $classReflection;

    public function __construct(string $method_name, ClassReflection $classReflection) {
        $this->method_name = $method_name;
        $this->classReflection = $classReflection;
    }

    public function getDeclaringClass(): ClassReflection {
        return $this->classReflection;
    }

    public function isStatic(): bool {
        return false;
    }

    public function isPrivate(): bool {
        return false;
    }

    public function isPublic(): bool {
        return true;
    }

    public function getDocComment(): ?string {
        return null;
    }

    public function getName(): string {
        return $this->method_name;
    }

    public function getPrototype(): ClassMemberReflection {
        return $this;
    }

    public function getVariants(): array {
        $phpdoc = $this->classReflection->getResolvedPhpDoc();
        $method_tags = [];
        if ($phpdoc !== null) {
            $method_tags = $phpdoc->getMethodTags();
        }
        $parent = $this->classReflection->getParentClass();
        while ($parent !== null) {
            $phpdoc = $parent->getResolvedPhpDoc();
            // order of array_merge is critical. since we are traversing from child to parent, we want child methods
            // to override their parent's methods
            $method_tags = array_merge($phpdoc->getMethodTags(), $method_tags);
            $parent = $parent->getParentClass();
        }
        $mt = $method_tags[$this->method_name];
        $params = [];
        foreach ($mt->getParameters() as $n => $p) {
            $params[] = new NativeParameterReflection(
                $n,
                $p->isOptional(),
                $p->getType(),
                $p->passedByReference(),
                $p->isVariadic(),
                $p->getDefaultValue()
            );
        }
        return [new SubmittyMagicVariant($params, $mt->getReturnType())];
    }

    public function isDeprecated(): TrinaryLogic {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string {
        return null;
    }

    public function isFinal(): TrinaryLogic {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic {
        return TrinaryLogic::createMaybe();
    }
}
