<?php

namespace PhpToZephir;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar\MagicConst;

class ClassCollector
{
    /**
     * @var NodeFetcher
     */
    private $nodeFetcher = null;
    /**
     * @var ReservedWordReplacer
     */
    private $reservedWordReplacer = null;
    /**
     * @var array
     */
    private $collected = [];

    /**
     * @param NodeFetcher $nodeFetcher
     * @param ReservedWordReplacer $reservedWordReplacer
     */
    public function __construct(NodeFetcher $nodeFetcher, ReservedWordReplacer $reservedWordReplacer)
    {
        $this->nodeFetcher = $nodeFetcher;
        $this->reservedWordReplacer = $reservedWordReplacer;
    }

    /**
     * @param Node[] $stmts
     * @param mixed $fileName
     *
     * @return string
     * @throws \Exception
     */
    public function collect(array $stmts, $fileName)
    {
        $namespace = null;
        $class = null;

        foreach ($this->nodeFetcher->foreachNodes($stmts) as $nodeData) {
            $node = $nodeData['node'];
            if ($node instanceof Stmt\Goto_) {
                throw new \Exception('Goto not supported in ' . $fileName . ' on line ' . $node->getLine());
            } elseif ($node instanceof Stmt\InlineHTML) {
                throw new \Exception('InlineHTML not supported in ' . $fileName . ' on line ' . $node->getLine());
            } elseif ($node instanceof Stmt\HaltCompiler) {
                throw new \Exception('HaltCompiler not supported in ' . $fileName . ' on line ' . $node->getLine());
            } elseif ($node instanceof MagicConst\Trait_) {
                throw new \Exception('MagicConst\Trait_ not supported in ' . $fileName . ' on line ' . $node->getLine());
            } elseif ($node instanceof Stmt\Trait_) {
                throw new \Exception('Trait not supported in ' . $fileName . ' on line ' . $node->getLine());
            } elseif ($node instanceof Stmt\Namespace_ && !empty($node->name)) {
                $namespace = implode('\\', $node->name->parts);
            } elseif ($node instanceof Stmt\Interface_ || $node instanceof Stmt\Class_) {
                if ($class !== null) {
                    throw new \Exception('Multiple class find in ' . $fileName);
                }
                $class = $namespace . '\\' . $this->reservedWordReplacer->replace($node->name);
            }
        }

        if ($namespace === null) {
            throw new \Exception('Namespace not found in ' . $fileName);
        }

        if ($class === null) {
            throw new \Exception('No class found in ' . $fileName);
        }

        $this->collected[$class] = $stmts;

        return $class;
    }

    /**
     * @return array
     */
    public function getCollected()
    {
        return $this->collected;
    }
}
