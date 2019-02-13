<?php

namespace PhpToZephir\Converter\Printer\Expr;

use PhpToZephir\Converter\Dispatcher;
use PhpToZephir\Logger;
use PhpParser\Node\Expr;
use PhpToZephir\ReservedWordReplacer;

class VariablePrinter
{
    /**
     * @var Dispatcher
     */
    private $dispatcher = null;
    /**
     * @var Logger
     */
    private $logger = null;
    /**
     * @var ReservedWordReplacer
     */
    private $reservedWordReplacer = null;

    /**
     * @param Dispatcher           $dispatcher
     * @param Logger               $logger
     * @param ReservedWordReplacer $reservedWordReplacer
     */
    public function __construct(Dispatcher $dispatcher, Logger $logger, ReservedWordReplacer $reservedWordReplacer)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->reservedWordReplacer = $reservedWordReplacer;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'pExpr_Variable';
    }

    /**
     * @param Expr\Variable $node
     *
     * @return string
     * @throws \Exception
     */
    public function convert(Expr\Variable $node)
    {
        if ($node->name instanceof Expr) {
            return '{'.$this->dispatcher->p($node->name).'}';
        } else {
            return ''.$this->reservedWordReplacer->replace($node->name);
        }
    }
}
