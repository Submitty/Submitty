<?php

namespace app\libraries;

use app\libraries\CustomCodeBlockRenderer;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;

class CustomFencedCodeRenderer extends CustomCodeBlockRenderer {
    public function __construct() {
        parent::__construct(FencedCodeRenderer::class);
    }
}
