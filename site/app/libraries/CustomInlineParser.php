<?php

namespace app\libraries;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class CustomInlineParser implements InlineParserInterface {

    public function getMatchDefinition(): InlineParserMatch {
        return InlineParserMatch::regex(`(.*?)`);
    }

    public function parse(InlineParserContext $inlineContext): bool {

        //Add classes for inline code
        [$content] = $inlineContext->getSubMatches();

        $inlineContext->getContainer()->appendChild(new Link("https://google.com"));

        return true;
    }

}