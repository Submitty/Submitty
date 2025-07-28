<?php

declare(strict_types=1);

namespace app\libraries\routers;

use Attribute;
use InvalidArgumentException;

/**
 * Attribute class for #[Enabled()].
 *
 * Use this to add a check to a controller to see if that
 * feature is enabled within the Config. Whatever string
 * gets passed to this attribute is used in a
 * `is{$feature}Enabled` check in the config model.
 *
 * Example Usage:
 *
 * ```php
 * [Enabled(feature: "forum")]
 * class ForumController {}
 * ```
 */
#[Attribute]
class Enabled {
    /** @var string */
    private $feature;

    public function __construct(?string $feature = null) {
        if (empty($feature)) {
            throw new InvalidArgumentException('Must have non-empty string "feature" for Enabled attribute');
        }
        $this->feature = $feature;
    }

    public function getFeature(): string {
        return $this->feature;
    }
}
