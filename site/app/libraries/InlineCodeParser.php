<?php

namespace app\libraries;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class TwitterHandleParser implements InlineParserInterface {

    public function getMatchDefinition(): InlineParserMatch {
        return InlineParserMatch::regex(`(.*?)`);
    }

    public function parse(InlineParserContext $inlineContext): bool {

        return true;
    }

}