<?php

namespace PhpToZephir\Converter\Printer\Expr\Cast;

use PhpParser\Node\Expr\Cast;
use PhpToZephir\Converter\SimplePrinter;

class ObjectPrinter extends SimplePrinter
{
    public static function getType()
    {
        return 'pExpr_Cast_Object';
    }

    public function convert(Cast\Object_ $node)
    {
        return $this->dispatcher->pPrefixOp('Expr_Cast_Object', '(object) ', $node->expr);
    }
}
