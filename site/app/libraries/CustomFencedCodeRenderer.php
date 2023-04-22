<?php

namespace app\libraries;

use League\CommonMark\Block\Renderer\FencedCodeRenderer;

class CustomFencedCodeRenderer extends CustomCodeBlockRenderer {
    public function __construct() {
        parent::__construct(FencedCodeRenderer::class);
    }
}
