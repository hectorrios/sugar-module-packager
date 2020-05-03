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
     * Copies the contents of the source directory, including sub-directories, over to the
     * destination directory. Any files passed in via the $filesToExclude variadic parameter will
     * be excluded from the copy over to the destination directory.
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