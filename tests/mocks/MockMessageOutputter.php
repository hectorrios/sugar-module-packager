<?php


namespace SugarModulePackager\Test\Mocks;


use SugarModulePackager\MessageOutputter;

class MockMessageOutputter implements MessageOutputter
{

    /* @var array $messages */
    private $messages = array();

    /* @var bool $enableEcho */
    private $enableEcho = false;

    /**
     *
     */
    public function toggleEnableEcho()
    {
        $this->enableEcho = !$this->enableEcho;
    }

    /**
     * @return bool
     */
    public function isEnableEcho()
    {
        return $this->enableEcho;
    }



    public function message($out = '')
    {
        if (empty($out)) {
            return;
        }

        $formattedMessage = $out . PHP_EOL;
        $this->messages[] = $formattedMessage;
        if ($this->enableEcho) {
            echo $formattedMessage;
        }
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