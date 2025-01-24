<?php

declare(strict_types=1);

namespace app\libraries\routers;

use InvalidArgumentException;

/**
 * Annotation class for @ Enabled().
 *
 * Use this to add a check to a controller to see if that
 * feature is enabled within the Config. Whatever string
 * gets passed to this annotation is used in a
 * `is{$feature}Enabled` check in the config model.
 *
 * Example Usage:
 *
 * ```php
 * @Enabled("forum")
 * class ForumController {}
 * ```
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class Enabled {
    /** @var string */
    private $feature;

    public function __construct(array $data) {
        if (empty($data['value']) || !is_string($data['value'])) {
            throw new InvalidArgumentException('Must have non-empty string "value" for Enabled annotation');
        }
        $this->feature = $data['value'];
    }

    public function getFeature(): string {
        return $this->feature;
    }
}
