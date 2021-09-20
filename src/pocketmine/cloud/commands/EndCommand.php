<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\Server;

class EndCommand extends VanillaCommand {
    public $cloud;

    public function __construct(Cloud $cloud, string $name) {
        $this->cloud = $cloud;
        parent::__construct($name, "Stop cloud", "/end");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        $this->cloud->onCloudStop();
    }
}