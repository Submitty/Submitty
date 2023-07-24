<?php

namespace app\libraries;

use League\CommonMark\Block\Renderer\IndentedCodeRenderer;

class CustomIndentedCodeRenderer extends CustomCodeBlockRenderer {
    public function __construct() {
        parent::__construct(IndentedCodeRenderer::class);
    }
}
