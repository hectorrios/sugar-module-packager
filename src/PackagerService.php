<?php


namespace SugarModulePackager;

use SugarModulePackager\Error\TemplateGenerationError;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use ZipArchive;

class PackagerService
{
    use DirectoryContentIterator;

    /* @var ReaderWriter $fileReaderWriterService */
    private $fileReaderWriterService;

    /**
     * PackagerService constructor.
     * @param ReaderWriter $fileReaderWriterService
     */
    public function __construct(ReaderWriter $fileReaderWriterService)
    {
        $this->fileReaderWriterService = $fileReaderWriterService;
    }

    public function createPackagerDirectories(...$directories)
    {
        foreach ($directories as $directory) {
            $this->fileReaderWriterService->createDirectory($directory);
        }
    }

    /**
     * @param $pathToManifestFile
     * @param string $version version string of the Package
     * @return array|bool containing the array contents of the manifest.php file or False if
     * the file does not exist.
     * @throws ManifestIncompleteException
     */
    public function getManifestFileContents($pathToManifestFile, $version)
    {
        $manifest = array();

        if (!file_exists($pathToManifestFile)) {
            return false;
        }

        $manifestBase = array(
            'version' => $version,
            'is_uninstallable' => true,
            'published_date' => date('Y-m-d H:i:s'),
            'type' => 'module',
        );

        require($pathToManifestFile);

        if (!isset($manifest) || !is_array($manifest)) {
            throw new ManifestIncompleteException($pathToManifestFile .
                ' was loaded but a $manifest variable was not present or is not defined ' .
                'as an array');
        }

        //merge the base with the existing just loaded manifest
        $manifest = array_replace_recursive($manifestBase, $manifest);

        try {
            $this->validateManifestArray($manifest);
        } catch (ManifestIncompleteException $e) {
            throw $e;
        }

        return $manifest;
    }

    /**
     *
     * @param PackagerConfiguration $configuration
     * @return false|array
     * @throws ManifestIncompleteException
     */
    public function createSkeletonManifestFile(PackagerConfiguration $configuration)
    {
        // create sample empty manifest file
        $manifestContent = "<?php" . PHP_EOL . "\$manifest['id'] = '';" . PHP_EOL .
            "\$manifest['built_in_version'] = '';" . PHP_EOL .
            "\$manifest['name'] = '';" . PHP_EOL .
            "\$manifest['description'] = '';" . PHP_EOL .
            "\$manifest['author'] = '" . $configuration->getManifestDefaultAuthor() . "';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = " .
            $configuration->getManifestDefaultInstallVersionString() . ";";

        $this->fileReaderWriterService->writeFile($configuration->getPathToManifestFile(),
                $manifestContent);

        $manifestContents = $this->getManifestFileContents($configuration->getPathToManifestFile(),
            $configuration->getVersion());
        try {
            $this->validateManifestArray($manifestContents);
        } catch (ManifestIncompleteException $e) {
            throw $e;
        }

        return $manifestContents;
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

    /**
     * uses the passed in templates configuration array to copy files from the directory
     * specified in the configuration to the directories in the "pkg" directory that match
     * the directory_pattern in the templates configuration array.
     * @param array $templatesConfig the contents of configurations/templates.php
     * @param MessageOutputter $messenger
     * @param PackagerConfiguration $config
     * @throws TemplateGenerationError
     * @throws IllegalStateException
     */
    public function generateTemplatedConfiguredFiles(array $templatesConfig, MessageOutputter $messenger,
                                                     PackagerConfiguration $config)
    {
        //Before starting as a precaution let's make sure that our standards templates directory is
        //present
        if (!file_exists($config->getPathToTemplatesDir())) {
            throw new IllegalStateException('the base templates dir: ' . $config->getTemplatesDirectoryName() .
                ' does not exist and is required');
        }

        foreach ($templatesConfig as $templateDir => $template_values) {

            $template_dst_directory = $template_values['directory_pattern'];
            $modules = $template_values['modules'];

            //resolve the template source directory and handle the case that the directory might not be
            //present
            $resolvedTemplateSrcDir =
                $this->fileReaderWriterService->resolvePath($config->getPathToTemplatesDir() .
                    DIRECTORY_SEPARATOR . $templateDir);
            if (!$resolvedTemplateSrcDir) {
                throw new IllegalStateException('The template directory: ' .
                    $templateDir . ' was not found. It should be placed under the templates/ directory.' .
                    ' If this template is not used then remove the ' . 'configuration for the template ' .
                    'from configuration/templates.php');
            }

            // generate runtime files based on the templates
            $template_files_list =
                $this->fileReaderWriterService->getFilesFromDirectory($resolvedTemplateSrcDir);
            if (empty($template_files_list)) {
                $messenger->message('****** there are no files present in template directory: ' .
                    $resolvedTemplateSrcDir);
                continue;
            }

            //for an OS that does not recognize "/" as a directory separator, e.g. Windows
            $template_dst_directory = str_replace('/', DIRECTORY_SEPARATOR, $template_dst_directory);
            $templateDirectories = $this->getTemplateDirectories($template_files_list);

            /*
             * Before entering the nested for loops that will generate the template files,
             * we need to instantiate the TWIG Loader and Environment and finally grab the
             * template_data from templates config
             */
            $loader = new FilesystemLoader($templateDirectories, $config->getPathToTemplatesDir());
            $twig = new Environment($loader);

            foreach ($modules as $module => $moduleContext) {

                $messenger->message('* Generating template files for module: ' . $module);

                //set the current module into the template context
                $moduleContext['module'] = $module;

                // replace "modulename" from path
                $current_module_destination = str_replace('{MODULENAME}', $module, $template_dst_directory);

                foreach ($template_files_list as $file_relative => $file_realpath) {
                    // build destination
                    $destination_directory = $config->getPathToPkgDir() . DIRECTORY_SEPARATOR .
                        $current_module_destination . DIRECTORY_SEPARATOR . dirname($file_relative) .
                        DIRECTORY_SEPARATOR;

                    $messenger->message('* Generating '. $destination_directory . basename($file_relative));

                    $templateName = basename($file_relative);
                    try {
                        $mergedContent = $twig->render($templateName, $moduleContext);
                    } catch (LoaderError $e) {
                        //this should not be an issue since we've verified that the file already
                        //exists
                        throw new TemplateGenerationError('unable to load template: ' . $templateName,
                            0, $e);
                    } catch (RuntimeError $e) {
                        throw new TemplateGenerationError('a template rendering issue has occurred for ' .
                        'template: ' . $templateName,0, $e);
                    } catch (SyntaxError $e) {
                        throw new TemplateGenerationError('a template parsing error has occurred for ' .
                            'template: ' . $templateName,0, $e);
                    }

                    //Was the file there? Let's make sure by checking that the file contents are not blank
                    if (empty($mergedContent)) {
                        return;
                    }

                    $this->fileReaderWriterService->createDirectory($destination_directory);

                    $this->fileReaderWriterService->writeFile($destination_directory . basename($file_relative),
                        $mergedContent);
                }
            }
        }
    }

    /**
     * @param string $manifestContent the contents of the completely built manifest
     * @param string $zipFile full-path and name of the zip file to be generated
     * @param MessageOutputter $messenger
     * @param array $pkgDirFiles files from the "pkg" directory
     * @param PackagerConfiguration $config
     * @param ZipArchive $archiver
     */
    public function generateZipPackage($manifestContent, $zipFile, MessageOutputter $messenger,
                                       array $pkgDirFiles, PackagerConfiguration $config, ZipArchive $archiver)
    {
        $messenger->message('Creating ' . $zipFile . '...');
        $archiver->open($zipFile, ZipArchive::CREATE);

        // add all pkg dir files to zip
        if (!empty($pkgDirFiles)) {
            foreach ($pkgDirFiles as $file_relative => $file_realpath) {
                $archiver->addFile($file_realpath, $file_relative);
            }
        }

        // adding the file as well, for reference purpose only
        $pkgDirPath = $config->getPathToPkgDir();
        $this->fileReaderWriterService->writeFile($pkgDirPath .
            DIRECTORY_SEPARATOR . $config->getManifestFile(), $manifestContent);
        $archiver->addFromString($config->getManifestFile(), $manifestContent);
        $archiver->close();

        $messenger->message($config->getSoftwareInfo() . ' successfully packaged ' . $zipFile);
    }

    /**
     * @inheritDoc
     */
    public function buildUpInstallDefs($fileList, $id, MessageOutputter $messenger,
                                       Callable $shouldAddToManifestCopy, $customInstalldefFilePath = '')
    {
        $installdefs = array();
        $installdefs_original = array();
        $installdefs_generated = array('copy' => array());

        $installdefs_original['id'] = $id;

        if (!empty($customInstalldefFilePath) && file_exists($customInstalldefFilePath)) {
            require($customInstalldefFilePath);
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
     * @param array $installdefs loaded installdefs plus any custom installdefs
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

    /**
     * @return ReaderWriter
     */
    public function getFileReaderWriterService()
    {
        return $this->fileReaderWriterService;
    }

    public function createAllPackagerDirectories(PackagerConfiguration $config)
    {
        $this->getFileReaderWriterService()->createDirectory(
            $config->getPathToReleasesDir());
        $this->getFileReaderWriterService()->createDirectory(
            $config->getPathToConfigurationDir());
        $this->getFileReaderWriterService()->createDirectory(
            $config->getPathToSrcDir());
        $this->getFileReaderWriterService()->createDirectory(
            $config->getPathToPkgDir());
        $this->getFileReaderWriterService()->createDirectory($config->getPathToTemplatesDir());
    }

    public function copySrcIntoPkg(PackagerConfiguration $config)
    {
        $this->getFileReaderWriterService()->copyDirectory($config->getPathToSrcDir(),
            $config->getPathToPkgDir(), ...$config->getFilesToRemoveFromZip());
    }

    /**
     * @param $pathToDirectory
     * @param string ...$filesToExclude
     * @return string[] all the files from this directory and nested directories. The keys of the
     * array are the relative path of the file and the value is the full-path of the file
     */
    public function getFilesFromDirectory($pathToDirectory, ...$filesToExclude)
    {
        return $this->fileReaderWriterService->getFilesFromDirectory($pathToDirectory, ...$filesToExclude);
    }

    private function validateManifestArray(array $manifestStructure)
    {
        if ( empty($manifestStructure['id']) ||
            empty($manifestStructure['built_in_version']) ||
            empty($manifestStructure['name']) ||
            empty($manifestStructure['version']) ||
            empty($manifestStructure['author']) ||
            empty($manifestStructure['acceptable_sugar_versions']['regex_matches']) ) {

            throw new ManifestIncompleteException('manifest file array failed validation');
        }
    }

    private function getTemplateDirectories(array $templateFileList)
    {
        $directories = array();
        foreach($templateFileList as $relativePath) {
            if (in_array(dirname($relativePath), $directories)) {
                continue;
            }
            $directories[] = dirname($relativePath);
        }

        return $directories;
    }
}