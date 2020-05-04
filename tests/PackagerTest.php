<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\FileReaderWriterImpl;
use SugarModulePackager\Error\ManifestIncompleteException;
use SugarModulePackager\Packager;
use SugarModulePackager\PackagerConfiguration;
use SugarModulePackager\PackagerService;
use SugarModulePackager\Test\Mocks\MockMessageOutputter;

class PackagerTest extends TestCase
{
    /* @var vfsStreamDirectory */
    private $rootDir;

    private $rootDirName = 'root';

    public function setup()
    {
        $this->rootDir = vfsStream::setup($this->rootDirName);
    }

    public function testBuildWithNoVersionProvided()
    {
        $expectedMessage = 'Provide version number' . PHP_EOL;
        $messageOutputter = new MockMessageOutputter();
        $pConfig = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION);
        $pService = new PackagerService(new FileReaderWriterImpl());
        $packager = new Packager($pService, $messageOutputter, $pConfig);

        $packager->build('');
        $this->assertEquals($expectedMessage, $messageOutputter->getLastMessage());
    }

    public function testBuildThrowsManifestIncompleteExceptionWhenNoManifestExists()
    {
        $messageOutputter = new MockMessageOutputter();
        $pConfig = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));
        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);
        $packager = new Packager($pService, $messageOutputter, $pConfig);

        $this->expectException(ManifestIncompleteException::class);
        $packager->build('0.0.1');
        $this->assertTrue($this->rootDir->hasChild('configuration'));
        $this->assertTrue($this->rootDir->hasChild('src'));
        $this->assertTrue($this->rootDir->hasChild('pkg'));
        $this->assertTrue($this->rootDir->hasChild('releases'));
    }

    public function testBuildThrowsManifestExceptionWhenExistingManifestFailsValidation()
    {
        $config = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));

        $structure =array(
            "configuration" => array(
                'manifest.php' => "<?php" . PHP_EOL . "\$manifest['id'] = 'id_001';" . PHP_EOL .
                    "\$manifest['built_in_version'] = '9.3';" . PHP_EOL .
                    "\$manifest['version'] = '0.0.1';" . PHP_EOL .
                    "\$manifest['name'] = 'test case';" . PHP_EOL .
                    "\$manifest['author'] = '';" . PHP_EOL .
                    "\$manifest['acceptable_sugar_versions']['regex_matches'] = " .
                    $config->getManifestDefaultInstallVersionString() . ";",
            ),
        );

        vfsStream::create($structure);
        $pService = new PackagerService(new FileReaderWriterImpl());
        $messenger = new MockMessageOutputter();
        $messenger->toggleEnableEcho();
        $this->expectException(ManifestIncompleteException::class);
        $packager = new Packager($pService, $messenger, $config);

        $packager->build('0.0.2');
    }

    public function testBuildWhereZipFileToCreateAlreadyExists()
    {
        $config = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));

        $structure = array(
            "configuration" => array(
                'manifest.php' => "<?php" . PHP_EOL . "\$manifest['id'] = 'id_001';" . PHP_EOL .
                    "\$manifest['built_in_version'] = '9.3';" . PHP_EOL .
                    "\$manifest['version'] = '0.0.1';" . PHP_EOL .
                    "\$manifest['name'] = 'test case';" . PHP_EOL .
                    "\$manifest['author'] = 'Sugar Testing GmbH';" . PHP_EOL .
                    "\$manifest['acceptable_sugar_versions']['regex_matches'] = " .
                    $config->getManifestDefaultInstallVersionString() . ";",
            ),
            "releases" => array(
                'module_id_001_0.0.2.zip' => 'my zip file',
            ),
        );

        vfsStream::create($structure);
        $pService = new PackagerService(new FileReaderWriterImpl());
        $messenger = new MockMessageOutputter();

        $packager = new Packager($pService, $messenger, $config);

        $packager->build('0.0.2');
        $expectedMessage = 'Release vfs://root/releases/module_id_001_0.0.2.zip already exists!' . PHP_EOL;
        $this->assertEquals($expectedMessage, $messenger->getLastMessage());
    }

    public function testBuildWipingThePkgDirectory()
    {
        $config = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));

        $structure = array(
            'pkg' => array(
                'one.txt' => 'first file',
                'two.txt' => 'second file',
                'nested_dir' => array(
                    'nested_one.txt' => 'nested one, first file',
                    'nested_two.txt' => 'nested one, second file',
                    'nested_three.txt' => 'nested one, third file',
                ),
            ),
        );

        vfsStream::create($structure);

        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);
        $mockPService = $this->getMockBuilder(PackagerService::class)
            ->setConstructorArgs([new FileReaderWriterImpl()])
            ->getMock();

        $mockPService->expects($this->once())
            ->method('wipeDirectory')
            ->with($this->equalTo(vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'pkg')));

        $mockPService->expects($this->any())
            ->method('getFileReaderWriterService')
            ->will($this->returnValue($readerWriter));

        $mockPService->expects($this->once())
            ->method('getManifestFileContents')
            ->will($this->returnValue(array('author' => 'Sugar', 'id' => '000_test',)));

        $mockPService->expects($this->once())
            ->method('buildUpInstallDefs')
            ->withAnyParameters()
            ->will($this->returnValue(array()));

        $mockPService->expects($this->any())
            ->method('getFilesFromDirectory')
            ->will($this->returnValue(array()));

        $messenger = new MockMessageOutputter();
        $messenger->toggleEnableEcho();
        $packager = new Packager($mockPService, $messenger, $config);
        $packager->build('0.0.2');
    }

    public function testCopySrcIntoPkg()
    {
        $config = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));

        $structure = array(
            'src' => array(
                'one.txt' => 'first file',
                'two.txt' => 'second file',
                'nested_dir' => array(
                    'nested_one.txt' => 'nested one, first file',
                    'nested_two.txt' => 'nested one, second file',
                    'nested_three.txt' => 'nested one, third file',
                ),
            ),
            'pkg' => array(),
        );

        vfsStream::create($structure);

        $mockPService = $this->getMockBuilder(PackagerService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockPService->expects($this->once())
            ->method('copySrcIntoPkg')
            ->with($this->equalTo($config));

        $mockPService->expects($this->once())
            ->method('getManifestFileContents')
            ->will($this->returnValue(array('author' => 'Sugar', 'id' => '000_test',)));

        $mockPService->expects($this->any())
            ->method('getFilesFromDirectory')
            ->will($this->returnValue(array()));

        $mockPService->expects($this->once())
            ->method('buildUpInstallDefs')
            ->withAnyParameters()
            ->will($this->returnValue(array()));

        $messenger = new MockMessageOutputter();
        $messenger->toggleEnableEcho();
        $packager = new Packager($mockPService, $messenger, $config);
        $packager->build('1.0.2');
    }

    public function testLoadTemplateConfiguration()
    {
        $config = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));

        $templString = '$templates[\'template1\'] = array(
                \'directory_pattern\' => \'custom/Extension/modules/{MODULENAME}/Ext\',
                \'modules\' => array(
                    \'Contacts\' => \'Contact\',
                    \'Accounts\' => \'Account\',
                    \'Cases\' => \'Case\',
                    \'Opportunities\' => \'Opportunity\',
                )
            );';

        $structure = array(
            'src' => array(

            ),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL; ' . $templString,
            ),
            'template1' => array(),
        );

        vfsStream::create($structure);

        $messenger = new MockMessageOutputter();

       //stub out PackagerService
        $mockPService = $this->createMock(PackagerService::class);

        $mockPService->method('getManifestFileContents')
            ->will($this->returnValue(['id' => '001_test']));

        $mockPService->method('getFilesFromDirectory')
            ->will($this->returnValue([]));

        $mockPService->method('buildFinalManifest')
            ->withAnyParameters()
            ->will($this->returnValue(array()));

        $mockPService->method('buildUpInstallDefs')
            ->will($this->returnValue([]));

        $expectedTemplate =array(
            'template1' => array(
                'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
                'modules' => array(
                    'Contacts' => 'Contact',
                    'Accounts' => 'Account',
                    'Cases' => 'Case',
                    'Opportunities' => 'Opportunity',
                ),
            ),
        );

        $mockPService->method('loadTemplateConfiguration')
            ->will($this->returnValue($expectedTemplate));

        $mockPService->expects($this->once())
            ->method('generateTemplatedConfiguredFiles')
            ->with($this->equalTo($expectedTemplate));


        $packager = new Packager($mockPService, $messenger, $config);

        $packager->build('0.2.6');
    }

    public function testGenerateTemplatedConfiguredFiles()
    {
        //test that this method gets called once
        $config = new PackagerConfiguration('0.0.1', Packager::SW_NAME,
            Packager::SW_VERSION, vfsStream::url($this->rootDirName));

        $messenger = new MockMessageOutputter();

        $structure = array(
            'src' => array(),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL; ',
            ),
            'template1' => array(),
        );

        vfsStream::create($structure);

        $expectedTemplate = array(
            'template1' => array(
                'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
                'modules' => array(
                    'Contacts' => 'Contact',
                    'Accounts' => 'Account',
                    'Cases' => 'Case',
                    'Opportunities' => 'Opportunity',
                ),
            ),
        );

        $mockService = $this->createMock(PackagerService::class);

        $mockService->method('getManifestFileContents')
            ->will($this->returnValue(array('id' => 'sugar_test')));

        $mockService->method('buildUpInstallDefs')
            ->will($this->returnValue(array()));

        $mockService->method('loadTemplateConfiguration')
            ->will($this->returnValue($expectedTemplate));

        $mockService->method('getFilesFromDirectory')
            ->will($this->returnValue(array()));

        $mockService->expects($this->once())
            ->method('generateTemplatedConfiguredFiles');

        $packager = new Packager($mockService, $messenger, $config);
        $packager->build('0.0.4');

    }
}
