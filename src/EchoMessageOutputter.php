<?php


namespace SugarModulePackager;


class EchoMessageOutputter implements MessageOutputter
{
    public function message($out = '')
    {
        $formattedMessage = $out . PHP_EOL;
        echo $formattedMessage;
    }

}