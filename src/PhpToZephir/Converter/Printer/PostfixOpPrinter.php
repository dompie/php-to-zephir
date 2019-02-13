<?php

namespace PhpToZephir\Converter\Printer;

use PhpParser\Node;
use PhpToZephir\Converter\SimplePrinter;

class PostfixOpPrinter extends SimplePrinter
{
    public static function getType()
    {
        return 'pPostfixOp';
    }

    /**
     * Pretty prints an array of nodes (statements) and indents them optionally.
     *
     * @param $type
     * @param Node $node Array of nodes
     *
     * @param $operatorString
     * @return string Pretty printed statements
     */
    public function convert($type, Node $node, $operatorString)
    {
        list($precedence, $associativity) = $this->dispatcher->getPrecedenceMap($type);

        return $this->dispatcher->pPrec($node, $precedence, $associativity, -1) . $operatorString;
    }
}
