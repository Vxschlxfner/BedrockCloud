<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;

class SaveCommand extends VanillaCommand {
    public $cloud;

    public function __construct(Cloud $cloud, string $name) {
        $this->cloud = $cloud;
        parent::__construct($name, "Start new servers by template", "/save <servername>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(isset($args[0])){
            if ($this->cloud->getServerByID($args[0])){
                $this->cloud->getServerByID($args[0])->copyTemplate();
            }else{
                $sender->sendMessage($this->getUsage());
            }
        }else{
            $sender->sendMessage($this->getUsage());
        }
    }
}
