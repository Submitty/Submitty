<?php

namespace app\libraries;

use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;

class CustomFencedCodeRenderer implements NodeRendererInterface {
    /** @var FencedCodeRenderer */
    protected $baseRenderer;

    public function __construct() {
        $this->baseRenderer = new FencedCodeRenderer();
    }

    /**
     * @return \Stringable|string|null
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer) {
        if (!$node instanceof FencedCode) {
            throw new \InvalidArgumentException('Invalid node type');
        }
        return $this->baseRenderer->render($node, $childRenderer);
    }
}
