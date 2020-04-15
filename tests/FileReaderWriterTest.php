<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\FileReaderWriter;

class FileReaderWriterTest extends TestCase
{
    /* @var vfsStreamDirectory */
    private $rootDir;

    public function setUp()
    {
       $this->rootDir = vfsStream::setup("exampleDir");
    }

    public function testCreateDirectory()
    {
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $dir = 'releases';
        $this->assertFalse($this->rootDir->hasChild($dir));
        //$readerWriter->createDirectory($dir);
        $readerWriter->createDirectory($dir);
        $this->assertTrue($this->rootDir->hasChild($dir));
    }

    public function testWriteFile()
    {
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $readerWriter->writeFile('sample_write.php',
            'Sample Content');
        $this->assertTrue($this->rootDir->hasChild('sample_write.php'));
    }

    public function testReadFile()
    {
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $expectedContent = 'Sample Content';
        $readerWriter->writeFile('sample_write.php',
            $expectedContent);


        //Now retrieve this file
        $contents = $readerWriter->readFile('sample_write.php');
        $this->assertEquals($expectedContent, $contents);
    }

    public function testReadFileWithNoFile()
    {
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $contents = $readerWriter->readFile('non_existing.php');
        $this->assertEquals('', $contents);
    }

}