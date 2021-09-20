<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\Options;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;

class CreateCommand extends VanillaCommand {
    public $cloud;

    public function __construct(Cloud $cloud, string $name) {
        $this->cloud = $cloud;
        parent::__construct($name, "Create a new template", "/create <name>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(count($args) == 1){
            $name = $args[0];
            if(!$this->cloud->isTemplate($name)){
                $this->cloud->initNewTemplate($name);
            }else{
                $sender->sendMessage(Options::PREFIX . "There is already a template with that name!");
            }
        }else{
            $sender->sendMessage($this->getUsage());
        }
    }
}