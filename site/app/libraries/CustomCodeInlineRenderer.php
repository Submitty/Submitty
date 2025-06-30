<?php

namespace app\libraries;

use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\CodeRenderer;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Util\HtmlElement;
use Stringable;

class CustomCodeInlineRenderer implements NodeRendererInterface {
    /** @var CodeRenderer */
    protected $baseRenderer;

    public function __construct() {
        $this->baseRenderer = new CodeRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable {
        if (!$node instanceof Code) {
            throw new \InvalidArgumentException('Invalid node type');
        }

        $attrs = [
            "class" => "inline-code"
        ];
        $content = htmlspecialchars($node->getLiteral(), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return new HtmlElement('code', $attrs, $content);
    }
}
