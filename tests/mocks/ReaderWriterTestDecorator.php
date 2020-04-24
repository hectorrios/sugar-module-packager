<?php


namespace SugarModulePackager\Test\Mocks;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SugarModulePackager\ReaderWriter;

class ReaderWriterTestDecorator implements ReaderWriter
{

    /* @var ReaderWriter */
    private $innerReaderWriter;

    /* @var array */
    private $pathMappings = array();

    /**
     * ReaderWriterTestDecorator constructor.
     * @param ReaderWriter $readerWriter
     */
    public function __construct(ReaderWriter $readerWriter)
    {
        $this->innerReaderWriter = $readerWriter;
    }

    public function writeFile($filename, $content = '')
    {
       $this->innerReaderWriter->writeFile($filename, $content);
    }

    public function readFile($filename = '')
    {
        return $this->innerReaderWriter->readFile($filename);
    }

    public function copyFile($src, $dst)
    {
       $this->innerReaderWriter->copyFile($src, $dst);
    }

    public function createDirectory($directory = '')
    {
       $this->innerReaderWriter->createDirectory($directory);
    }

    public function resolvePath($path = '')
    {
        echo 'decorator resolvePath:: The path param value is: ' . PHP_EOL;
        if (!array_key_exists($path, $this->pathMappings)) {
            echo 'Ooops we hit a path not resolved in the decorator.' . PHP_EOL;
           return false;
        }

        return $this->pathMappings[$path];
    }

    public function addPathMapping($relativePath, $absPath)
    {
        $this->pathMappings[$relativePath] = $absPath;
    }

    public function copyDirectory($srcDir, $destDir, ...$filesToExclude)
    {
        $this->innerReaderWriter->copyDirectory($srcDir, $destDir, $filesToExclude);
    }

    public function getFilesFromDirectory($srcDir, ...$filesToExclude)
    {
       return $this->innerReaderWriter->getFilesFromDirectory($srcDir, ...$filesToExclude);
    }

}