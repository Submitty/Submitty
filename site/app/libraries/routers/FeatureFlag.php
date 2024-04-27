<?php

namespace app\libraries\routers;

use InvalidArgumentException;

/**
 * Annotation class for @ FeatureFlag().
 *
 * Use this to enable access to a given controller or
 * method of a controller. The feature flag is passed
 * as the single argument to the constructor:
 *
 * ```php
 * @FeatureFlag("foo")
 * class Foo {}
 * ```
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class FeatureFlag {
    /** @var string */
    private $flag;

    public function __construct(array $data) {
        if (empty($data['value']) || !is_string($data['value'])) {
            throw new InvalidArgumentException('Must have non-empty string "value" for FeatureFlag annotation');
        }
        $this->flag = $data['value'];
    }

    public function getFlag(): string {
        return $this->flag;
    }
}
