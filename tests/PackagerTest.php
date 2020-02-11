<?php

namespace SugarModulePackager\Test;

use PHPUnit\Framework\TestCase;

class PackagerTest extends TestCase
{
    public function testSayMessage()
    {
        $startMessage = "Hello";
        $expectedMessage = "say Hello" . PHP_EOL;
        $packager = new \SugarModulePackager\Packager();
        $this->assertEquals($expectedMessage,
            $packager->sayMessage($startMessage));
        $this->assertTrue(true);

    }

}
