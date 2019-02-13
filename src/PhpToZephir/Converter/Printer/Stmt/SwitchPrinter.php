<?php

namespace PhpToZephir\Converter\Printer\Stmt;

use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr\BinaryOp;
use PhpToZephir\Converter\SimplePrinter;

class SwitchPrinter extends SimplePrinter
{
    /**
     * @return string
     */
    public static function getType()
    {
        return 'pStmt_Switch';
    }

    /**
     * @param Stmt\Switch_ $node
     *
     * @return string
     * @throws \Exception
     */
    public function convert(Stmt\Switch_ $node)
    {
        $transformToIf = false;
        foreach ($node->cases as $case) {
            if (($case->cond instanceof Scalar\String_) === false && $case->cond !== null) {
                $transformToIf = true;
            }
        }

        if ($transformToIf === true) {
            return $this->convertSwitchToIfelse($node);
        } else {
            return 'switch ('.$this->dispatcher->p($node->cond).') {'
             .$this->dispatcher->pStmts($node->cases)."\n".'}';
        }
    }

    /**
     * @param $case
     * @return Node\Expr
     */
    private function removeBreakStmt($case)
    {
        if (is_array($case->stmts) && !empty($case->stmts)) {
            $key = array_keys($case->stmts);
            $breakStmt = $case->stmts[end($key)];
            if ($breakStmt instanceof Stmt\Break_) {
                unset($case->stmts[end($key)]);
            }
        }

        return $case;
    }

    /**
     * @param Stmt\Switch_ $node
     *
     * @return string
     */
    private function convertSwitchToIfelse(Stmt\Switch_ $node)
    {
        $stmt = array(
            'else' => null,
            'elseifs' => array(),
        );
        $if = null;
        $ifDefined = false;
        $left = null;
        foreach ($node->cases as $case) {
            $case = $this->removeBreakStmt($case);
            if (end($node->cases) === $case) {
                $stmt['else'] = new Stmt\Else_($case->stmts);
            } else {
                if (empty($case->stmts)) { // concatene empty statement
                    if ($left !== null) {
                        $left = new BinaryOp\BooleanOr($left, $case->cond);
                    } else {
                        $left = $case->cond;
                    }
                } elseif ($ifDefined === false) {
                    if ($left !== null) {
                        $lastLeft = new BinaryOp\BooleanOr($left, $case->cond);
                        $if = new Stmt\If_($lastLeft, array('stmts' => $case->stmts));
                        $left = null;
                    } else {
                        $if = new Stmt\If_($case->cond, array('stmts' => $case->stmts));
                    }
                    $ifDefined = true;
                } else {
                    if ($left !== null) {
                        $lastLeft = new BinaryOp\BooleanOr($left, $case->cond);
                        $stmt['elseifs'][] = new Stmt\ElseIf_($lastLeft, $case->stmts);
                        $left = null;
                    } else {
                        $stmt['elseifs'][] = new Stmt\ElseIf_($case->cond, $case->stmts);
                    }
                }
            }
        }
        $elseifs = array_reverse($stmt['elseifs']);
        $if = new Stmt\If_($if->cond, array(
            'stmts' => $if->stmts,
            'elseifs' => $elseifs,
            'else' => $stmt['else'],
        ));

        return $this->dispatcher->pStmt_If($if);
    }
}
