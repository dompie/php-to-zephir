<?php

namespace PhpToZephir\Converter\Printer\Expr\AssignOp;

use PhpParser\Node\Expr\AssignOp;
use PhpToZephir\Converter\SimplePrinter;

class PowPrinter extends SimplePrinter
{
    public static function getType()
    {
        return 'pExpr_AssignOp_Pow';
    }

    public function convert(AssignOp\Pow $node)
    {
        return $this->dispatcher->pInfixOp('Expr_AssignOp_Pow', $node->var, ' **= ', $node->expr);
    }
}
