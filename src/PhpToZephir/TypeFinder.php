<?php

namespace PhpToZephir;

use phpDocumentor\Reflection\DocBlock\Tag\ParamTag as PhpDocParamTag;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;
use phpDocumentor\Reflection\DocBlock\Tag\ReturnTag;
use phpDocumentor\Reflection\DocBlock\Tag\ThrowsTag;
use PhpToZephir\Converter\ClassMetadata;
use PhpParser\Node;

class TypeFinder
{
    /**
     * @var ReservedWordReplacer
     */
    private $reservedWordReplacer = null;
    /**
     * @var Logger
     */
    private $logger = null;
    /**
     * @var ClassCollector
     */
    private $classCollector = null;
    /**
     * @var NodeFetcher
     */
    private $nodeFetcher = null;

    /**
     * @param ReservedWordReplacer $reservedWordReplacer
     * @param Logger $logger
     * @param ClassCollector $classCollector
     * @param NodeFetcher $nodeFetcher
     */
    public function __construct(ReservedWordReplacer $reservedWordReplacer, Logger $logger, ClassCollector $classCollector, NodeFetcher $nodeFetcher)
    {
        $this->reservedWordReplacer = $reservedWordReplacer;
        $this->logger = $logger;
        $this->classCollector = $classCollector;
        $this->nodeFetcher = $nodeFetcher;
    }

    /**
     * @param ClassMethod $node
     * @param ClassMetadata $classMetadata
     * @retur narray
     * @return array
     */
    public function getTypes(ClassMethod $node, ClassMetadata $classMetadata)
    {
        $definition = array();

        $definition = $this->parseParam($node, $classMetadata, $definition);

        $phpdoc = $this->nodeToDocBlock($node);

        return $this->findReturnTag($phpdoc, $definition, $classMetadata, $node);
    }

    /**
     * @param ClassMethod $node
     * @param ClassMetadata $classMetadata
     * @param array $definition
     *
     * @return array
     */
    private function parseParam(ClassMethod $node, ClassMetadata $classMetadata, array $definition)
    {
        if (isset($definition['params']) === false) {
            $definition['params'] = array();
        }

        foreach ($node->params as $param) {
            $params = array();
            $params['name'] = $this->replaceReservedWords($param->name);
            $params['default'] = $param->default;
            $params['type'] = null;

            /* @var $param \PhpParser\Node\Param */
            if ($param->type === 'array') {
                $params['type']['value'] = 'array';
                $params['type']['isClass'] = false;
            } elseif ($param->type === null) { // scalar or not strong typed in method
                $docBlock = $this->nodeToDocBlock($node);
                if ($docBlock !== null) {
                    $params['type'] = $this->foundTypeInCommentForVar($docBlock, $param, $classMetadata);
                }
            } elseif ($param->type instanceof Name) {
                $className = implode('\\', $param->type->parts);
                $params['type']['value'] = $className;
                $params['type']['isClass'] = true;
            }

            $definition['params'][] = $params;
        }

        return $definition;
    }

    /**
     * @param string $string
     * @return mixed|string
     */
    private function replaceReservedWords($string)
    {
        return $this->reservedWordReplacer->replace($string);
    }

    /**
     * @param ClassMethod $node
     *
     * @return null|\phpDocumentor\Reflection\DocBlock
     */
    private function nodeToDocBlock(ClassMethod $node)
    {
        $attribute = $node->getAttributes();

        if (isset($attribute['comments'][0]) === false) {
            return null;
        }

        $docBlock = $attribute['comments'][0]->getText();

        return new DocBlock($docBlock);
    }

    /**
     * @param DocBlock $phpdoc
     * @param Param $param
     * @param ClassMetadata $classMetadata
     * @return null|array
     */
    private function foundTypeInCommentForVar(DocBlock $phpdoc, Param $param, ClassMetadata $classMetadata)
    {
        foreach ($phpdoc->getTags() as $tag) {
            if ($tag instanceof PhpDocParamTag) {
                if ($param->name === substr($tag->getVariableName(), 1)) {
                    if (!empty($tag->getType())) {
                        return $this->findType($tag, $param, $classMetadata);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param DocBlock $phpdoc
     *
     * @param array $definition
     * @param ClassMetadata $classMetadata
     * @param ClassMethod $node
     * @return array
     */
    private function findReturnTag($phpdoc = null, array $definition, ClassMetadata $classMetadata, ClassMethod $node)
    {
        $implements = $classMetadata->getImplements();
        if (is_array($implements) === true) {
            foreach ($implements as $implement) {
                foreach ($this->classCollector->getCollected() as $className => $classInfo) {
                    if ($classMetadata->getNamespace() . '\\' . $implement === $className) {
                        try {
                            $phpdoc = $this->nodeToDocBlock($this->findMethod($classInfo, $node->name));
                        } catch (\InvalidArgumentException $e) {
                        }
                    }
                    foreach ($classMetadata->getClasses() as $use) {
                        if ($use . '/' . $implement === $className) {
                            try {
                                $phpdoc = $this->nodeToDocBlock($this->findMethod($classInfo, $node->name));
                            } catch (\InvalidArgumentException $e) {
                            }
                        }
                    }
                }
            }
        }

        if ($phpdoc !== null) {
            foreach ($phpdoc->getTags() as $tag) {
                if ($this->isReturnTag($tag) === true) {
                    $definition['return'] = array(
                        'type' => $this->findType($tag, $node, $classMetadata),
                    );
                    break;
                }
            }
        }

        return $definition;
    }

    /**
     * @param array $classInfo
     * @param string $name
     *
     * @return \PhpParser\Node\Stmt\ClassMethod
     */
    private function findMethod(array $classInfo, $name)
    {
        foreach ($this->nodeFetcher->foreachNodes($classInfo) as $stmtData) {
            $stmt = $stmtData['node'];
            if ($stmt instanceof ClassMethod && $stmt->name === $name) {
                return $stmt;
            }
        }

        throw new \InvalidArgumentException(sprintf('method %s not found', $name));
    }

    /**
     * @param Tag $tag
     *
     * @return bool
     */
    private function isReturnTag(Tag $tag)
    {
        if ($tag instanceof ReturnTag
            && ($tag instanceof ThrowsTag) === false
            && ($tag instanceof ParamTag) === false
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param Tag $tag
     *
     * @param Node $node
     * @param ClassMetadata $classMetadata
     * @return array
     */
    private function findType(Tag $tag, Node $node, ClassMetadata $classMetadata)
    {
        $rawType = $tag->getType();

        if ($rawType === 'integer') {
            $rawType = 'int';
        }

        $primitiveTypes = array(
            'string',
            'int',
            'integer',
            'float',
            'double',
            'bool',
            'boolean',
            'array',
            'null',
            'callable',
            'scalar',
            'void',
            'object',
        );

        $excludedType = array('mixed', 'callable', 'callable[]', 'scalar', 'scalar[]', 'void', 'object', 'self', 'resource', 'true');

        if (in_array($rawType, $excludedType) === true || count(explode('|', $rawType)) !== 1) {
            return array('value' => '', 'isClass' => false);
        }

        $arrayOfPrimitiveTypes = array_map(function ($val) {
            return $val . '[]';
        }, $primitiveTypes);

        if (class_exists($rawType)) {
            $type = array('value' => $rawType, 'isClass' => true);
        } elseif ($name = $this->isInUse($rawType, $classMetadata)) {
            $type = array('value' => $name, 'isClass' => true);
        } elseif ($name = $this->isInActualNamespaceOrInBase($rawType)) {
            $type = array('value' => $name, 'isClass' => true);
        } elseif (strpos($rawType, '[]')) {
            $type = array('value' => 'array', 'isClass' => false);
        } elseif (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $rawType) === 0) { // this is a typo
            $this->logger->logNode(
                sprintf('Type "%s" does not exist in docblock', $rawType),
                $node,
                $classMetadata->getFullQualifiedNameClass()
            );
            $type = array('value' => '', 'isClass' => false);
        } elseif (in_array(strtolower($rawType), $primitiveTypes)) {
            $type = array('value' => strtolower($rawType), 'isClass' => false);
        } elseif (in_array(strtolower($rawType), $arrayOfPrimitiveTypes)) {
            $type = array('value' => strtolower($rawType), 'isClass' => false);
        } else { // considered as class
            $type = array('value' => $rawType, 'isClass' => true);
        }


        return $type;
    }

    /**
     * @param string $rawType
     * @param ClassMetadata $classMetadata
     * @return string|boolean
     */
    private function isInUse($rawType, ClassMetadata $classMetadata)
    {
        $rawType = substr($rawType, 1);

        foreach ($classMetadata->getClasses() as $use) {
            if (substr($use, -strlen($rawType)) == $rawType && substr(substr($use, -(strlen($rawType) + 1)), 0, 1) === "\\") {
                return $rawType;
            }
        }

        return false;
    }

    private function isInActualNamespaceOrInBase($rawType)
    {
        $type = substr($rawType, 1);

        foreach (array_keys($this->classCollector->getCollected()) as $class) {
            // is in actual namespace ?
            if (substr($class, -strlen($type)) == $type && substr(substr($class, -(strlen($type) + 1)), 0, 1) === "\\") {
                return $type;
            } elseif ($class === $type) {
                return $rawType;
            }
        }

        return false;
    }
}
