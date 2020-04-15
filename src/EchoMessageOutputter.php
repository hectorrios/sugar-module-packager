<?php


namespace SugarModulePackager;


class EchoMessageOutputter implements MessageOutputter
{
    /* @var string $lastMessage */
    private $lastMessage;

    public function message($out = '')
    {
        if (empty($out)) {
            //reset lastMessage to blank
            $this->lastMessage = '';
            return;
        }

        $formattedMessage = $out . PHP_EOL;
        $this->lastMessage = $formattedMessage;
        echo $formattedMessage;
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }

}