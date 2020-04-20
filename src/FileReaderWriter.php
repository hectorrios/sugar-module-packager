<?php


namespace SugarModulePackager;


use InvalidArgumentException;

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

    /**
     * writes the contents provided to the named file. If
     * the file does not exist then it will be created. If the
     * filename represents a path with directories, then the assumption is
     * that the directories should already exist. If they don't then an error will
     * be thrown. InvalidArgumentException is thrown if the filename provided
     * is not given or is the empty string. Additionally, it is also thrown if
     * directories present in the path don't already exist.
     *
     * @param string $filename
     * @param string $content
     * @throws InvalidArgumentException
     */
    public function writeFile($filename, $content = '')
    {
        if (empty($filename)) {
            throw new InvalidArgumentException('filename cannot be empty');
        }

        $filename = $this->constructPathWithBase($filename);
        $dirPart = dirname($filename);
        if (!file_exists($dirPart)) {
            throw new InvalidArgumentException('the portion of the path: ' .
            $dirPart . ' does  not exist');
        }

        file_put_contents($filename, $content);
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
        if (empty($src) || empty($dst)) {
            return;
        }
        $src = $this->constructPathWithBase($src);
        $dst = $this->constructPathWithBase($dst);
        copy($src, $dst);
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