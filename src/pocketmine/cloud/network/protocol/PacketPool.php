<?php

declare(strict_types=1);

namespace pocketmine\cloud\network\protocol;

use pocketmine\utils\Binary;

class PacketPool {
    /** @var \SplFixedArray<DataPacket> */
    protected static $pool = null;

    public static function init() {
        static::$pool = new \SplFixedArray(256);
        static::registerPacket(new LoginPacket());
    }

    public static function registerPacket(DataPacket $packet) {
        static::$pool[$packet->pid()] = clone $packet;
    }

    public static function getPacketById(int $pid): ?DataPacket {
        return isset(static::$pool[$pid]) ? clone static::$pool[$pid] : null;
    }

    public static function getPacket(string $buffer): ?DataPacket {
        $offset = 0;
        $pk = static::getPacketById(Binary::readUnsignedVarInt($buffer, $offset) & DataPacket::PID_MASK);
        if (!is_null($pk)) {
            $pk->setBuffer($buffer, $offset);
            return $pk;
        }
        return null;
    }
}
