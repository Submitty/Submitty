<?php

namespace app\libraries;

use League\CommonMark\Extension\CommonMark\Renderer\Block\IndentedCodeRenderer;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Util\HtmlElement;

class CustomCodeBlockRenderer implements NodeRendererInterface {
    /** @var IndentedCodeRenderer|FencedCodeRenderer */
    protected $baseRenderer;

    public function __construct($baseRenderer) {
        $this->baseRenderer = new $baseRenderer(['default']);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer, bool $inTightList = false) {
        $element = $this->baseRenderer->render($node, $childRenderer);
        $num_lines = substr_count($element, "\n");
        return new HtmlElement('div', ["style" => "position: relative;"], $this->addLineNumbers($element, $num_lines));
    }

    private function addLineNumbers(HtmlElement $element, int $num_lines) {
        if ($num_lines < 5) {
            return $element;
        }
        $line_numbers_content = "";
        for ($num = 1; $num <= $num_lines; $num++) {
            $line_numbers_content .= strval($num) . "\n";
        }
        $line_numbers_pre = new HtmlElement('pre', ['class' => 'line-numbers'], $line_numbers_content);
        return $element . $line_numbers_pre;
    }
}
