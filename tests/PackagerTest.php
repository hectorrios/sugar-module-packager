<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\EchoMessageOutputter;
use SugarModulePackager\FileReaderWriter;
use SugarModulePackager\ManifestIncompleteException;
use SugarModulePackager\Packager;
use SugarModulePackager\PackagerConfiguration;

class PackagerTest extends TestCase
{
    /* @var vfsStreamDirectory */
    private $rootDir;

    public function setup()
    {
        $this->rootDir = vfsStream::setup('exampleDir');
    }

    public function testBuildWithNoVersionProvided()
    {
        $expectedMessage = 'Provide version number' . PHP_EOL;
        $messageOutputter = new EchoMessageOutputter();
        $pConfig = new PackagerConfiguration();
        $packager = new Packager(new FileReaderWriter(), $messageOutputter, $pConfig);

        $packager->build();
        $this->assertEquals($expectedMessage, $messageOutputter->getLastMessage());
    }

    public function testBuildThrowsManifestIncompleteExceptionWhenNoManifestExists()
    {

        $messageOutputter = new EchoMessageOutputter();
        $pConfig = new PackagerConfiguration();
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $packager = new Packager($readerWriter, $messageOutputter, $pConfig);

        $this->expectException(ManifestIncompleteException::class);
        $packager->build('0.0.1');
    }

}
