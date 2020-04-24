<?php

namespace SugarModulePackager\Test;

use PHPUnit\Framework\TestCase;
use SugarModulePackager\PackagerConfiguration;

class PackagerConfigurationTest extends TestCase
{

    public function test__construct()
    {
        $config = new PackagerConfiguration('0.0.1');
        $this->assertEquals('0.0.1', $config->getVersion());
    }

}
