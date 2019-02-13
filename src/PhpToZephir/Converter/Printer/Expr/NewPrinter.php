<?php

namespace PhpToZephir\Converter\Printer\Expr;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpToZephir\Converter\SimplePrinter;

class NewPrinter extends SimplePrinter
{
    public static function getType()
    {
        return 'pExpr_New';
    }

    public function convert(Expr\New_ $node)
    {
        if ($node->class instanceof Variable) {
            return 'new {'.$this->dispatcher->p($node->class).'}('.$this->dispatcher->pCommaSeparated($node->args).')';
        }

        return 'new '.$this->dispatcher->p($node->class).'('.$this->dispatcher->pCommaSeparated($node->args).')';
    }
}
