<?php

namespace PhpToZephir\Converter\Printer\Expr;

use PhpToZephir\Converter\Dispatcher;
use PhpToZephir\Logger;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpToZephir\NodeFetcher;

class ClosurePrinter
{
    /**
     * @var Dispatcher
     */
    private $dispatcher = null;
    /**
     * @var Logger
     */
    private $logger = null;

    private static $converted = [];

    /**
     * @param Dispatcher $dispatcher
     * @param Logger $logger
     */
    public function __construct(Dispatcher $dispatcher, Logger $logger)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'pExpr_Closure';
    }

    /**
     * @param Expr\Closure $node
     *
     * @return string
     */
    public function convert(Expr\Closure $node)
    {
        $methodName = $this->dispatcher->getMetadata()->getClass() . ucfirst($this->dispatcher->getLastMethod());
        if (isset(self::$converted[$methodName])) {
            ++self::$converted[$methodName];
        } else {
            self::$converted[$methodName] = 1;
        }

        $name = $methodName . 'Closure' . $this->N2L(self::$converted[$methodName]);

        $this->logger->logNode(
            sprintf('Closure does not exist in Zephir, class "%s" with __invoke is created', $name),
            $node,
            $this->dispatcher->getMetadata()->getFullQualifiedNameClass()
        );

        return 'new ' . $name . '(' . $this->dispatcher->pCommaSeparated($node->uses) . ')';
    }

    /**
     * @param Expr\Closure $node
     * @param null|string $lastMethod
     * @return array
     */
    public function createClosureClass(Expr\Closure $node, $lastMethod)
    {
        $this->logger->trace(__METHOD__ . ' ' . __LINE__, $node, $this->dispatcher->getMetadata()->getFullQualifiedNameClass());

        self::$converted[$lastMethod] = self::$converted[$lastMethod] ?? 0;
        $name = $this->dispatcher->getMetadata()->getClass() . ucfirst($lastMethod) . 'Closure' . $this->N2L(++self::$converted[$lastMethod]);

        $this->logger->logNode(
            sprintf('Closure does not exist in Zephir, class "%s" with __invoke is created', $name),
            $node,
            $this->dispatcher->getMetadata()->getFullQualifiedNameClass()
        );

        return [
            'name' => $name,
            'code' => $this->createClass($name, $this->dispatcher->getMetadata()->getNamespace(), $node),
        ];
    }

    /**
     * @param string $name
     * @param string $namespace
     * @param Expr\Closure $node
     * @return string
     */
    private function createClass($name, $namespace, Expr\Closure $node)
    {
        $class = "namespace $namespace;

class $name
{
";

        foreach ($node->uses as $use) {
            $class .= '    private ' . $use->var . ";\n";
        }

        $class .= '
    public function __construct(' . (!empty($node->uses) ? '' . $this->dispatcher->pCommaSeparated($node->uses) : '') . ')
    {
';
        foreach ($node->uses as $use) {
            $class .= '        let this->' . $use->var . ' = ' . $use->var . ";\n";
        }
        $class .= '    }

    public function __invoke(' . $this->dispatcher->pCommaSeparated($node->params) . ')
    {'
            . preg_replace('~\n(?!$|' . Dispatcher::noIndentToken . ')~', "\n    ",
                $this->dispatcher->pStmts($this->convertUseToMemberAttribute($node->stmts, $node->uses))) . '
    }
}
    ';

        return $class;
    }

    /**
     * @param Node[] $node
     * @param Expr\ClosureUse[] $uses
     * @return Node[]
     */
    private function convertUseToMemberAttribute($node, $uses)
    {
        $noFetcher = new NodeFetcher();

        foreach ($noFetcher->foreachNodes($node) as &$stmt) {
            if ($stmt['node'] instanceof Expr\Variable) {
                foreach ($uses as $use) {
                    if ($use->var === $stmt['node']->name) {
                        $stmt['node']->name = 'this->' . $stmt['node']->name;
                    }
                }
            }
        }

        return $node;
    }

    /**
     * @param int $number
     * @return string
     */
    private function N2L($number)
    {
        $result = [];
        $tens = floor($number / 10);
        $units = $number % 10;

        $words = [
            'units' => ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eightteen', 'Nineteen'],
            'tens' => ['', '', 'Twenty', 'Thirty', 'Fourty', 'Fifty', 'Sixty', 'Seventy', 'Eigthy', 'Ninety'],
        ];

        if ($tens < 2) {
            $result[] = $words['units'][$tens * 10 + $units];
        } else {
            $result[] = $words['tens'][$tens];

            if ($units > 0) {
                $result[count($result) - 1] .= '-' . $words['units'][$units];
            }
        }

        if (empty($result[0])) {
            $result[0] = 'Zero';
        }

        return trim(implode(' ', $result));
    }
}
