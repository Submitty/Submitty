<?php

namespace app\libraries;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class ExtractFunction extends FunctionNode {
    public $field;
    public $source;

    public function parse(Parser $parser) {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->field = $parser->getLexer()->lookahead['value'];
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_COMMA);
        $this->source = $parser->SimpleArithmeticExpression();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker) {
        return sprintf(
            'EXTRACT(%s FROM %s)',
            $this->field,
            $this->source->dispatch($sqlWalker)
        );
    }
}

