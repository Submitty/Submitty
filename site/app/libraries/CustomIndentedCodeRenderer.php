<?php

namespace app\libraries;

use League\CommonMark\Extension\CommonMark\Renderer\Block\IndentedCodeRenderer;

class CustomIndentedCodeRenderer extends CustomCodeBlockRenderer {
    public function __construct() {
        parent::__construct(IndentedCodeRenderer::class);
    }
}
