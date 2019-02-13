<?php

namespace PhpToZephir\Converter\Printer\Stmt;

use PhpToZephir\Converter\Dispatcher;
use PhpToZephir\Logger;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpToZephir\Converter\Manipulator\AssignManipulator;

class IfPrinter
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
     * @var AssignManipulator
     */
    private $assignManipulator = null;

    /**
     * @param Dispatcher        $dispatcher
     * @param Logger            $logger
     * @param AssignManipulator $assignManipulator
     */
    public function __construct(Dispatcher $dispatcher, Logger $logger, AssignManipulator $assignManipulator)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->assignManipulator = $assignManipulator;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'pStmt_If';
    }

    /**
     * @param Stmt\If_ $node
     *
     * @return string
     * @throws \Exception
     */
    public function convert(Stmt\If_ $node)
    {
        $collected = $this->assignManipulator->collectAssignInCondition($node->cond);
        $node->cond = $this->assignManipulator->transformAssignInConditionTest($node->cond);

        if (empty($node->stmts)) {
            $node->stmts = array(new Stmt\Echo_(array(new Scalar\String_('not allowed'))));
            $this->logger->logIncompatibility(
                'Empty if',
                'Empty if not allowed, add "echo not allowed"',
                $node,
                $this->dispatcher->getMetadata()->getFullQualifiedNameClass()
            );
        }

        return $collected->getCollected() .
               'if '.$this->dispatcher->p($node->cond).' {'
             .$this->dispatcher->pStmts($node->stmts)."\n".'}'
             .$this->implodeElseIfs($node);
    }

    /**
     * @param Stmt\If_ $node
     *
     * @return string
     * @throws \Exception
     */
    private function implodeElseIfs(Stmt\If_ $node)
    {
        $elseCount = 0;
        $toReturn = '';
        foreach ($node->elseifs as $elseIf) {
            $collected = $this->assignManipulator->collectAssignInCondition($elseIf->cond);
            if ($collected->hasCollected()) {
                ++$elseCount;
                $toReturn .= ' else {'."\n".$this->dispatcher->p(new Stmt\If_($elseIf->cond, array('stmts' => $elseIf->stmts)))."\n";
            } else {
                $toReturn .= $this->dispatcher->pStmt_ElseIf($elseIf);
            }
        }
        $toReturn .= (null !== $node->else ? $this->dispatcher->p($node->else) : '');

        return $toReturn.str_repeat('}', $elseCount);
    }
}
