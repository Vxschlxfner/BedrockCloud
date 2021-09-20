<?php

namespace pocketmine\cloud\network\protocol;

class ConsoleTextPacket extends Packet{
    public const NETWORK_ID = Packet::PACKET_LOG;
	/** @var string */
    public $sender = "";
	/** @var string */
    public $message = "";


	/**
	 * Function pid
	 * @return int
	 */
    public function pid() {
        return self::NETWORK_ID;
    }

	/**
	 * Function decodePayload
	 * @return void
	 */
    protected function decodePayload() {
        $this->sender = $this->getString();
        $this->message = $this->getString();
    }

	/**
	 * Function encodePayload
	 * @return void
	 */
    protected function encodePayload() {
		$this->putString($this->sender);
		$this->putString($this->message);
    }
}
