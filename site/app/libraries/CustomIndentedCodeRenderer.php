<?php

namespace app\libraries;

use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Extension\CommonMark\Renderer\Block\IndentedCodeRenderer;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;

class CustomIndentedCodeRenderer implements NodeRendererInterface {
    /** @var IndentedCodeRenderer */
    protected $baseRenderer;

    public function __construct() {
        $this->baseRenderer = new IndentedCodeRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer) {
        if (!$node instanceof IndentedCode) {
            throw new \InvalidArgumentException('Invalid node type');
        }
        return $this->baseRenderer->render($node, $childRenderer);
    }
}
