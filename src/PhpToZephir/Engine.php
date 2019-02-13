<?php

namespace PhpToZephir;

use PhpParser\Parser;
use PhpToZephir\Converter\Converter;
use PhpToZephir\CodeCollector\CodeCollectorInterface;

class Engine
{
    /**
     * @var Parser
     */
    private $parser = null;
    /**
     * @var Converter
     */
    private $converter = null;
    /**
     * @var ClassCollector
     */
    private $classCollector = null;

    /**
     * @param Parser $parser
     * @param Converter $converter
     * @param ClassCollector $classCollector
     */
    public function __construct(
        Parser $parser,
        Converter $converter,
        ClassCollector $classCollector
    )
    {
        $this->parser = $parser;
        $this->converter = $converter;
        $this->classCollector = $classCollector;
    }

    /**
     * @param CodeCollectorInterface $codeCollector
     * @param Logger $logger
     * @param string $filterFileName
     *
     * @return array
     */
    public function convert(CodeCollectorInterface $codeCollector, Logger $logger, $filterFileName = null)
    {
        $zephirCode = [];
        $classes = [];

        $files = $codeCollector->getCode();
        $count = count($files);
        $codes = [];

        $logger->log('Collect class names');
        $progress = $logger->progress($count);

        foreach ($files as $fileName => $fileContent) {
            try {
                $codes[$fileName] = $this->parser->parse($fileContent);
                $classes[$fileName] = $this->classCollector->collect($codes[$fileName], $fileName);
            } catch (\Exception $e) {
                $logger->log(
                    sprintf(
                        '<error>Could not convert file' . "\n" . '"%s"' . "\n" . 'cause : %s %s %s</error>' . "\n",
                        $fileName,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    )
                );
            }
            $progress->advance();
        }

        $progress->finish();

        $logger->log("\nConvert php to zephir");
        $progress = $logger->progress(count($classes));

        foreach ($classes as $phpFile => $class) {
            if ($filterFileName !== null) {
                if (basename($phpFile, '.php') !== $filterFileName) {
                    continue;
                }
            }

            $phpCode = $codes[$phpFile];
            $fileName = basename($phpFile, '.php');
            try {
                $converted = $this->convertCode($phpCode, $this->classCollector, $logger, $phpFile, $classes);
                $converted['class'] = $class;
            } catch (\Exception $e) {
                $logger->log(
                    sprintf(
                        'Could not convert file "%s" cause : %s %s %s' . "\n",
                        $phpFile,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    )
                );
                $progress->advance();
                continue;
            }

            $zephirCode[$phpFile] = array_merge(
                $converted,
                [
                    'phpPath' => substr($phpFile, 0, strrpos($phpFile, '/')),
                    'fileName' => $fileName,
                    'fileDestination' => $converted['class'] . '.zep',
                ]
            );

            $zephirCode[$phpFile]['fileDestination'] = str_replace('\\', '/', $zephirCode[$phpFile]['fileDestination']);

            foreach ($converted['additionalClass'] as $aditionalClass) {
                $zephirCode[$phpFile . $aditionalClass['name']] = array_merge(
                    [
                        'fileName' => $aditionalClass['name'],
                        'zephir' => $aditionalClass['code'],
                        'fileDestination' => str_replace('\\', '/', $converted['namespace']) . '/' . $aditionalClass['name'] . '.zep',
                        'destination' => str_replace('\\', '/', $converted['namespace']) . '/',
                    ]
                );
            }

            $progress->advance();
        }

        $progress->finish();
        $logger->log("\n");

        return $zephirCode;
    }

    /**
     * @param string|array $phpCode
     * @param ClassCollector $classCollector
     * @param Logger $logger
     * @param string|null $fileName
     * @param array $classes
     * @return array
     * @throws \Exception
     */
    private function convertCode($phpCode, ClassCollector $classCollector, Logger $logger, $fileName = null, array $classes = [])
    {
        $converted = $this->converter->nodeToZephir($phpCode, $classCollector, $logger, $fileName, $classes);

        return [
            'zephir' => $converted['code'],
            'php' => $phpCode,
            'namespace' => $converted['namespace'],
            'destination' => str_replace('\\', '/', $converted['namespace']) . '/',
            'additionalClass' => $converted['additionalClass'],
        ];
    }
}
