<?php
namespace pocketmine\cloud\network\protocol;

class RequestPacket extends Packet{
	/** @var int */
	public $type;
	/** @var string */
	public $requestid;
}