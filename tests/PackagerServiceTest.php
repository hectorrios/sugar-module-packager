<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\FileReaderWriterImpl;
use SugarModulePackager\ManifestIncompleteException;
use SugarModulePackager\PackagerConfiguration;
use SugarModulePackager\PackagerService;
use SugarModulePackager\Test\Mocks\MockMessageOutputter;
use SugarModulePackager\Test\Mocks\ReaderWriterTestDecorator;

class PackagerServiceTest extends TestCase
{
    /* @var vfsStreamDirectory */
    private $rootDir;

    private $rootDirName = 'exampleDir';

    protected function setUp()
    {
        $this->rootDir = vfsStream::setup($this->rootDirName);
    }


    public function testGetManifestFileContentsNonExistingManifest()
    {
        $readerWriter = new FileReaderWriterImpl(vfsStream::url($this->rootDirName));
//        $config = new PackagerConfiguration('0.0.1');
        $service = new PackagerService($readerWriter);

        $this->expectException(ManifestIncompleteException::class);

        $service->getManifestFileContents(vfsStream::url($this->rootDirName .
            DIRECTORY_SEPARATOR . 'manifest.php'));
    }

    public function testGetManifestFileContentsWithBareMinimum()
    {
        $config = new PackagerConfiguration("0.0.1");

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));
        //Create a base template lacking all of the main thing
        $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = 'id_001';".PHP_EOL.
            "\$manifest['built_in_version'] = '9.3';".PHP_EOL.
            "\$manifest['version'] = '0.0.1';".PHP_EOL.
            "\$manifest['name'] = 'test case';".PHP_EOL.
            "\$manifest['description'] = '';".PHP_EOL.
            "\$manifest['author'] = 'Sugar Partner';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".
                $config->getManifestDefaultInstallVersionString() .";";

        $readerWriter = new FileReaderWriterImpl(vfsStream::url($this->rootDirName));

        $readerWriter->writeFile($config->getManifestFile(),
            $manifestContent);

        $this->assertTrue($this->rootDir->hasChild($config->getManifestFile()));

        $pService = new PackagerService($readerWriter);
        $contents =
            $pService->getManifestFileContents(
                vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . $config->getManifestFile()));

        $this->assertIsArray($contents, 'the contents retrieved should be an array');
        $this->assertArrayHasKey('author', $contents);
    }

    public function testCreatePackageDirectories()
    {
        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);

        $directories = [
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'config'),
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'releases' ),
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'src' ),
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'pkg' ),
        ];

        $pService->createPackagerDirectories(...$directories);
        $this->assertTrue($this->rootDir->hasChildren());
        $this->assertTrue($this->rootDir->hasChild('config'));
        $this->assertTrue($this->rootDir->hasChild('releases'));
        $this->assertTrue($this->rootDir->hasChild('src'));
        $this->assertTrue($this->rootDir->hasChild('pkg'));
    }

    public function testWipePkgDirectoryBasic()
    {

        $readerWriter =
            new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $pkgDirectory = vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . "pkg");

        $readerWriter->createDirectory($pkgDirectory);
        $this->assertTrue($this->rootDir->hasChild("pkg"));
        $readerWriter->writeFile($pkgDirectory . DIRECTORY_SEPARATOR . 'somefile.php',
            '<?php echo \'Hello There \';');
        $this->assertTrue($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR . 'somefile.php'));

        $readerWriter->addPathMapping($pkgDirectory, $pkgDirectory);

        $pService->wipeDirectory($pkgDirectory);

        $this->assertFalse($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR . 'somefile.php'));

    }

    public function testWipePkgDirectoryWithAFewFilesAndSubDirectories()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $pkgDirectory = vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . "pkg");
        $this->assertFalse($this->rootDir->hasChild('pkg'));
        $this->assertFalse($this->rootDir->hasChild('somefile.php'));

        $readerWriter->createDirectory($pkgDirectory);
        $readerWriter->createDirectory($pkgDirectory . DIRECTORY_SEPARATOR . 'subDirOne');
        $readerWriter->createDirectory($pkgDirectory . DIRECTORY_SEPARATOR . 'subDirTwo');

        $readerWriter->writeFile($pkgDirectory . DIRECTORY_SEPARATOR . 'subDirOne' .
            DIRECTORY_SEPARATOR . 'somefile.php',
            '<?php echo \'Hello There \';');
        $this->assertFalse($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR . 'somefile.php'));
        $this->assertTrue($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR .
            'subDirOne' . DIRECTORY_SEPARATOR . 'somefile.php'));

        $readerWriter->writeFile($pkgDirectory . DIRECTORY_SEPARATOR . 'subDirTwo' .
            DIRECTORY_SEPARATOR . 'logic_hook.php',
            '<?php echo \'Hello There Again\';');
        $this->assertFalse($this->rootDir->hasChild($pkgDirectory . DIRECTORY_SEPARATOR . 'subDirOne' .
            DIRECTORY_SEPARATOR . 'logic_hook.php'));

        $readerWriter->addPathMapping($pkgDirectory, $pkgDirectory);

        $pService->wipeDirectory($pkgDirectory);
        $this->assertTrue($this->rootDir->hasChild('pkg/subDirOne'));
        $this->assertTrue($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR . 'subDirTwo'));
        $this->assertFalse($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR
            . 'subDirTwo' . DIRECTORY_SEPARATOR . 'logic_hook.php'));
        $this->assertFalse($this->rootDir->hasChild('pkg' . DIRECTORY_SEPARATOR
            . 'subDirOne' . DIRECTORY_SEPARATOR . 'somefile.php'));
        $this->assertTrue($this->rootDir->hasChild('pkg'));
        //echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);
    }


//    public function testCopySrcDirectoryToDestinationDir()
//    {
//        Create the src directory structure with just the custom folder
//        $structure = array(
//            'src' => array(
//                'custom' => array(
//                    'clients' => array(
//                        'base' => array(
//                            'api' => array(
//                                'WOM2Api.php' => '<?php echo "Hello";',
//                            ),
//                        ),
//                    ),
//                ),
//            ),
//            'pkg' => array(),
//        );
//
//        vfsStream::create($structure);
//        echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);
//        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
//        $pService = new PackagerService($readerWriter);
//
//        $srcDir = vfsStream::url('exampleDir/src');
//        $readerWriter->addPathMapping($srcDir, $srcDir);
//        $pService->copySrcDirectoryToDestinationDir($srcDir, vfsStream::url('exampleDir/pkg'));
//        $this->assertTrue($this->rootDir->hasChild('pkg/custom/clients/base/api/WOM2Api.php'));
//    }

    public function testLoadTemplateConfigWithNoTemplateConfig()
    {
        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);

        $structure = array(
            'src' => array(

            ),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
            ),
        );

        vfsStream::create($structure);
//        echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);

        $config = $pService->loadTemplateConfiguration(vfsStream::url('exampleDir/configuration/templates.php'));

        $this->assertNull($config);
        $structureAfter = vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure();
        $this->assertTrue(array_key_exists('src', $structureAfter['exampleDir']));
        $this->assertTrue(empty($structureAfter['exampleDir']['src']));
    }

    public function testLoadTemplateConfigurationWithExistingTemplateConfigButIncorrect()
    {

        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);

        $structure = array(
            'src' => array(

            ),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => 'should be a php file',
            ),
        );

        vfsStream::create($structure);

        $config = $pService->loadTemplateConfiguration(vfsStream::url('exampleDir/configuration/templates.php'));
        $this->assertNull($config);
    }

    public function testLoadTemplateConfigurationWithExistingAndValidButEmptyTemplateConfig()
    {

        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);

        $structure = array(
            'src' => array(

            ),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL;',
            ),
        );

        vfsStream::create($structure);

        $config = $pService->loadTemplateConfiguration(vfsStream::url('exampleDir/configuration/templates.php'));
        $this->assertNull($config);
    }

    public function testLoadTemplateConfigurationWithExistingAndValidTemplateConfig()
    {

        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

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
        $readerWriter->addPathMapping('template1', vfsStream::url('exampleDir/template1'));

        $config = $pService->loadTemplateConfiguration(vfsStream::url('exampleDir/configuration/templates.php'));

        $this->assertNotNull($config);
        $this->assertIsArray($config);
        $this->assertTrue(array_key_exists('template1', $config));
        $this->assertTrue(array_key_exists('directory_pattern', $config['template1']));
        $this->assertTrue(array_key_exists('modules', $config['template1']));
        $this->assertCount(4, $config['template1']['modules']);
    }

    public function testGenerateTemplatedConfiguredFilesWithNonExistentTemplatesSrcDirectory()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $templates['template1'] = array(
                'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
                'modules' => array(
                    'Contacts' => 'Contact',
                    'Accounts' => 'Account',
                    'Cases' => 'Case',
                    'Opportunities' => 'Oportunity',
                )
            );

        $structure = array(
            'src' => array(),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL;',
            ),
        );

        vfsStream::create($structure);
        $messenger = new MockMessageOutputter();
        $pService->generateTemplatedConfiguredFiles($templates, $messenger, vfsStream::url('exampleDir/pkg'));
        $this->assertFalse($this->rootDir->hasChild('src/custom/Extension'));
        $this->assertEquals('The template1 was not found.' . PHP_EOL, $messenger->getLastMessage());
    }

    public function testGenerateTemplatedConfiguredFilesWithExistingTemplateSrcDirectory()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $templates['template1'] = array(
            'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
            'modules' => array(
                'Contacts' => 'Contact',
                'Accounts' => 'Account',
                'Cases' => 'Case',
                'Opportunities' => 'Oportunity',
            )
        );

        $structure = array(
            'src' => array(),
            'pkg' => array(),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL;',
            ),
            'template1' => array(
                'clients' => array(
                    'base' => array(
                        'views' => array(
                            'record' => array (
                                'remove_copy.php' => '<?php

$module = \'{MODULENAME}\';

foreach($viewdefs[$module][\'base\'][\'view\'][\'record\'][\'buttons\'] as $key => $value) {
    if($value[\'type\'] == \'actiondropdown\' && $value[\'name\'] == \'main_dropdown\') {
        if(!empty($value[\'buttons\'])) {
            foreach($value[\'buttons\'] as $button_key => $button) {
                if(!empty($button[\'name\']) && $button[\'name\'] == \'duplicate_button\') {
                    unset($viewdefs[$module][\'base\'][\'view\'][\'record\'][\'buttons\'][$key][\'buttons\'][$button_key]);
                }
            }
        }
    }
}',
                            ),
                        ),
                    ),
                ),
            ),
        );

        vfsStream::create($structure);

        $readerWriter->addPathMapping('template1',
            vfsStream::url('exampleDir/template1'));
        $readerWriter->addPathMapping(vfsStream::url('exampleDir/template1'),
            vfsStream::url('exampleDir/template1'));

        $messenger = new MockMessageOutputter();
        $pService->generateTemplatedConfiguredFiles($templates, $messenger, vfsStream::url('exampleDir/pkg'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension'));
//        echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Accounts'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Contacts'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Cases'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Opportunities'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Accounts/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Contacts/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Cases/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Opportunities/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Accounts/Ext/clients/base/views/record/remove_copy.php'));
    }

    public function testBuildUpInstallDefsWithNoCustomInstallDefs()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $templates['template1'] = array(
            'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
            'modules' => array(
                'Contacts' => 'Contact',
                'Accounts' => 'Account',
                'Cases' => 'Case',
                'Opportunities' => 'Opportunity',
            )
        );

        $structure = array(
            'src' => array(
            ),
            'pkg' => array(
                'custom' => array(
                    'clients' => array(
                        'base' => array(
                            'api' => array(
                                'WOM2Api.php' => '<?php echo "Hello";',
                            ),
                        ),
                    ),
                ),
            ),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
            ),
        );

        vfsStream::create($structure);

        $fileList = $readerWriter->getFilesFromDirectory(vfsStream::url('exampleDir/pkg'));

        $finalInstallDefs = $pService->buildUpInstallDefs($fileList, 'my_id_001', new MockMessageOutputter(),'',
            function($file_relative, $installDefs) {
            return true;
            }
        );

        $this->assertIsArray($finalInstallDefs);
        $this->assertArrayHasKey('copy', $finalInstallDefs);
        $this->assertCount(1, $finalInstallDefs);
    }

    public function testBuildUpInstallDefsWithCustomInstallDefs()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $structure = array(
            'src' => array(
            ),
            'pkg' => array(
                'custom' => array(
                    'clients' => array(
                        'base' => array(
                            'api' => array(
                                'WOM2Api.php' => '<?php echo "Hello";',
                            ),
                        ),
                    ),
                ),
                'modules' => array(
                    'Packages' => array(
                      'Packages.php' => '<?php echo \'Hello\'',
                    ),
                ),
            ),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'installdefs.php' => '<?php

$installdefs[\'beans\'] = array (
    0 =>
        array (
            \'module\' => \'Packages\',
            \'class\' => \'Packages\',
            \'path\' => \'modules/Packages/Packages.php\',
            \'tab\' => false,
        ),
);',
            ),
        );

        vfsStream::create($structure);


        $fileList = $readerWriter->getFilesFromDirectory(vfsStream::url('exampleDir/pkg'));

        $finalInstallDefs = $pService->buildUpInstallDefs($fileList, 'my_id_001', new MockMessageOutputter(),
            vfsStream::url('exampleDir/configuration/installdefs.php'),
            function($file_relative, $installDefs) {
                return true;
            }
        );

        $this->assertIsArray($finalInstallDefs);
        $this->assertArrayHasKey('copy', $finalInstallDefs);
        $this->assertCount(2, $finalInstallDefs);
        //echo print_r($finalInstallDefs, true);
    }

    public function testBuildFinalManifestWithEmptyManifestBase()
    {
        $installdefsLocal = array();
        $installdefsLocal['beans'] = array (
            0 =>
                array (
                    'module' => 'ToothbrushSettings',
                    'class' => 'ToothbrushSettings',
                    'path' => 'modules/ToothbrushSettings/ToothbrushSettings.php',
                    'tab' => false,
                ),
        );

        $installdefsLocal['language'] = array (
            0 =>
                array (
                    'from' => '<basepath>/Language/en_us.lang.php',
                    'to_module' => 'application',
                    'language' => 'en_us',
                ),
        );

        $installdefsLocal['image_dir'] = '<basepath>/icons';
        $installdefsLocal['copy'] = array(
            array(
                'from' => '<basepath>/custom/clients/base/api/WOM2Api.php',
                'to' => 'custom/clients/base/api/WOM2Api.php',
            ),
            array (
                'from' => '<basepath>/modules/Packages/Packages.php',
                'to' => 'modules/Packages/Packages.php'
            ),
        );

        $manifestLocal = array();
        $manifestLocal['id'] = 'dummy package';
        $manifestLocal['built_in_version'] = '9.0';
        $manifestLocal['name'] = 'dummy_utility';
        $manifestLocal['description'] = 'does nothing';
        $manifestLocal['author'] = 'phpunit';
        $manifestLocal['acceptable_sugar_versions']['regex_matches'] = array('^9.[\d]+.[\d]+$');

        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);
        $output = $pService->buildFinalManifest($manifestLocal, $installdefsLocal);

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
//        echo $output . PHP_EOL;
        $manifest = array();
        $installdefs = array();
        //Now let's write it out to file and then include it to make some assertions on the contents
        $readerWriter->writeFile(vfsStream::url('exampleDir/myManifest.php'), $output);
//        unset($manifest);
        require(vfsStream::url('exampleDir/myManifest.php'));
        $this->assertTrue($this->rootDir->hasChild('myManifest.php'));
        $this->assertIsArray($manifest);
        $this->assertIsArray($installdefs);
        $this->assertNotEmpty($manifest);
        $this->assertNotEmpty($installdefs);

        $this->assertArrayHasKey('copy', $installdefs);
        $this->assertCount(2, $installdefs['copy']);
        $this->assertArrayHasKey('beans', $installdefs);
        $this->assertCount(1, $installdefs['beans']);
        $this->assertCount(4, $installdefs['beans'][0]);
    }

}
