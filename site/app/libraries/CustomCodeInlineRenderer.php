<?php

namespace app\libraries;

use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;
use League\CommonMark\ElementRendererInterface;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Renderer\CodeRenderer;

class CustomCodeInlineRenderer implements InlineRendererInterface {

    /** @var \League\CommonMark\Inline\Renderer\CodeRenderer */
    protected $baseRenderer;

    public function __construct() {
        $this->baseRenderer = new CodeRenderer();
    }

    public function render(AbstractInline $inline, ElementRendererInterface $htmlRenderer) {
        $element = $this->baseRenderer->render($inline, $htmlRenderer);
        $attrs = [
            "class" => "inline-code"
        ];
        return new HtmlElement('code', $attrs, $element->getContents());
    }
}
