<?php

namespace app\libraries;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\CodeRenderer;

class CustomCodeInlineRenderer implements NodeRendererInterface {
    /** @var CodeRenderer */
    protected $baseRenderer;

    public function __construct() {
        $this->baseRenderer = new CodeRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer) {
        $element = $this->baseRenderer->render($node, $childRenderer);
        $attrs = [
            "class" => "inline-code"
        ];
        return new HtmlElement('code', $attrs, $element->getContents());
    }
}
