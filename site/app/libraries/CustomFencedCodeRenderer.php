<?php

namespace app\libraries;

use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;

class CustomFencedCodeRenderer extends CustomCodeBlockRenderer {
    public function __construct() {
        parent::__construct(FencedCodeRenderer::class);
    }
}
