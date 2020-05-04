<?php

namespace SugarModulePackager\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PHPUnit\Framework\TestCase;
use SugarModulePackager\Error\TemplateGenerationError;
use SugarModulePackager\FileReaderWriterImpl;
use SugarModulePackager\Error\IllegalStateException;
use SugarModulePackager\Error\ManifestIncompleteException;
use SugarModulePackager\Packager;
use SugarModulePackager\PackagerConfiguration;
use SugarModulePackager\PackagerService;
use SugarModulePackager\Test\Mocks\MockMessageOutputter;
use SugarModulePackager\Test\Mocks\ReaderWriterTestDecorator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PackagerServiceTest extends TestCase
{
    /* @var vfsStreamDirectory */
    private $rootDir;

    private $rootDirName = 'root';

    private $softwareName = 'SugarModulePackager';

    private $softwareVersion = '0.2.3';

    protected function setUp()
    {
        $this->rootDir = vfsStream::setup($this->rootDirName);
    }

    public function testGetManifestFileContentsNonExistingManifest()
    {
        $readerWriter = new FileReaderWriterImpl(vfsStream::url($this->rootDirName));
        $service = new PackagerService($readerWriter);

        $contents = $service->getManifestFileContents(vfsStream::url($this->rootDirName .
            DIRECTORY_SEPARATOR . 'manifest.php'), '0.0.1');
        $this->assertIsBool($contents);
        $this->assertFalse($contents);
    }

    public function testGetManifestFileContentsWithBareMinimum()
    {
        $config = new PackagerConfiguration("0.0.1", $this->softwareName, $this->softwareVersion);

        $this->assertFalse($this->rootDir->hasChild($config->getManifestFile()));
        //Create a base template lacking all of the main thing
        $manifestContent = "<?php" . PHP_EOL . "\$manifest['id'] = 'id_001';" . PHP_EOL .
            "\$manifest['built_in_version'] = '9.3';" . PHP_EOL .
            "\$manifest['version'] = '0.0.1';" . PHP_EOL .
            "\$manifest['name'] = 'test case';" . PHP_EOL .
            "\$manifest['description'] = '';" . PHP_EOL .
            "\$manifest['author'] = 'Sugar Partner';" . PHP_EOL .
            "\$manifest['acceptable_sugar_versions']['regex_matches'] = " .
                $config->getManifestDefaultInstallVersionString() . ";";

        $readerWriter = new FileReaderWriterImpl(vfsStream::url($this->rootDirName));

        $readerWriter->writeFile($config->getManifestFile(),
            $manifestContent);

        $this->assertTrue($this->rootDir->hasChild($config->getManifestFile()));

        $pService = new PackagerService($readerWriter);
        $contents =
            $pService->getManifestFileContents(
                vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . $config->getManifestFile()),
                '0.0.1');

        $this->assertIsArray($contents, 'the contents retrieved should be an array');
        $this->assertArrayHasKey('author', $contents);
        $this->assertArrayHasKey('version', $contents);
    }

    public function testGetManifestFileContentsWhereManifestFailsBasicValidation()
    {
        $config = new PackagerConfiguration('0.0.2', $this->softwareName, $this->softwareVersion);

        //the manifest is missing the "description" key which is ok but the
        // author field for example cannot be empty
        $structure = [
          "configuration" => [
              'manifest.php' => "<?php" . PHP_EOL . "\$manifest['id'] = 'id_001';" . PHP_EOL .
                  "\$manifest['built_in_version'] = '9.3';" . PHP_EOL .
                  "\$manifest['version'] = '0.0.1';" . PHP_EOL .
                  "\$manifest['name'] = 'test case';" . PHP_EOL .
                  "\$manifest['author'] = '';" . PHP_EOL .
                  "\$manifest['acceptable_sugar_versions']['regex_matches'] = " .
                  $config->getManifestDefaultInstallVersionString() . ";",
          ],
        ];

        vfsStream::create($structure);

        $pService = new PackagerService(new FileReaderWriterImpl());
        $this->expectException(ManifestIncompleteException::class);
        $contents = $pService->getManifestFileContents(vfsStream::url($this->rootDirName .DIRECTORY_SEPARATOR .
            'configuration' . DIRECTORY_SEPARATOR . $config->getManifestFile()), '0.0.1');

    }

    public function testCreatePackageDirectories()
    {
        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);

        $directories = array(
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'config'),
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'releases' ),
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'src' ),
            vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'pkg' ),

        );

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

        $config = $pService->loadTemplateConfiguration(vfsStream::url($this->rootDirName . '/configuration/templates.php'));

        $this->assertNull($config);
        $structureAfter = vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure();
        $this->assertArrayHasKey('src', $structureAfter[$this->rootDirName]);
        $this->assertTrue(empty($structureAfter[$this->rootDirName]['src']));
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
                'templates.php' => '<?php $message = "should be a php file";',
            ),
        );

        vfsStream::create($structure);

        $config = $pService->loadTemplateConfiguration(vfsStream::url($this->rootDirName . '/configuration/templates.php'));
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

        $config = $pService->loadTemplateConfiguration(vfsStream::url($this->rootDirName . '/configuration/templates.php'));
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
        $readerWriter->addPathMapping('template1', vfsStream::url($this->rootDirName . '/template1'));

        $config = $pService->loadTemplateConfiguration(vfsStream::url($this->rootDirName . '/configuration/templates.php'));

        $this->assertNotNull($config);
        $this->assertIsArray($config);
        $this->assertTrue(array_key_exists('template1', $config));
        $this->assertTrue(array_key_exists('directory_pattern', $config['template1']));
        $this->assertTrue(array_key_exists('modules', $config['template1']));
        $this->assertCount(4, $config['template1']['modules']);
    }

    /**
     * a test for documentation purposes on how to use the Twig templating
     * library in case there is a need to make changes to generateTemplatedConfiguredFiles
     * on PackagerService
     */
    public function testTwigSimpleExample()
    {
        $structure = array(
            'templates' => array(
                'sample_template.php' =>
                    '<?php $config = array(\'module_name\' => \'{{module}}\', \'table_name\' => \'{{tableName}}\',)' .
                        PHP_EOL,
            ),
        );

        vfsStream::create($structure);

        $loader =
            new FilesystemLoader(vfsStream::url($this->rootDirName . DIRECTORY_SEPARATOR . 'templates'));

        $twig = new Environment($loader);

        $mergedContents = $twig->render('sample_template.php', array('module' => 'Contacts',
            'tableName' => 't_contacts' ));
        $expectedContents =
            '<?php $config = array(\'module_name\' => \'Contacts\', \'table_name\' => \'t_contacts\',)' . PHP_EOL;
        $this->assertEquals($expectedContents, $mergedContents);

    }

    public function testGenerateTemplatedConfiguredFilesWhereTheBaseTemplatesDirIsNonExistent()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $config = new PackagerConfiguration('0.0.6', $this->softwareName, $this->softwareVersion,
            $this->rootDir->url());

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
            'src' => array(),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL;',
            ),
        );

        vfsStream::create($structure);

        $messenger = new MockMessageOutputter();
        $this->expectException(IllegalStateException::class);
        $pService->generateTemplatedConfiguredFiles($templates, $messenger, $config);
    }

    /**
     * test the case where "template1" directory does not exist under the "templates" directory
     */
    public function testGenerateTemplatedConfiguredFilesWithNonExistentTemplatesSrcDirectory()
    {
        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $pService = new PackagerService($readerWriter);

        $config = new PackagerConfiguration('0.0.6', $this->softwareName, $this->softwareVersion,
            $this->rootDir->url());

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
            'src' => array(),
            'templates' => array(),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL;',
            ),
        );

        vfsStream::create($structure);

        $messenger = new MockMessageOutputter();
        $this->expectException(IllegalStateException::class);
        $pService->generateTemplatedConfiguredFiles($templates, $messenger, $config);
    }

    public function testGenerateTemplatedConfiguredFilesWithExistingTemplateSrcDirectory()
    {
        $config = new PackagerConfiguration('0.0.6', $this->softwareName, $this->softwareVersion,
            $this->rootDir->url());
        $readerWriter = new FileReaderWriterImpl();
        $pService = new PackagerService($readerWriter);

        $templates['template1'] = array(
            'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
            'modules' => array(
                'Contacts' => array(
                    'singular' => 'Contact',
                ),
                'Accounts' => array(
                  'singular' => 'Account',
                ),
                'Cases' => array(
                    'singular' => 'Case',
                    'tableName' => 't_cases',
                ),
                'Opportunities' => array(
                   'singular' => 'Opportunity',
                )
            ),
        );

        $templates['template2'] = array(
            'directory_pattern' => 'custom/Extension/modules/{MODULENAME}/Ext',
            'modules' => array(
                'Contacts' => array(
                   'singular' => 'Contact',
                    'tableName' => 't_contacts',
                ),
                'Accounts' => array(
                    'singular' => 'Account',
                    'tableName' => 't_accounts',
                ),
                'Cases' => array(
                    'singular' => 'Case',
                    'tableName' => 't_cases',
                ),
                'Opportunities' => array(
                    'singular' => 'Opportunity',
                    'tableName' => 't_opportunities'
                ),
            ),
        );

        $structure = array(
            'src' => array(),
            'pkg' => array(),
            'configuration' => array(
                'manifest.php' => '<?php echo "my manifest file";',
                'templates.php' => '<?php PHP_EOL;',
            ),
            'templates' => array(
                'template1' => array(
                    'clients' => array(
                        'base' => array(
                            'views' => array(
                                'record' => array (
                                    'define_config_array.php' => '<?php

$test_config_array_1 = array(
    \'module\' => \'{{module}}\',
    \'table\' => \'{{tableName}}\',
    \'hostName\' => \'localhost\',
);
',
                                ),
                            ),
                        ),
                    ),
                ), //end template1
                'template2' => array(
                    'clients' => array(
                        'base' => array(
                            'views' => array(
                                'record' => array (
                                    'logic_hook_def.php' => '<?php

$template_2_def = array(
    \'module\' => \'{{module}}\',
    \'table\' => \'{{tableName}}\',
);
',
                                ),
                            ),
                        ),
                    ),
                ), //end template2
            ),
        );

        vfsStream::create($structure);

        $messenger = new MockMessageOutputter();
        //echo print_r(vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure(), true);
        $pService->generateTemplatedConfiguredFiles($templates, $messenger, $config);

        /*
         * assert that the files were generated in the "pkg" directory
         */
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Accounts'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Contacts'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Cases'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/Extension/modules/Opportunities'));

        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Accounts/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Contacts/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Cases/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Opportunities/Ext/clients/base/views/record'));
        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Accounts/Ext/clients/base/views/record/define_config_array.php'));
        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Cases/Ext/clients/base/views/record/define_config_array.php'));
        $this->assertTrue($this->rootDir->hasChild(
            'pkg/custom/Extension/modules/Accounts/Ext/clients/base/views/record/logic_hook_def.php'));

        /*
         * Load some of the generated files to see if the placeholders were correctly set
         */
        require($config->getPathToPkgDir() .
            '/custom/Extension/modules/Cases/Ext/clients/base/views/record/define_config_array.php');
        $this->assertTrue(isset($test_config_array_1));
        $this->assertEquals('t_cases', $test_config_array_1['table']);

        require($config->getPathToPkgDir() .
            '/custom/Extension/modules/Accounts/Ext/clients/base/views/record/logic_hook_def.php');
        $this->assertTrue(isset($template_2_def));
        $this->assertEquals('t_accounts', $template_2_def['table']);

        require($config->getPathToPkgDir() .
            '/custom/Extension/modules/Opportunities/Ext/clients/base/views/record/logic_hook_def.php');
        $this->assertTrue(isset($template_2_def));
        $this->assertEquals('t_opportunities', $template_2_def['table']);
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

        $fileList = $readerWriter->getFilesFromDirectory(vfsStream::url($this->rootDirName . '/pkg'));

        $finalInstallDefs = $pService->buildUpInstallDefs($fileList, 'my_id_001', new MockMessageOutputter(),
            function($file_relative, $installDefs) {
                return true;
            }
        );

        $this->assertIsArray($finalInstallDefs);
        $this->assertArrayHasKey('copy', $finalInstallDefs);
        $this->assertCount(2, $finalInstallDefs);
        $this->assertCount(1, $finalInstallDefs['copy']);
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


        $fileList = $readerWriter->getFilesFromDirectory(vfsStream::url($this->rootDirName . '/pkg'));

        $finalInstallDefs = $pService->buildUpInstallDefs($fileList, 'my_id_001', new MockMessageOutputter(),
            function($file_relative, $installDefs) {
                return true;
            },
            vfsStream::url($this->rootDirName . '/configuration/installdefs.php')
        );

        $this->assertIsArray($finalInstallDefs);
        $this->assertArrayHasKey('copy', $finalInstallDefs);
        $this->assertCount(3, $finalInstallDefs);
        $this->assertCount(2, $finalInstallDefs['copy']);
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

        $manifest = array();
        $installdefs = array();
        //Now let's write it out to file and then include it to make some assertions on the contents
        $readerWriter->writeFile(vfsStream::url($this->rootDirName . '/myManifest.php'), $output);

        require(vfsStream::url($this->rootDirName . '/myManifest.php'));

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

    public function testGenerateZipPackage()
    {
        $structure = array(
            '/' => array(
                'tmp' => array(
                ),
            ),
            'src' => array(
            ),
            'releases' => array(),
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

        $this->assertTrue($this->rootDir->hasChild('releases'));

        $manifestLocal = array();
        $manifestLocal['id'] = 'dummy package';
        $manifestLocal['built_in_version'] = '9.0';
        $manifestLocal['name'] = 'dummy_utility';
        $manifestLocal['description'] = 'does nothing';
        $manifestLocal['author'] = 'phpunit';
        $manifestLocal['acceptable_sugar_versions']['regex_matches'] = array('^9.[\d]+.[\d]+$');

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

        $readerWriter = new ReaderWriterTestDecorator(new FileReaderWriterImpl());
        $readerWriter->addPathMapping('pkg', vfsStream::url($this->rootDirName . '/pkg'));

        $listOfFiles = $readerWriter->getFilesFromDirectory(vfsStream::url($this->rootDirName . '/pkg'));
        //echo print_r($listOfFiles, true) . PHP_EOL;
        $this->assertCount(2, $listOfFiles);

        $manifestContent = $this->constructSampleFinalManifest($installdefsLocal, $manifestLocal);

        $config = new PackagerConfiguration('v0.0.1', $this->softwareName, $this->softwareVersion,
            $this->rootDir->url());
        $pService = new PackagerService($readerWriter);
        $messenger = new MockMessageOutputter();
//        $messenger->toggleEnableEcho();

        $archiver = $this->getMockBuilder("\ZipArchive")->getMock();
        $archiver->expects($this->exactly(2))
            ->method('addFile');

        $archiver->expects($this->once())
            ->method('open');
        $archiver->expects($this->once())
            ->method('close')
            ->will($this->returnValue(
                $readerWriter->writeFile(vfsStream::url($this->rootDirName . '/releases/test_001.zip'))));

        $pService->generateZipPackage($manifestContent, vfsStream::url($this->rootDirName . '/releases/test_001.zip'),
            $messenger, $listOfFiles, $config, $archiver);
        $this->assertTrue($this->rootDir->hasChild('pkg/manifest.php'));
        $this->assertTrue($this->rootDir->hasChild('releases/test_001.zip'));
    }

    public function testCreateSkeletonManifestFile()
    {
        $structure = [
           'configuration' => array(),
        ] ;
        vfsStream::create($structure);

        $config = new PackagerConfiguration('0.0.1', $this->softwareVersion, $this->softwareName,
            vfsStream::url($this->rootDirName));

        $this->assertFalse($this->rootDir->hasChild('configuration/manifest.php'));
        $pService = new PackagerService(new ReaderWriterTestDecorator(new FileReaderWriterImpl()));
        $this->expectException(ManifestIncompleteException::class);
        $pService->createSkeletonManifestFile($config);
        $this->assertTrue($this->rootDir->hasChild('configuration/manifest.php'));

    }

    public function testCreateAllPackagerDirectories()
    {
        $config = new PackagerConfiguration('0.0.1', $this->softwareName, $this->softwareVersion,
            $this->rootDir->url());

        $pService = new PackagerService(new FileReaderWriterImpl());
        $pService->createAllPackagerDirectories($config);

        $this->assertTrue($this->rootDir->hasChild('pkg'));
        $this->assertTrue($this->rootDir->hasChild('src'));
        $this->assertTrue($this->rootDir->hasChild('releases'));
        $this->assertTrue($this->rootDir->hasChild('configuration'));
        $this->assertTrue($this->rootDir->hasChild('templates'));
    }

    public function testCopySrcIntoPkg()
    {
        $structure = array(
            'src' => array(
                'custom' => array(
                    'clients' => array(
                        'base' => array(
                            'api' => array(
                                'WOM2Api.php' => '<?php echo "Hello";',
                                '.gitkeep' => 'this should be ignored',
                                '.DS_Store' => 'this should also be ignored',
                            ),
                        ),
                    ),
                ),
            ),
            'pkg' => array(),
        );

        vfsStream::create($structure);

        $config = new PackagerConfiguration('0.2.5', $this->softwareName, $this->softwareVersion,
            $this->rootDir->url());

        $pService = new PackagerService(new FileReaderWriterImpl());
        $pService->copySrcIntoPkg($config);
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/clients/base/api'));
        $this->assertTrue($this->rootDir->hasChild('pkg/custom/clients/base/api/WOM2Api.php'));
        $this->assertFalse($this->rootDir->hasChild('pkg/custom/clients/base/api/.gitkeep'));
        $this->assertFalse($this->rootDir->hasChild('pkg/custom/clients/base/api/.DS_Store'));

    }

    public function testGetFilesFromDirectory()
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
                    '.gitignore' => '.idea/' . PHP_EOL . '.git' . PHP_EOL,
                ),
            ),
            'pkg' =>array(),
        );

        vfsStream::create($structure);

        $pService = new PackagerService(new FileReaderWriterImpl());
        $files = $pService->getFilesFromDirectory($config->getPathToSrcDir(), '.gitignore');
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);
        $this->assertCount(5, $files);

    }

    private function constructSampleFinalManifest(array $installdefs, array $manifest)
    {
        if (!empty($installdefs['copy'])) {
            $installdefs_copy = $installdefs['copy'];
            unset($installdefs['copy']);
        } else {
            $installdefs_copy = array();
        }

        return sprintf(
            "<?php\n\n\$manifest = %s;\n\n\$installdefs = %s;\n\n\$installdefs['copy'] = %s;\n",
            var_export($manifest, true),
            var_export($installdefs, true),
            preg_replace('(\s+\d+\s=>)', '', var_export($installdefs_copy, true))
        );

    }
}
