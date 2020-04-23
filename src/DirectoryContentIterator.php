<?php


namespace SugarModulePackager;


use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait DirectoryContentIterator
{
    /**
     * @param $path string should be a valid path existing path
     * @return RecursiveIteratorIterator
     * @throws InvalidArgumentException
     */
    protected function getDirectoryContentIterator($path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException('the path: ' . $path .
                'does not exist. Please verify that it is correct');
        }

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path,
                RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }
}