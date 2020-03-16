<?php

namespace app\libraries\routers;

/**
 * Annotation class for @FeatureFlag().
 *
 * Use this to enable access to a given controller or
 * method of a controller. The feature flag is passed
 * as the single argument to the constructor:
 *
 *   @FeatureFlag('foo')
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class FeatureFlag {
    /** @var string */
    private $flag;

    public function __construct(array $data) {
        $this->flag = $data['value'];
    }

    public function getFlag(): string {
        return $this->flag;
    }
}
