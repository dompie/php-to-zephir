<?php

namespace PhpToZephir;

class ReservedWordReplacer
{
    public function replace($string)
    {
        if ($string === null) {
            return $string;
        }

        $reservedWord = [
            'inline' => 'inlinee',
            'Inline' => 'Inlinee',
            'array' => 'myArray',
            'class' => 'classs',
            'var' => 'varr',
            'bool' => 'booll',
            'namespace' => 'namespacee',
            'const' => 'constt',
            'enum' => 'enumm',
            'interface' => 'interfacee',
            'loop' => 'loopp',
            'for' => 'forr',
            'foreach' => 'foreachh',
            'if' => 'iff',
            'elseif' => 'elseiff',
            'else' => 'elsee',
            'function' => 'functionn',
            'private' => 'privatee',
            'protected' => 'protectedd',
            'public' => 'publicc',
            'boolean' => 'booleann',
            'return' => 'returnn',
            'abstract' => 'abstractt',
            'resource' => 'resourcee',
            'callable' => 'callablee',
            'string' => 'stringg',
            'float' => 'floatt',
            'int' => 'intt',
            'internal' => 'internall',
            'deprecated' => 'deprecatedd'
        ];

        foreach ($reservedWord as $word => $replacement) {
            if ($string == $word) {
                $string = $replacement;
                break;
            }
        }

        if (ctype_upper($string)) {
            $string = strtolower($string);
        }

        return $string;
    }
}
