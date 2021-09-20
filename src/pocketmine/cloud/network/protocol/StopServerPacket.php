<?php

namespace pocketmine\cloud\network\protocol;

class StopServerPacket extends RequestPacket{
	public const NETWORK_ID = self::PACKET_STOP_SERVER;

	/** @var string */
	public $server = "";
	/** @var string */
	public $requestId = "";



	/**
	 * Function decodePayload
	 * @return void
	 */
	protected function decodePayload(): void{
		$this->type = $this->getInt();
		$this->requestId = $this->getString();
		$this->server = $this->getString();
	}

	/**
	 * Function encodePayload
	 * @return void
	 */
	protected function encodePayload(): void{
		$this->putInt($this->type);
		$this->putString($this->requestId);
		$this->putString($this->server);
	}
}
