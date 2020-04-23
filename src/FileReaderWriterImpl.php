<?php


namespace SugarModulePackager;


use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileReaderWriterImpl implements ReaderWriter
{

    /* @var string */
    private $baseDirectory;

    /**
     * FileReaderWriterImpl constructor.
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

    public function resolvePath($path = '')
    {
        return realpath($path);
    }

    public function copyDirectory($srcDir, $destDir, ...$filesToExclude)
    {
        $common_files_list = $this->getFilesFromDirectory($srcDir);
        if (!empty($common_files_list)) {
            foreach ($common_files_list as $file_relative => $file_realpath) {
                $destination_directory = $destDir . DIRECTORY_SEPARATOR .
                    dirname($file_relative) . DIRECTORY_SEPARATOR;

                $this->createDirectory($destination_directory);
                $this->copyFile($file_realpath,
                    $destination_directory . basename($file_realpath));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getFilesFromDirectory($srcDir, ...$filesToExclude)
    {
        //a check for the possible case that resolving the path may have produced False
        if ($srcDir === false) {
            throw new InvalidArgumentException('the src directory must be a string');
        }

        $files_iterator = $this->getDirectoryContentIterator($srcDir);
        $result = array();
        //$path = realpath($path);

        if (empty($files_iterator) || empty($srcDirectory)) {
            return $result;
        }

        /* @var SplFileInfo $file */
        foreach ($files_iterator as $name => $file) {
            if ($file->isFile()) {
//                $file_realpath = $file->getRealPath();
                $file_realpath = $file->getPathname();

                if (in_array($file->getFilename(), $filesToExclude)) {
                    continue;
                }

                $file_relative = '' . str_replace($srcDirectory . '/', '', $file_realpath);
//                echo 'The file relative value is: ' . $file_relative . PHP_EOL;
                $result[$file_relative] = $file_realpath;
            }
        }

        return $result;
    }

    /**
     * @param $path
     * @return RecursiveIteratorIterator
     */
    protected function getDirectoryContentIterator($path)
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->resolvePath($path),
                RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }


}