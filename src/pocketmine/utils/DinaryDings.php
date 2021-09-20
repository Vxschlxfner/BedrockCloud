<?php

namespace pocketmine\utils;

class DinaryDings extends BinaryStream{

	/**
	 * Reads a 32-bit variable-length unsigned integer from the buffer and returns it.
	 * @return int
	 */
	public function getUnsignedVarInt() : int{
		return Binary::readUnsignedVarInt($this->buffer, $this->offset);
	}

	/**
	 * Writes a 32-bit variable-length unsigned integer to the end of the buffer.
	 * @param int $v
	 */
	public function putUnsignedVarInt(int $v){
		($this->buffer .= Binary::writeUnsignedVarInt($v));
	}
}
