<?php

namespace SugarModulePackager\Test;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\FileReaderWriterImpl;
use SugarModulePackager\PackagerConfiguration;
use SugarModulePackager\Test\Mocks\ReaderWriterTestDecorator;

class FileReaderWriterImplTest extends TestCase
{
    /* @var vfsStreamDirectory */
    private $rootDir;

    private $softwareName = 'SugarModulePackager';

    private $softwareVersion = '0.2.2';

    public function setUp()
    {
       $this->rootDir = vfsStream::setup("exampleDir");
    }

    public function testCreateDirectory()
    {
        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $dir = 'releases';
        $this->assertFalse($this->rootDir->hasChild($dir));
        //$readerWriter->createDirectory($dir);
        $readerWriter->createDirectory($dir);
        $this->assertTrue($this->rootDir->hasChild($dir));
    }

    public function testCreateDirectoryWithoutBaseDir()
    {
        $readerWriter = new FileReaderWriterImpl();
        $dir = 'releases';
        $this->assertFalse($this->rootDir->hasChild($dir));
        $dirPath = vfsStream::url('exampleDir' . DIRECTORY_SEPARATOR . $dir);
        $readerWriter->createDirectory($dirPath);
        $this->assertTrue($this->rootDir->hasChild($dir));
    }

    public function testWriteFile()
    {
        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $readerWriter->writeFile('sample_write.php',
            'Sample Content');
        $this->assertTrue($this->rootDir->hasChild('sample_write.php'));
    }

    public function testWriteFileWithinExistingNestedDirectories()
    {
        $config = new PackagerConfiguration('0.0.1', $this->softwareName, $this->softwareVersion);

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));

        $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
            "\$manifest['built_in_version'] = '';".PHP_EOL.
            "\$manifest['name'] = '';".PHP_EOL.
            "\$manifest['description'] = '';".PHP_EOL.
            "\$manifest['author'] = 'Sugar Partner';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".
            $config->getManifestDefaultInstallVersionString() .";";

        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $readerWriter->createDirectory($config->getConfigDirectory());
        $readerWriter->writeFile($config->getConfigDirectory() . DIRECTORY_SEPARATOR .
        $config->getManifestFile(), $manifestContent);

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));
        $this->assertTrue($this->rootDir->hasChild($config->getConfigDirectory() . DIRECTORY_SEPARATOR .
            $config->getManifestFile()));
    }

    public function testWriteFileWithinNonExistingNestedDirectories()
    {
        $config = new PackagerConfiguration('0.0.1', $this->softwareName, $this->softwareVersion);

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));

        $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
            "\$manifest['built_in_version'] = '';".PHP_EOL.
            "\$manifest['name'] = '';".PHP_EOL.
            "\$manifest['description'] = '';".PHP_EOL.
            "\$manifest['author'] = 'Sugar Partner';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".
            $config->getManifestDefaultInstallVersionString() .";";

        $readerWriter = new FileReaderWriterImpl();

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
        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $expectedContent = 'Sample Content';
        $readerWriter->writeFile('sample_write.php',
            $expectedContent);


        //Now retrieve this file
        $contents = $readerWriter->readFile('sample_write.php');
        $this->assertEquals($expectedContent, $contents);
    }

    public function testReadFileWithNonExistentFile()
    {
        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $contents = $readerWriter->readFile('non_existing.php');
        $this->assertEquals('', $contents);
    }

    public function testCopyFileWithBaseDirProvided()
    {

        $readerWriter = new FileReaderWriterImpl(vfsStream::url('exampleDir'));
        $expectedContent = 'Sample Content';
        $readerWriter->writeFile('sample_write.php',
            $expectedContent);

        $readerWriter->copyFile('sample_write.php', 'sample_write_copy.php');
        $this->assertTrue($this->rootDir->hasChild('sample_write_copy.php'));
    }

    public function testCopyFileWithoutBaseDirProvided()
    {
        $readerWriter = new FileReaderWriterImpl();
        $expectedContent = 'Sample Content';
        $readerWriter->writeFile(
            vfsStream::url('exampleDir' . DIRECTORY_SEPARATOR . 'sample_write_3.php'),
            $expectedContent);
        $readerWriter->copyFile(vfsStream::url('exampleDir' . DIRECTORY_SEPARATOR . 'sample_write_3.php'),
            vfsStream::url('exampleDir' .  DIRECTORY_SEPARATOR . 'sample_write_3_copy.php'));
        $this->assertTrue($this->rootDir->hasChild('sample_write_3_copy.php'));
    }

    public function testResolvePathUsingRealRelativePath()
    {
        $readerWriter = new FileReaderWriterImpl();
        //resolve the path of this current file
        $absPath = $readerWriter->resolvePath('tests' . DIRECTORY_SEPARATOR . 'FileReaderWriterImplTest.php');
        $this->assertEquals('tests' . DIRECTORY_SEPARATOR . 'FileReaderWriterImplTest.php', $absPath);
    }

    public function testResolvePathUsingStreamJustDir()
    {
        $readerWriter = new FileReaderWriterImpl();//Create the src directory structure with just the custom folder
        $structure = array(
            'src' => array(),
            'pkg' => array(),
        );

        vfsStream::create($structure);

        //resolve the path of the "pkg" directory
        $absPath = $readerWriter->resolvePath(vfsStream::url('exampleDir/pkg'));
        $this->assertEquals(vfsStream::url('exampleDir/pkg'), $absPath);
    }

    public function testGetFilesFromDirectorySimpleOneDirectory()
    {
        //Create the src directory structure with just the custom folder
        $structure = array(
            'src' => array(
                'custom' => array(
                    'clients' => array(
                        'base' => array(
                            'api' => array(
                                'WOM2Api.php' => '<?php echo "Hello";',
                                '.gitkeep' => 'this should be ignored',
                                'file_to_ignore.txt' => 'this should also be ignored',
                            ),
                        ),
                    ),
                ),
            ),
        );

        vfsStream::create($structure);
        //echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());

        $srcDir = vfsStream::url('exampleDir/src');
        $readerWriter->addPathMapping($srcDir, $srcDir);

        $files = $readerWriter->getFilesFromDirectory($srcDir,
            '.gitkeep', 'file_to_ignore.txt');
        $this->assertCount(1, $files);
        $expectedFilePath = 'custom/clients/base/api/WOM2Api.php';
        $this->assertTrue(array_key_exists($expectedFilePath, $files));
    }

    public function testGetFilesFromDirectoryWithEmptySource()
    {
        //Create the src directory structure with just the custom folder
        $structure = array(
            'src' => array(
                'custom' => array(
                    'clients' => array(
                        'base' => array(
                            'api' => array(
                                '.gitkeep' => 'this should be ignored',
                                'file_to_ignore.txt' => 'this should also be ignored',
                            ),
                        ),
                    ),
                ),
                '.gitkeep' => 'ignore my gitkeep file',
            ),
        );

        vfsStream::create($structure);
        //echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());

        $srcDir = vfsStream::url('exampleDir/src');
        $readerWriter->addPathMapping($srcDir, $srcDir);

        $files = $readerWriter->getFilesFromDirectory($srcDir,
            '.gitkeep', 'file_to_ignore.txt');

        $this->assertCount(0, $files);
    }

}