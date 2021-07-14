<?php

namespace app\libraries;

use app\libraries\CustomCodeRenderer;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;

class CustomFencedCodeRenderer extends CustomCodeRenderer {
    public function __construct() {
        parent::__construct(FencedCodeRenderer::class);
    }
}
