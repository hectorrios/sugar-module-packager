<?php


namespace SugarModulePackager;


use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PackagerService
{
    use DirectoryContentIterator;

    /* @var ReaderWriter $fileReaderWriterService */
    private $fileReaderWriterService;


    /* @var PackagerConfiguration $config */
    //private $config;

    /**
     * PackagerService constructor.
     * @param ReaderWriter $fileReaderWriterService
     */
    public function __construct(ReaderWriter $fileReaderWriterService)
    {
        $this->fileReaderWriterService = $fileReaderWriterService;
//        $this->config = $config;
    }

    public function createPackagerDirectories(...$directories)
    {
        foreach ($directories as $directory) {
            $this->fileReaderWriterService->createDirectory($directory);
        }
    }

    /**
     * @param $pathToManifestFile
     * @return array containing the array contents of the manifest.php file.
     * @throws ManifestIncompleteException
     */
    public function getManifestFileContents($pathToManifestFile)
    {
        $manifest = array();

        //if (!file_exists($this->buildSimplePath($pathToManifestFile))) {
        if (!file_exists($pathToManifestFile)) {
            throw new ManifestIncompleteException('Manifest at path: ' . $pathToManifestFile . ' does not exist');
        }

        require($pathToManifestFile);

        if ( empty($manifest['id']) ||
            empty($manifest['built_in_version']) ||
            empty($manifest['name']) ||
            empty($manifest['version']) ||
            empty($manifest['author']) ||
            empty($manifest['acceptable_sugar_versions']['regex_matches']) ) {

            throw new ManifestIncompleteException('Please fill in the required details on your ' .
               $pathToManifestFile . ' file.');
        }

        return $manifest;
    }

    public function wipeDirectory($pkgDirectory)
    {
        $pkg_files = $this->getDirectoryContentIterator($pkgDirectory);

        if (empty($pkg_files)) {
            return;
        }

        foreach ($pkg_files as $pkg_file) {
            unlink($pkg_file->getPathname());
        }
    }


    /**
     * @param string $srcDirectory the assumption is that the path passed is absolute path
     * @param mixed ...$filesToExclude
     * @return array|mixed
     * @throws InvalidArgumentException
     */
//    public function getModuleFilesFromSrc($srcDirectory, ...$filesToExclude)
//    {
//        //a check for the possible case that resolving the path may have produced False
//        if ($srcDirectory === false) {
//            throw new InvalidArgumentException('the src directory must be a string');
//        }
//
//        $files_iterator = $this->getDirectoryContentIterator($srcDirectory);
//        $result = array();
//        //$path = realpath($path);
//
//        if (empty($files_iterator) || empty($srcDirectory)) {
//            return $result;
//        }
//
//        /* @var SplFileInfo $file */
//        foreach ($files_iterator as $name => $file) {
//            if ($file->isFile()) {
////                $file_realpath = $file->getRealPath();
//                $file_realpath = $file->getPathname();
//
//                if (in_array($file->getFilename(), $filesToExclude)) {
//                    continue;
//                }
//
//                $file_relative = '' . str_replace($srcDirectory . '/', '', $file_realpath);
////                echo 'The file relative value is: ' . $file_relative . PHP_EOL;
//                $result[$file_relative] = $file_realpath;
//            }
//        }
//
//        return $result;
//
//    }

//    public function copySrcDirectoryToDestinationDir($srcDirectory, $destDirectory)
//    {
//        // copy into pkg all src files
//        $common_files_list = $this->getModuleFilesFromSrc($srcDirectory);
//        if (!empty($common_files_list)) {
//            foreach ($common_files_list as $file_relative => $file_realpath) {
//                $destination_directory = $destDirectory . DIRECTORY_SEPARATOR .
//                    dirname($file_relative) . DIRECTORY_SEPARATOR;
//
//                $this->fileReaderWriterService->createDirectory($destination_directory);
//                $this->fileReaderWriterService->copyFile($file_realpath,
//                    $destination_directory . basename($file_realpath));
//            }
//        }
//    }

    /**
     * @param $templateFileConfigPath
     * @return array|mixed|void
     * @throws IllegalStateException
     */
    public function loadTemplateConfiguration($templateFileConfigPath)
    {
        if (!file_exists($templateFileConfigPath)) {
            return null;
        }

        require($templateFileConfigPath);

        if (empty($templates)) {
            return null;
        }

        foreach ($templates as $template_src_directory => $template_values) {

            if (!is_dir($this->fileReaderWriterService->resolvePath($template_src_directory)) ||
                empty($template_values['directory_pattern']) ||
                empty($template_values['modules'])) {

                throw new IllegalStateException('template source directory or directory pattern is invalid');
            }
        }

        return $templates;
    }


    public function generateTemplatedConfiguredFiles(array $templates, MessageOutputter $messenger, $pkgDirectory)
    {
        foreach ($templates as $template_src_directory => $template_values) {

            $template_dst_directory = $template_values['directory_pattern'];
            echo 'the template src directory: ' . $template_src_directory . PHP_EOL;
            $modules = $template_values['modules'];

            //resolve the template source directory and handle the case that the directory might not be
            //present
            $resolvedTemplateSrcDir = $this->fileReaderWriterService->resolvePath($template_src_directory);
            if (!$resolvedTemplateSrcDir) {
                $messenger->message('The ' . $template_src_directory . ' was not found.');
                return;
            }

            // generate runtime files based on the templates
            $template_files_list = $this->fileReaderWriterService->getFilesFromDirectory($resolvedTemplateSrcDir);

            if (empty($template_files_list)) {
                return;
            }

            $template_dst_directory = str_replace('/', DIRECTORY_SEPARATOR, $template_dst_directory);

            foreach ($modules as $module => $object) {

                $messenger->message('* Generating template files for module: ' . $module);

                // replace "modulename" from path
                $current_module_destination = str_replace('{MODULENAME}', $module, $template_dst_directory);

                foreach ($template_files_list as $file_relative => $file_realpath) {
                    // build destination
                    $destination_directory = $pkgDirectory . DIRECTORY_SEPARATOR . $current_module_destination .
                        DIRECTORY_SEPARATOR . dirname($file_relative) . DIRECTORY_SEPARATOR;

                    $messenger->message('* Generating '.$destination_directory . basename($file_relative));

                    $this->fileReaderWriterService->createDirectory($destination_directory);
                    $this->fileReaderWriterService->copyFile($file_realpath,
                        $destination_directory . basename($file_relative));

                    // modify content
                    $content = $this->fileReaderWriterService->readFile($destination_directory . basename($file_relative));
                    $content = str_replace('{MODULENAME}', $module, $content);
                    $content = str_replace('{OBJECTNAME}', $object, $content);
                    $this->fileReaderWriterService->writeFile($destination_directory . basename($file_relative), $content);
                }
            }
        }
    }

    public function generateZipPackage(array $manifest, $zipFile, MessageOutputter $messenger,
                                       array $pkgDirFiles, array $installDefs, PackagerConfiguration $config)
    {
        $messenger->message('Creating ' . $zipFile . '...');
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE);

        // add all pkg dir files to zip
        if (!empty($pkgDirFiles)) {
            foreach ($pkgDirFiles as $file_relative => $file_realpath) {
                $zip->addFile($file_realpath, $file_relative);
            }
        }

        //$installdefs = $this->getInstallDefs($manifest, $module_files_list);

        if (!empty($installdefs['copy'])) {
            $installdefs_copy = $installdefs['copy'];
            unset($installdefs['copy']);
        } else {
            $installdefs_copy = array();
        }

        $manifestContent = sprintf(
            "<?php\n\n\$manifest = %s;\n\n\$installdefs = %s;\n\n\$installdefs['copy'] = %s;\n",
            var_export($manifest, true),
            var_export($installdefs, true),
            preg_replace('(\s+\d+\s=>)', '', var_export($installdefs_copy, true))
        );

        // adding the file as well, for reference purpose only
        $this->fileReaderWriterService->writeFile($this->buildSimplePath($config->getPkgDirectory(),
            $config->getManifestFile()), $manifestContent);
        $zip->addFromString($config->getManifestFile(), $manifestContent);
        $zip->close();

        $messenger->message($config->getSoftwareInfo() . ' successfully packaged ' . $zipFile);

    }

    /**
     * @inheritDoc
     */
    public function buildUpInstallDefs($fileList, $id, MessageOutputter $messenger,
                                       $customInstallDefFilePath = '', Callable $shouldAddToManifestCopy)
    {
        $installdefs = array();
        $installdefs_original = array();
        $installdefs_generated = array('copy' => array());

        if (!empty($manifest['id'])) {
            $installdefs_original['id'] = $manifest['id'];
        }

        if (!empty($customInstallDefFilePath) && file_exists($customInstallDefFilePath)) {
            require($customInstallDefFilePath);
        }

        if (!empty($fileList)) {
            foreach ($fileList as $file_relative => $file_realpath) {

                if ($shouldAddToManifestCopy($file_relative, $installdefs)) {
                    $installdefs_generated['copy'][] = array(
                        'from' => '<basepath>/' . $file_relative,
                        'to' => $file_relative,
                    );
                    $messenger->message('* Automatically added manifest copy directive for ' . $file_relative);
                } else {
                    $messenger->message('* Skipped manifest copy directive for ' . $file_relative);
                }
            }
        }

        $installdefs = array_replace_recursive($installdefs_original, $installdefs, $installdefs_generated);

        return $installdefs;
    }

    /**
     * @param array $manifest existing skeleton manifest
     * @param array $installDefs loaded installdefs plus any custom installdefs
     * @return string containing the final complete manifest array
     */
    public function buildFinalManifest(array $manifest, array $installdefs)
    {
        if (!empty($installdefs['copy'])) {
        $installdefs_copy = $installdefs['copy'];
        unset($installdefs['copy']);
        } else {
            $installdefs_copy = array();
        }

        return sprintf(
            "<?php\n\n\$manifest = %s;\n\n\$installdefs = %s;\n\n\$installdefs['copy'] = %s;\n",
            var_export($manifest, true),
            var_export($installdefs, true),
            preg_replace('(\s+\d+\s=>)', '', var_export($installdefs_copy, true))
        );

    }

    protected function buildSimplePath($directory = '', $file = '')
    {
        $path = '';
        if (!empty($directory) && !empty($file)) {
            $path = realpath($directory) . DIRECTORY_SEPARATOR . $file;
        }
        return $path;
    }
}