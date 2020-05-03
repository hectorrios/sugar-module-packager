<?php

namespace SugarModulePackager;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SugarModulePackager\Error\TemplateGenerationError;
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

    /**
     * @param $file_relative
     * @param array $installdefs
     * @return bool
     */
    protected function shouldAddToManifestCopy($file_relative, $installdefs)
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

    /**
     * @param string $version
     * @throws ManifestIncompleteException
     * @throws IllegalStateException
     * @throws TemplateGenerationError
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
                $this->config->getPathToManifestFile(), $version);
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
                $this->config);
        }

        $installDefs = $this->packagerService->buildUpInstallDefs(
            $this->packagerService->getFilesFromDirectory($this->config->getPathToPkgDir()),
            $manifest['id'],
            $this->messageOutputter,
            function($file_relative, $customInstallDefs) {
                return $this->shouldAddToManifestCopy($file_relative, $customInstallDefs);
            },
            $this->config->getPathToConfigInstalldefsFile()
        );

        $pkgDirFiles = $this->packagerService->getFilesFromDirectory(
                $this->config->getPathToPkgDir());

        $manifestContent = $this->packagerService->buildFinalManifest($manifest, $installDefs);
        $this->packagerService->generateZipPackage($manifestContent, $zip, $this->messageOutputter, $pkgDirFiles,
            $this->config, new ZipArchive());
    }

}
