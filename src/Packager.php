<?php


namespace SugarModulePackager;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Packager
{
    const SW_VERSION = '0.2.2';
    const SW_NAME = 'SugarModulePackager';

    /* @var MessageOutputter $messageOutputter */
    private $messageOutputter;

    /* @var PackagerConfiguration $config */
    private $config;

    /* @var PackagerService $packagerService */
    private $packagerService;

    /**
     * Packager constructor.
     * @param PackagerService $packagerService
     * @param MessageOutputter $msgOutputter
     * @param PackagerConfiguration $config
     */
    public function __construct(PackagerService $packagerService,
                                MessageOutputter $msgOutputter,
                                PackagerConfiguration $config)
    {
//        $this->fileReaderWriterService = $readerWriter;
        $this->messageOutputter = $msgOutputter;
        $this->config = $config;
        $this->packagerService = $packagerService;
    }

    private function getSoftwareVersionNumber()
    {
        return self::SW_VERSION;
    }

    private function getSoftwareName()
    {
        return self::SW_NAME;
    }

    public function getSoftwareInfo()
    {
        return $this->getSoftwareName() . ' v' . $this->getSoftwareVersionNumber();
    }

    protected function getZipName($package_name = '')
    {
        return $this->config->getPathToReleasesDir() . DIRECTORY_SEPARATOR .
            $this->config->getPrefixReleasePackage() . $package_name . '.zip';
    }

//    protected function createAllDirectories()
//    {
//        $this->packagerService->getFileReaderWriterService()->createDirectory(
//            $this->config->getPathToReleasesDir());
//        $this->packagerService->getFileReaderWriterService()->createDirectory(
//            $this->config->getPathToConfigurationDir());
//        $this->packagerService->getFileReaderWriterService()->createDirectory(
//            $this->config->getPathToSrcDir());
//        $this->packagerService->getFileReaderWriterService()->createDirectory(
//            $this->config->getPathToPkgDir());
//    }

    protected function getDirectoryContentIterator($path)
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath($path), RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    protected function getModuleFiles($path)
    {
        $files_iterator = $this->getDirectoryContentIterator($path);
        $result = array();
        $path = realpath($path);
        if (!empty($files_iterator) && !empty($path)) {
            foreach ($files_iterator as $name => $file) {
                if ($file->isFile()) {
                    $file_realpath = $file->getRealPath();
                    if (!in_array($file->getFilename(), $this->config->getFilesToRemoveFromZip())) {
                        $file_relative = '' . str_replace($path . '/', '', $file_realpath);
                        $result[$file_relative] = $file_realpath;
                    }
                }
            }
        }
        return $result;
    }

//    protected function wipePkgDirectory()
//    {
//        $pkg_files = $this->getDirectoryContentIterator($this->config->getPkgDirectory());
//
//        if (!empty($pkg_files)) {
//            foreach ($pkg_files as $pkg_file) {
//                unlink($pkg_file->getPathname());
//            }
//        }
//    }

    /**
     * @param string $version
     * @return array The manifest contents
     * @throws ManifestIncompleteException
     */
//    protected function getManifest($version = '')
//    {
//        $manifest = array();
//        if (empty($version)) {
//            return $manifest;
//        }
//
//
         //check existence of manifest template
//        $manifest_base = array(
//            'version' => $version,
//            'is_uninstallable' => true,
//            'published_date' => date('Y-m-d H:i:s'),
//            'type' => 'module',
//        );
//
//        if (file_exists($this->buildSimplePath($this->config->getConfigDirectory(), $this->config->getManifestFile()))) {
//            require($this->buildSimplePath($this->config->getConfigDirectory(), $this->config->getManifestFile()));
//            $manifest = array_replace_recursive($manifest_base, $manifest);
//        } else {
             //create sample empty manifest file
//            $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
//                "\$manifest['built_in_version'] = '';".PHP_EOL.
//                "\$manifest['name'] = '';".PHP_EOL.
//                "\$manifest['description'] = '';".PHP_EOL.
//                "\$manifest['author'] = '".$this->manifest_default_author."';".PHP_EOL.
//                "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".$this->manifest_default_install_version_string.";";
//
//            $this->fileReaderWriterService->writeFile(
//                $this->buildSimplePath($this->config->getConfigDirectory(), $this->config->getManifestFile()),
//                $manifestContent);
//        }
//
//        if ( empty($manifest['id']) ||
//            empty($manifest['built_in_version']) ||
//            empty($manifest['name']) ||
//            empty($manifest['version']) ||
//            empty($manifest['author']) ||
//            empty($manifest['acceptable_sugar_versions']['regex_matches']) ) {
//
//            throw new ManifestIncompleteException('Please fill in the required details on your ' .
//                $this->buildSimplePath($this->config->getConfigDirectory(),
//                    $this->config->getManifestFile())  . ' file.');
            // some problem... return empty manifest
           // return array();
//        }
//
//        return $manifest;
//    }

//    protected function getInstallDefs($manifest, $module_files_list)
//    {
//        $installdefs = array();
//        $installdefs_original = array();
//        $installdefs_generated = array('copy' => array());
//
//        if (!empty($manifest['id'])) {
//            $installdefs_original['id'] = $manifest['id'];
//        }
//
//        if (is_dir($this->config->getConfigDirectory()) &&
//            file_exists($this->buildSimplePath($this->config->getConfigDirectory(), $this->config->getConfigInstalldefsFile()))) {
//            require($this->buildSimplePath($this->config->getConfigDirectory(),
//                $this->config->getConfigInstalldefsFile()));
//        }
//
//        if (!empty($module_files_list)) {
//            foreach ($module_files_list as $file_relative => $file_realpath) {
//                if ($this->shouldAddToManifestCopy($file_relative, $installdefs)) {
//                    $installdefs_generated['copy'][] = array(
//                        'from' => '<basepath>/' . $file_relative,
//                        'to' => $file_relative,
//                    );
//                    $this->messageOutputter->message('* Automatically added manifest copy directive for ' . $file_relative);
//                } else {
//                    $this->messageOutputter->message('* Skipped manifest copy directive for ' . $file_relative);
//                }
//            }
//        }
//
//        $installdefs = array_replace_recursive($installdefs_original, $installdefs, $installdefs_generated);
//
//        return $installdefs;
//    }

    /**
     * @param $file_relative
     * @param $custom_installdefs
     * @return bool
     */
    protected function shouldAddToManifestCopy($file_relative, $custom_installdefs)
    {
        if (!in_array(basename($file_relative), $this->config->getFilesToRemoveFromManifestCopy())) {
            // check and dont copy all *_execute and *_uninstall installdefs keyword files
            foreach ($this->config->getInstalldefsKeysToRemoveFromManifestCopy() as $to_remove) {
                if (!empty($custom_installdefs[$to_remove])) {
                    foreach ($custom_installdefs[$to_remove] as $manifest_file_copy) {
                        // found matching relative file as one of the *_execute or *_uninstall scripts
                        if (strcmp(str_replace('<basepath>/', '', $manifest_file_copy), $file_relative) == 0) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

//    protected function copySrcIntoPkg()
//    {
//        TODO: simplify this by deferring to the copyDirectory method in ReaderWriter
//        $this->packagerService->getFileReaderWriterService()->copyDirectory($this->config->getPathToSrcDir(),
//            $this->config->getPathToPkgDir());

        // copy into pkg all src files
//        $common_files_list = $this->getModuleFiles($this->config->getSrcDirectory());
//        if (!empty($common_files_list)) {
//            foreach ($common_files_list as $file_relative => $file_realpath) {
//                $destination_directory = $this->config->getPkgDirectory() . DIRECTORY_SEPARATOR .
//                    dirname($file_relative) . DIRECTORY_SEPARATOR;
//
//                $this->packagerService->getFileReaderWriterService()
//                    ->createDirectory($destination_directory);
//                $this->packagerService->getFileReaderWriterService()
//                    ->copyFile($file_realpath,
//                        $destination_directory . basename($file_realpath));
//            }
//        }
//    }

//    protected function generateZipPackage($manifest, $zipFile)
//    {
//        $this->messageOutputter->message('Creating ' . $zipFile . '...');
//        $zip = new ZipArchive();
//        $zip->open($zipFile, ZipArchive::CREATE);
//
        // add all files to zip
//        $module_files_list = $this->getModuleFiles($this->config->getPkgDirectory());
//        if (!empty($module_files_list)) {
//            foreach ($module_files_list as $file_relative => $file_realpath) {
//                $zip->addFile($file_realpath, $file_relative);
//            }
//        }
//
//        $installdefs = $this->getInstallDefs($manifest, $module_files_list);
//
//        if (!empty($installdefs['copy'])) {
//            $installdefs_copy = $installdefs['copy'];
//            unset($installdefs['copy']);
//        } else {
//            $installdefs_copy = array();
//        }
//
//        $manifestContent = sprintf(
//            "<?php\n\n\$manifest = %s;\n\n\$installdefs = %s;\n\n\$installdefs['copy'] = %s;\n",
//            var_export($manifest, true),
//            var_export($installdefs, true),
//            preg_replace('(\s+\d+\s=>)', '', var_export($installdefs_copy, true))
//        );
//
        // adding the file as well, for reference purpose only
//        $this->fileReaderWriterService->writeFile($this->buildSimplePath($this->config->getPkgDirectory(),
//            $this->config->getManifestFile()), $manifestContent);
//        $zip->addFromString($this->config->getManifestFile(), $manifestContent);
//        $zip->close();
//
//        $this->messageOutputter->message($this->getSoftwareInfo() . ' successfully packaged ' . $zipFile);
//    }

    /**
     * @param string $version
     * @throws ManifestIncompleteException
     * @throws IllegalStateException
     */
    public function build($version)
    {
        if (empty($version)) {
            $this->messageOutputter->message('Provide version number');
            return;
        }

        //create all our necessary directories if they don't already exist
        $this->packagerService->createAllPackagerDirectories($this->config);

        try {
            $manifest = $this->packagerService->getManifestFileContents(
                $this->config->getPathToManifestFile());
            //$manifest = $this->getManifest($version);
            if (is_bool($manifest) && !$manifest) {
                $this->packagerService->createSkeletonManifestFile($this->config);
            }
        } catch (ManifestIncompleteException $e) {
            throw $e;
        }

        if (empty($manifest)) {
            throw new IllegalStateException('the manifest contents are empty which means that it somehow was' .
                ' not read correctly from the filesystem');
        }

        $zip = $this->getZipName($manifest['id'] . '_' . $version);

        if (file_exists($zip)) {
            $this->messageOutputter->message('Release '.$zip.' already exists!');
            return;
        }

        $this->packagerService->wipeDirectory($this->config->getPathToPkgDir());
        $this->packagerService->copySrcIntoPkg($this->config);

        $templates = $this->packagerService->loadTemplateConfiguration($this->config->getConfigTemplateFile());

        if (!empty($templates)) {
            $this->packagerService->generateTemplatedConfiguredFiles($templates, $this->messageOutputter,
                $this->config->getPathToPkgDir());
        }

        $installDefs = $this->packagerService->buildUpInstallDefs(
            $this->packagerService->getFileReaderWriterService()->getFilesFromDirectory($this->config->getPathToPkgDir()),
            $manifest['id'], $this->messageOutputter, function($file_relative, $customInstallDefs) {
            $this->shouldAddToManifestCopy($file_relative, $customInstallDefs);
        }
        );

        $pkgDirFiles =
            $this->packagerService->getFileReaderWriterService()->getFilesFromDirectory(
                $this->config->getPathToPkgDir());

        $manifestContent = $this->packagerService->buildFinalManifest($manifest, $installDefs);
        $this->packagerService->generateZipPackage($manifestContent, $zip, $this->messageOutputter, $pkgDirFiles,
            $this->config, new ZipArchive());
    }

//    protected function generateTemplatedConfiguredFiles()
//    {
//        if (is_dir($this->config_directory) &&
//            file_exists($this->buildSimplePath($this->config_directory, $this->config_template_file))) {
//
//            require($this->buildSimplePath($this->config_directory, $this->config_template_file));
//
//            if (!empty($templates)) {
//                foreach ($templates as $template_src_directory => $template_values) {
//                    if (is_dir(realpath($template_src_directory)) &&
//                        !empty($template_values['directory_pattern']) && !empty($template_values['modules'])) {
//                        $template_dst_directory = $template_values['directory_pattern'];
//                        $modules = $template_values['modules'];

                        // generate runtime files based on the templates
//                        $template_files_list = $this->getModuleFiles($template_src_directory);
//                        if (!empty($template_files_list)) {
//                            $template_dst_directory = str_replace('/', DIRECTORY_SEPARATOR, $template_dst_directory);

//                            foreach ($modules as $module => $object) {
//                                $this->messageOutputter->message('* Generating template files for module: ' . $module);
                                // replace "modulename" from path
//                                $current_module_destination = str_replace('{MODULENAME}', $module, $template_dst_directory);
//                                foreach ($template_files_list as $file_relative => $file_realpath) {
                                    // build destination
//                                    $destination_directory = $this->pkg_directory . DIRECTORY_SEPARATOR . $current_module_destination .
//                                        DIRECTORY_SEPARATOR . dirname($file_relative) . DIRECTORY_SEPARATOR;
//                                    $this->messageOutputter->message('* Generating '.$destination_directory . basename($file_relative));
//
//                                    $this->fileReaderWriterService->createDirectory($destination_directory);
//                                    $this->fileReaderWriterService->copyFile($file_realpath, $destination_directory . basename($file_relative));

                                    // modify content
//                                    $content = $this->fileReaderWriterService->readFile($destination_directory . basename($file_relative));
//                                    $content = str_replace('{MODULENAME}', $module, $content);
//                                    $content = str_replace('{OBJECTNAME}', $object, $content);
//                                    $this->fileReaderWriterService->writeFile($destination_directory . basename($file_relative), $content);
//                                }
//                            }
//                        }
//                    }
//                }
//            }
//        }
//    }
}
