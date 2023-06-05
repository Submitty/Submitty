<?php

namespace app\libraries;

use app\libraries\CustomCodeBlockRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;

class CustomIndentedCodeRenderer extends CustomCodeBlockRenderer {
    public function __construct() {
        parent::__construct(IndentedCodeRenderer::class);
    }
}
