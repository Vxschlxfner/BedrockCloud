<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

/**
 * Command handling related classes
 */
namespace pocketmine\command;

use pocketmine\command\utils\CommandException;
use function explode;
use function str_replace;

abstract class Command{

	/** @var string */
	private $name;

	/** @var string */
	private $nextLabel;

	/** @var string */
	private $label;

	/** @var string[] */
	private $aliases = [];

	/**
	 * @var string[]
	 */
	private $activeAliases = [];

	/** @var CommandMap */
	private $commandMap = null;

	/** @var string */
	protected $description = "";

	/** @var string */
	protected $usageMessage;

	/**
	 * @param string   $name
	 * @param string   $description
	 * @param string   $usageMessage
	 * @param string[] $aliases
	 */
	public function __construct(string $name, string $description = "", string $usageMessage = null, array $aliases = []){
		$this->name = $name;
		$this->setLabel($name);
		$this->setDescription($description);
		$this->usageMessage = $usageMessage ?? ("/" . $name);
		$this->setAliases($aliases);
	}

	/**
	 * @param CommandSender $sender
	 * @param string        $commandLabel
	 * @param string[]      $args
	 *
	 * @return mixed
	 * @throws CommandException
	 */
	abstract public function execute(CommandSender $sender, string $commandLabel, array $args);

	/**
	 * @return string
	 */
	public function getName() : string{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getLabel() : string{
		return $this->label;
	}

	public function setLabel(string $name) : bool{
		$this->nextLabel = $name;
		if(!$this->isRegistered()){
			$this->label = $name;

			return true;
		}

		return false;
	}

	/**
	 * Registers the command into a Command map
	 *
	 * @param CommandMap $commandMap
	 *
	 * @return bool
	 */
	public function register(CommandMap $commandMap) : bool{
		if($this->allowChangesFrom($commandMap)){
			$this->commandMap = $commandMap;

			return true;
		}

		return false;
	}

	/**
	 * @param CommandMap $commandMap
	 *
	 * @return bool
	 */
	public function unregister(CommandMap $commandMap) : bool{
		if($this->allowChangesFrom($commandMap)){
			$this->commandMap = null;
			$this->activeAliases = $this->aliases;
			$this->label = $this->nextLabel;

			return true;
		}

		return false;
	}

	/**
	 * @param CommandMap $commandMap
	 *
	 * @return bool
	 */
	private function allowChangesFrom(CommandMap $commandMap) : bool{
		return $this->commandMap === null or $this->commandMap === $commandMap;
	}

	/**
	 * @return bool
	 */
	public function isRegistered() : bool{
		return $this->commandMap !== null;
	}

	/**
	 * @return string[]
	 */
	public function getAliases() : array{
		return $this->activeAliases;
	}

	/**
	 * @return string
	 */
	public function getDescription() : string{
		return $this->description;
	}

	/**
	 * @return string
	 */
	public function getUsage() : string{
		return $this->usageMessage;
	}

	/**
	 * @param string[] $aliases
	 */
	public function setAliases(array $aliases){
		$this->aliases = $aliases;
		if(!$this->isRegistered()){
			$this->activeAliases = $aliases;
		}
	}

	/**
	 * @param string $description
	 */
	public function setDescription(string $description){
		$this->description = $description;
	}

	/**
	 * @param string $usage
	 */
	public function setUsage(string $usage){
		$this->usageMessage = $usage;
	}

	/**
	 * @return string
	 */
	public function __toString() : string{
		return $this->name;
	}
}
