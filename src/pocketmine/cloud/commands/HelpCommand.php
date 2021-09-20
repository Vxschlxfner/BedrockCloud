<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\Options;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;

class HelpCommand extends VanillaCommand {
	public $cloud;

	public function __construct(Cloud $cloud, string $name) {
		$this->cloud = $cloud;
		parent::__construct($name, "Stop cloud", "/end");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		$sender->sendMessage(Options::PREFIX . "§cCommands§7: §r");
		$sender->sendMessage(Options::PREFIX . "§7- §e/start <template> <count>");
		$sender->sendMessage(Options::PREFIX . "§7- §e/list");
		$sender->sendMessage(Options::PREFIX . "§7- §e/end");
		$sender->sendMessage(Options::PREFIX . "§7- §e/stop <template/Servername>");
		$sender->sendMessage(Options::PREFIX . "§7- §e/create <template>");
		$sender->sendMessage(Options::PREFIX . "§7- §e/save <Servername>| §4(Unstable)");
	}
}
