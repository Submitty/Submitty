<?php

namespace tests\phpstan;

use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

class SubmittyMagicVariant implements ParametersAcceptor {
    private array $params;

    private Type $returnType;

    public function __construct(array $params, Type $returnType) {
        $this->params = $params;
        $this->returnType = $returnType;
    }


    public function getTemplateTypeMap(): TemplateTypeMap {
        return TemplateTypeMap::createEmpty();
    }

    public function getResolvedTemplateTypeMap(): TemplateTypeMap {
        return TemplateTypeMap::createEmpty();
    }

    /**
     * @inheritDoc
     */
    public function getParameters(): array {
        return $this->params;
    }

    public function isVariadic(): bool {
        return false;
    }

    public function getReturnType(): Type {
        return $this->returnType;
    }
}
