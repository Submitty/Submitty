<?php

namespace app\libraries;
use Aptoma\Twig\Extension\MarkdownEngine\ParsedownEngine;


class CustomParsedownEngine extends ParsedownEngine
{
    /**
     * @param string|null $instanceName
     */
    public function __construct($instanceName = null) {
        parent::__construct($instanceName);
        $this->engine->setSafeMode(true);
    }
}