<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\PackagerConfiguration;

class PackagerConfigurationTest extends TestCase
{

    private $rootDirName = 'root';

    /* @var vfsStreamDirectory $rootDir */
    private $rootDir;

    protected function setUp()
    {
        $this->rootDir = vfsStream::setup($this->rootDirName);
    }
    public function test__construct()
    {
        $config = new PackagerConfiguration('0.0.1', 'SugarPackager', '0.2.2');
        $this->assertEquals('0.0.1', $config->getVersion());
        $info = 'SugarPackager v0.2.2';
        $this->assertEquals($info, $config->getSoftwareInfo());
    }

    public function testGetPathToManifestFile()
    {
        $structure = array(
          'src' => array(),
          'pkg' => array(),
          'configuration' => array(),
        );

        vfsStream::create($structure);

        $config = new PackagerConfiguration('0.0.1', 'SugarPackager', '0.2.2',
            vfsStream::url($this->rootDirName));
        $this->assertEquals('configuration', $config->getConfigDirectoryName());
        $this->assertEquals('vfs://root/configuration/manifest.php', $config->GetPathToManifestFile());
    }

}
