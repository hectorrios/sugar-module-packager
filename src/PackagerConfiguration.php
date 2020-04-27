<?php


namespace SugarModulePackager;

use InvalidArgumentException;

class PackagerConfiguration
{
    /* @var string $packageRootDir */
    private $packageRootDir;

    private $release_directory_name = 'releases';
    private $prefix_release_package = 'module_';
    private $config_directory_name = 'configuration';
    private $config_template_file = 'templates.php';
    private $config_installdefs_file = 'installdefs.php';
    private $src_directory_name = 'src';
    private $pkg_directory_name = 'pkg';
    private $manifest_file = 'manifest.php';

    private $files_to_remove_from_zip = array(
        '.DS_Store',
        '.gitkeep'
    );

    private $files_to_remove_from_manifest_copy = array(
        'LICENSE',
        'LICENSE.txt',
        'README.txt'
    );

    private $installdefs_keys_to_remove_from_manifest_copy = array(
        'pre_execute',
        'post_execute',
        'pre_uninstall',
        'post_uninstall'
    );


    private $manifest_default_install_version_string = "array('^8.[\d]+.[\d]+$')";
    private $manifest_default_author = 'Enrico Simonetti';

    /* @var string */
    private $version;

    /* @var string $softwareversion */
    private $softwareVersion;

    /* @var string $softwareName */
    private $softwareName;


    /**
     * PackagerConfiguration constructor.
     * @param string $version
     * @param string $softwareName
     * @param string $softwareVersion
     * @param string $packageRootDir
     */
    public function __construct($version, $softwareName, $softwareVersion, $packageRootDir = '')
    {
        $this->version = $version;
        if (empty($packageRootDir)) {
            $packageRootDir = realpath('.');
        }
        $this->packageRootDir = $packageRootDir;
        $this->softwareName = $softwareName;
        $this->softwareVersion = $softwareVersion;
    }

    /**
     * @return string
     */
    public function getPackageRootDir()
    {
        return $this->packageRootDir;
    }

    /**
     * @return string
     */
    public function getReleaseDirectoryName()
    {
        return $this->release_directory_name;
    }

    /**
     * @return string
     */
    public function getConfigDirectoryName()
    {
        return $this->config_directory_name;
    }

    /**
     * @return string
     */
    public function getSrcDirectoryName()
    {
        return $this->src_directory_name;
    }

    /**
     * @return string
     */
    public function getPkgDirectoryName()
    {
        return $this->pkg_directory_name;
    }

    /**
     * @return array
     */
    public function getFilesToRemoveFromZip()
    {
        return $this->files_to_remove_from_zip;
    }

    /**
     * @return string
     */
    public function getManifestFile()
    {
        return $this->manifest_file;
    }

    /**
     * @return string
     */
    public function getConfigInstalldefsFile()
    {
        return $this->config_installdefs_file;
    }

    /**
     * @return string
     */
    public function getManifestDefaultAuthor()
    {
        return $this->manifest_default_author;
    }

    /**
     * @return string
     */
    public function getManifestDefaultInstallVersionString()
    {
        return $this->manifest_default_install_version_string;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getSoftwareVersion()
    {
        return $this->softwareVersion;
    }

    /**
     * @param string $softwareVersion
     */
    public function setSoftwareVersion($softwareVersion)
    {
        $this->softwareVersion = $softwareVersion;
    }

    /**
     * @return string
     */
    public function getSoftwareName()
    {
        return $this->softwareName;
    }

    /**
     * @param string $softwareName
     */
    public function setSoftwareName($softwareName)
    {
        $this->softwareName = $softwareName;
    }

    public function getSoftwareInfo()
    {
        return $this->getSoftwareName() . ' v' . $this->getSoftwareVersion();
    }

    /**
     * @return string
     */
    public function getPrefixReleasePackage()
    {
        return $this->prefix_release_package;
    }

    /**
     * @return string[]
     */
    public function getInstalldefsKeysToRemoveFromManifestCopy()
    {
        return $this->installdefs_keys_to_remove_from_manifest_copy;
    }

    /**
     * @return string[]
     */
    public function getFilesToRemoveFromManifestCopy()
    {
        return $this->files_to_remove_from_manifest_copy;
    }

    /**
     * @return string
     */
    public function getConfigTemplateFile()
    {
        return $this->config_template_file;
    }

    private function buildSimplePath($directory = '', $file = '')
    {
        $path = '';
        if (empty($directory) || empty($file)) {
            return $path;
        }

        return $this->packageRootDir . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * @return string path to the manifest file
     */
    public function getPathToManifestFile()
    {
        return $this->buildSimplePath($this->getConfigDirectoryName(), $this->getManifestFile());
    }

    public function getPathToConfigurationDir()
    {
        return $this->buildSimplePath($this->getConfigDirectoryName());
    }

    public function getPathToPkgDir()
    {
        return $this->buildSimplePath($this->getPkgDirectoryName());
    }

    public function getPathToSrcDir()
    {
        return $this->buildSimplePath($this->getSrcDirectoryName());
    }

    public function getPathToReleasesDir()
    {
        return $this->buildSimplePath($this->getReleaseDirectoryName());
    }


}