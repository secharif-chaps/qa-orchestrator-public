<?php

namespace App\Infrastructure\Persistence\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class UnaccentFunction extends FunctionNode
{
    /**
     * @var Node|string
     */
    public $field;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->field = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        if ($this->field instanceof Node) {
            $fieldSql = $this->field->dispatch($sqlWalker);
        } else {
            $fieldSql = (string) $this->field;
        }

        return \sprintf('unaccent(%s::text)', $fieldSql);
    }
}
