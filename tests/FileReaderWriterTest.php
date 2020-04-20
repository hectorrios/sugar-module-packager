<?php

namespace SugarModulePackager\Test;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\FileReaderWriter;
use SugarModulePackager\PackagerConfiguration;

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

    public function testCreateDirectoryWithoutBaseDir()
    {
        $readerWriter = new FileReaderWriter();
        $dir = 'releases';
        $this->assertFalse($this->rootDir->hasChild($dir));
        $dirPath = vfsStream::url('exampleDir' . DIRECTORY_SEPARATOR . $dir);
        $readerWriter->createDirectory($dirPath);
        $this->assertTrue($this->rootDir->hasChild($dir));
    }

    public function testWriteFile()
    {
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $readerWriter->writeFile('sample_write.php',
            'Sample Content');
        $this->assertTrue($this->rootDir->hasChild('sample_write.php'));
    }

    public function testWriteFileWithinExistingNestedDirectories()
    {
        $config = new PackagerConfiguration('0.0.1');

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));

        $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
            "\$manifest['built_in_version'] = '';".PHP_EOL.
            "\$manifest['name'] = '';".PHP_EOL.
            "\$manifest['description'] = '';".PHP_EOL.
            "\$manifest['author'] = 'Sugar Partner';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".
            $config->getManifestDefaultInstallVersionString() .";";

        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $readerWriter->createDirectory($config->getConfigDirectory());
        $readerWriter->writeFile($config->getConfigDirectory() . DIRECTORY_SEPARATOR .
        $config->getManifestFile(), $manifestContent);

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));
        $this->assertTrue($this->rootDir->hasChild($config->getConfigDirectory() . DIRECTORY_SEPARATOR .
            $config->getManifestFile()));
    }

    public function testWriteFileWithinNonExistingNestedDirectories()
    {
        $config = new PackagerConfiguration('0.0.1');

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));

        $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
            "\$manifest['built_in_version'] = '';".PHP_EOL.
            "\$manifest['name'] = '';".PHP_EOL.
            "\$manifest['description'] = '';".PHP_EOL.
            "\$manifest['author'] = 'Sugar Partner';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".
            $config->getManifestDefaultInstallVersionString() .";";

        $readerWriter = new FileReaderWriter();

        $filePath = vfsStream::url('exampleDir' .
            DIRECTORY_SEPARATOR . $config->getConfigDirectory() . DIRECTORY_SEPARATOR .
            $config->getManifestFile());

        $dirPart = dirname($filePath);

        $this->assertDirectoryNotExists($dirPart);

        //create it
        //$readerWriter->createDirectory($dirPart);

        //$this->assertDirectoryExists($dirPart);

        $this->expectException(InvalidArgumentException::class);
        //$readerWriter->createDirectory($config->getConfigDirectory());
        $readerWriter->writeFile( $filePath, $manifestContent);

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));
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

    public function testReadFileWithNonExistentFile()
    {
        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $contents = $readerWriter->readFile('non_existing.php');
        $this->assertEquals('', $contents);
    }

    public function testCopyFileWithBaseDirProvided()
    {

        $readerWriter = new FileReaderWriter(vfsStream::url('exampleDir'));
        $expectedContent = 'Sample Content';
        $readerWriter->writeFile('sample_write.php',
            $expectedContent);

        $readerWriter->copyFile('sample_write.php', 'sample_write_copy.php');
        $this->assertTrue($this->rootDir->hasChild('sample_write_copy.php'));
    }

    public function testCopyFileWithoutBaseDirProvided()
    {
        $readerWriter = new FileReaderWriter();
        $expectedContent = 'Sample Content';
        $readerWriter->writeFile(
            vfsStream::url('exampleDir' . DIRECTORY_SEPARATOR . 'sample_write_3.php'),
            $expectedContent);
        $readerWriter->copyFile(vfsStream::url('exampleDir' . DIRECTORY_SEPARATOR . 'sample_write_3.php'),
            vfsStream::url('exampleDir' .  DIRECTORY_SEPARATOR . 'sample_write_3_copy.php'));
        $this->assertTrue($this->rootDir->hasChild('sample_write_3_copy.php'));
    }
}