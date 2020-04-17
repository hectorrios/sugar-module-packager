<?php


namespace SugarModulePackager;


class PackagerConfiguration
{
    /* @var string $packageRootDir */
    private $packageRootDir;

    private $release_directory = 'releases';
    private $prefix_release_package = 'module_';
    private $config_directory = 'configuration';
    private $config_template_file = 'templates.php';
    private $config_installdefs_file = 'installdefs.php';
    private $src_directory = 'src';
    private $pkg_directory = 'pkg';
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

    /**
     * PackagerConfiguration constructor.
     * @param string $packageRootDir
     */
    public function __construct($packageRootDir = '')
    {
        $this->packageRootDir = $packageRootDir;
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
    public function getReleaseDirectory()
    {
        return $this->release_directory;
    }

    /**
     * @return string
     */
    public function getConfigDirectory()
    {
        return $this->config_directory;
    }

    /**
     * @return string
     */
    public function getSrcDirectory()
    {
        return $this->src_directory;
    }

    /**
     * @return string
     */
    public function getPkgDirectory()
    {
        return $this->pkg_directory;
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


}