<?php

namespace app\libraries\routers;

use InvalidArgumentException;
use Attribute;

/**
 * Attribute class for #[FeatureFlag()].
 *
 * Use this to enable access to a given controller or
 * method of a controller. The feature flag is passed
 * as the single argument to the constructor:
 *
 * ```php
 * #[FeatureFlag(flag: "foo")]
 * class Foo {}
 * ```
 *
 */
#[Attribute]
class FeatureFlag {
    /** @var string */
    private $flag;

    public function __construct(?string $flag = null) {
        if ($flag === null) {
            throw new InvalidArgumentException('Must have non-empty string "flag" for FeatureFlag attribute');
        }
        $this->flag = $flag;
    }

    public function getFlag(): string {
        return $this->flag;
    }
}
