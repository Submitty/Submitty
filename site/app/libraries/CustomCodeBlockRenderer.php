<?php

namespace app\libraries;

use League\CommonMark\Block\Element\AbstractBlock;
use League\CommonMark\Block\Renderer\BlockRendererInterface;
use League\CommonMark\ElementRendererInterface;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;
use League\CommonMark\HtmlElement;

class CustomCodeBlockRenderer implements BlockRendererInterface {

    /** @var \League\CommonMark\Block\Renderer\IndentedCodeRenderer|\League\CommonMark\Block\Renderer\FencedCodeRenderer */
    protected $baseRenderer;

    public function __construct($baseRenderer) {
        $this->baseRenderer = new $baseRenderer(['default']);
    }

    public function render(AbstractBlock $block, ElementRendererInterface $htmlRenderer, bool $inTightList = false) {
        $element = $this->baseRenderer->render($block, $htmlRenderer, $inTightList);
        $num_lines = substr_count($element->getContents(), "\n");
        $element->setContents($this->addLineNumbers($element, $num_lines));
        return $element;
    }

    private function addLineNumbers(HtmlElement $element, int $num_lines) {
        if ($num_lines < 5) {
            return $element->getContents();
        }
        $line_numbers_content = "";
        for ($num = 1; $num <= $num_lines; $num++) {
            $line_numbers_content .= strval($num) . "\n";
        }
        $line_numbers_pre = new HtmlElement('pre', ['class' => 'line-numbers'], $line_numbers_content);
        return $element . $line_numbers_pre;
    }
}
