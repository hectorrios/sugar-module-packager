<?php


namespace SugarModulePackager\Test\Mocks;


use SugarModulePackager\MessageOutputter;

class MockMessageOutputter implements MessageOutputter
{

    /* @var array $messages */
    private $messages = array();

    public function message($out = '')
    {
        if (empty($out)) {
            return;
        }

        $formattedMessage = $out . PHP_EOL;
        $this->messages[] = $formattedMessage;
    }

    public function getLastMessage()
    {

        $messageCount = count($this->messages);
        if (empty($this->messages)) {
            return '';
        }

        return $this->messages[count($this->messages) - 1];
    }

}