<?php

namespace PhpToZephir\Converter\Printer\Expr;

use PhpParser\Node\Expr;
use PhpToZephir\Converter\SimplePrinter;

class ArrayItemPrinter extends SimplePrinter
{
    public static function getType()
    {
        return "pExpr_ArrayItem";
    }

    public function convert(Expr\ArrayItem $node)
    {
        $this->logger->trace(__METHOD__.' '.__LINE__, $node, $this->dispatcher->getMetadata()->getFullQualifiedNameClass());

        return (null !== $node->key ? $this->dispatcher->p($node->key).' : ' : '')
             .($node->byRef ? '&' : '').$this->dispatcher->p($node->value);
    }
}