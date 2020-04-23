<?php


namespace SugarModulePackager;


interface ReaderWriter
{
    public function writeFile($filename, $content = '');

    /**
     * @param string $filename
     * @return string|false
     */
    public function readFile($filename = '');

    public function copyFile($src, $dst);

    public function createDirectory($directory = '');

    /**
     * @param string $path
     * @return string|false
     */
    public function resolvePath($path = '');

    /**
     * @param string $srcDir
     * @param string $destDir
     * @param string ...$filesToExclude
     * @return void
     */
    public function copyDirectory($srcDir, $destDir, ...$filesToExclude);

    /**
     * @param $srcDir
     * @param string ...$filesToExclude
     * @return array
     */
    public function getFilesFromDirectory($srcDir, ...$filesToExclude);
}