<?php

namespace pocketmine\cloud\network\protocol;

class ListServersPacket extends RequestPacket {

    public const NETWORK_ID = 4;

    public function pid() {
        return self::NETWORK_ID;
    }

    /** @var string */
    public $template;

    public $servers = [];

    protected function decodePayload() {
        $this->type = $this->getInt();
        $this->requestid = $this->getString();
        $this->template = $this->getString();
        $this->servers = json_decode($this->getString());
    }

    protected function encodePayload() {
        $this->putInt($this->type);
        $this->putString($this->requestid);
        $this->putString($this->template);
        $this->putString(json_encode($this->servers));
    }
}