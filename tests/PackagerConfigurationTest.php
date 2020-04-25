<?php

namespace SugarModulePackager\Test;

use PHPUnit\Framework\TestCase;
use SugarModulePackager\PackagerConfiguration;

class PackagerConfigurationTest extends TestCase
{

    public function test__construct()
    {
        $config = new PackagerConfiguration('0.0.1', 'SugarPackager', '0.2.2');
        $this->assertEquals('0.0.1', $config->getVersion());
        $info = 'SugarPackager v0.2.2';
        $this->assertEquals($info, $config->getSoftwareInfo());
    }

}
