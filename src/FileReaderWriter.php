<?php


namespace SugarModulePackager;


class FileReaderWriter
{

    /* @var string */
    private $baseDirectory;

    /**
     * FileReaderWriter constructor.
     * @param string $baseDirectory
     */
    public function __construct($baseDirectory = '')
    {
        $this->baseDirectory = $baseDirectory;
    }

    public function writeFile($filename = '', $content = '')
    {
        if (!empty($filename)) {
            $filename = $this->constructPathWithBase($filename);
            file_put_contents($filename, $content);
        }
    }

    public function readFile($filename = '')
    {
        if (empty($filename)) {
            return '';
        }

        $filename = $this->constructPathWithBase($filename);
        if (!file_exists($filename)) {
           return '';
        }

        return file_get_contents($filename);
    }

    public function copyFile($src, $dst)
    {
        if (!empty($src) && !empty($dst)) {
            copy($src, $dst);
        }
    }

    public function createDirectory($directory = '')
    {
        if (!empty($directory)) {
            //construct the dir taking baseDirectory into account
            $directory = $this->constructPathWithBase($directory);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }

    /**
     * @param string $fragment The directory or file to add to the base directory
     * @return string Full-path with the baseDirectory
     */
    private function constructPathWithBase($fragment)
    {
        $fullPath = '';
        if (empty($this->baseDirectory)) {
            $fullPath = $fragment;
        } else {
            $fullPath = $this->baseDirectory . DIRECTORY_SEPARATOR . $fragment;
        }

        return $fullPath;
    }

}