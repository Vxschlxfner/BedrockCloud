<?php

namespace pocketmine\cloud\network\protocol;

class DisconnectPacket extends RequestPacket{
	public const NETWORK_ID = self::PACKET_DISCONNECT;

	/** @var int */
	public $reason = 0;
	/** @var string */
	public $requestId = "";

	public const REASON_UNKNOWN         = 0;
	public const REASON_SERVER_SHUTDOWN = 1;
	public const REASON_WRONG_PASSWORD  = 2;
	public const REASON_CLOUD_SHUTDOWN  = 3;



	/**
	 * Function decodePayload
	 * @return void
	 */
	protected function decodePayload(): void{
		$this->requestId = $this->getString();
		$this->reason = $this->getInt();
	}

	/**
	 * Function encodePayload
	 * @return void
	 */
	protected function encodePayload(): void{
		$this->putString($this->requestId);
		$this->putInt($this->reason);
	}
}