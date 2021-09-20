<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;

class StartCommand extends VanillaCommand {
    public $cloud;

    public function __construct(Cloud $cloud, string $name) {
        $this->cloud = $cloud;
        parent::__construct($name, "Start new servers by template", "/start <template> <count>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(isset($args[0])){
            if($this->cloud->isTemplate($args[0])){
                $template = $this->cloud->getTemplateByName($args[0]);
                if(isset($args[1])){
                    if(is_numeric($args[1])){
                        $count = intval($args[1]);
                    }else{
                        $count = 1;
                    }
                }else{
                    $count = 1;
                }
                for ($i = 0; $i < $count; $i++) {
					$server = $template->createNewServer();
					$server->startServer();
				}
            }else{
                $sender->sendMessage("Template not found!");
            }
        }else{
            $sender->sendMessage($this->getUsage());
        }
    }
}