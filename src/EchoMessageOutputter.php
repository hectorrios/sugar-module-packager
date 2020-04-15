<?php


namespace SugarModulePackager;


class EchoMessageOutputter implements MessageOutputter
{
    public function message($out = '')
    {
        if (empty($out)) {
            return;
        }

        echo $out . PHP_EOL;
    }

}