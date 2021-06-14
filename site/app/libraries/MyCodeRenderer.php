<?php

namespace app\libraries;

use app\libraries\Output;
use League\CommonMark\Environment;
use League\CommonMark\Block\Element\AbstractBlock;
use League\CommonMark\Block\Renderer\BlockRendererInterface;
use League\CommonMark\Block\Element\FencedCode;
use League\CommonMark\ElementRendererInterface;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use League\CommonMark\HtmlElement;

class MyCodeRenderer implements BlockRendererInterface
{
    public function __construct(){
        $this->baseRenderer = new FencedCodeRenderer();
    }
    public function render(AbstractBlock $block, ElementRendererInterface $htmlRenderer, bool $inTightList = false)
    {
        if(!$block instanceof FencedCode){
            echo "ERRORRRRRRRR NOT FENCED CODE";
        }
        //var_dump($block->getBlock());
        $test = new HtmlElement('div', ['class' => 'test'], 'test div');
        //$block->setLiteral('hellooooo');
        $element = $this->baseRenderer->render($block, $htmlRenderer, $inTightList);
        //$test->insertBefore($element);
        //var_dump($test);
        //var_dump($block);
        return $element->getContents();
    }
}