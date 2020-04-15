<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\EchoMessageOutputter;
use SugarModulePackager\FileReaderWriter;
use SugarModulePackager\Packager;

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
        $packager = new Packager(new FileReaderWriter(), $messageOutputter);

        $packager->build();
        $this->assertEquals($expectedMessage, $messageOutputter->getLastMessage());
    }

}
