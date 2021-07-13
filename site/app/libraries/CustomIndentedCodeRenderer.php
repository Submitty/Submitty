<?php

namespace app\libraries;

use app\libraries\CustomCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;

class CustomIndentedCodeRenderer extends CustomCodeRenderer {
    public function __construct() {
        parent::__construct(IndentedCodeRenderer::class);
    }
}
