<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\EchoMessageOutputter;
use SugarModulePackager\FileReaderWriterImpl;
use SugarModulePackager\ManifestIncompleteException;
use SugarModulePackager\Packager;
use SugarModulePackager\PackagerConfiguration;
use SugarModulePackager\PackagerService;
use SugarModulePackager\Test\Mocks\MockMessageOutputter;

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
        $messageOutputter = new MockMessageOutputter();
        $pConfig = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION);
        $pService = new PackagerService(new FileReaderWriterImpl());
        $packager = new Packager($pService, $messageOutputter, $pConfig);

        $packager->build();
        $this->assertEquals($expectedMessage, $messageOutputter->getLastMessage());
    }

    public function testBuildThrowsManifestIncompleteExceptionWhenNoManifestExists()
    {

        $messageOutputter = new MockMessageOutputter();
        $pConfig = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION);
        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $pService = new PackagerService($readerWriter);
        $packager = new Packager($pService, $messageOutputter, $pConfig);

        $this->expectException(ManifestIncompleteException::class);
        $packager->build('0.0.1');
    }

}
