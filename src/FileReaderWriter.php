<?php


namespace SugarModulePackager;


class FileReaderWriter
{

    /**
     * FileReaderWriter constructor.
     */
    public function __construct()
    {
    }

    public function writeFile($filename = '', $content = '')
    {
        if (!empty($filename)) {
            file_put_contents($filename, $content);
        }
    }

    public function readFile($filename = '')
    {
        if (!empty($filename) && file_exists($filename)) {
            return file_get_contents($filename);
        }
        return '';
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
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }

}