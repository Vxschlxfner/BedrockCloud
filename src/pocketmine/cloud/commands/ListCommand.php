<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\Options;
use pocketmine\cloud\Template;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;

class ListCommand extends VanillaCommand {
    public $cloud;

    public function __construct(Cloud $cloud, string $name) {
        $this->cloud = $cloud;
        parent::__construct($name, "List templates and servers", "/list");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        $templates = $this->cloud->getTemplates();
        if(count($templates) > 0){
            if(isset($args[0])){
                if($this->cloud->isTemplate($args[0])){
                    $sender->sendMessage($this->listByTemplate($this->cloud->getTemplateByName($args[0])));
                }else{
                    $sender->sendMessage(Options::PREFIX . "§4Template " . $args[0] . " not found. Is it enabled?");
                }
            }else{
                $sender->sendMessage(Options::PREFIX . "§aListing all enabled templates");
                foreach ($templates as $template){
                    $sender->sendMessage($this->listByTemplate($template));
                }
            }
        }else{
            $sender->sendMessage(Options::PREFIX . "§4No templates enabled!");
        }
    }

    public function listByTemplate(Template $template):string {
        $message = "\n§e".$template->getName()."\n";
        foreach ($template->getServers() as $server){
            if(is_null($server->getPlayerCount())){
                $players = "N/A";
            }else{
                $players = $server->getPlayerCount();
            }
            $message = $message.Options::PREFIX . "§a".$server->getID()." §8- §a".$players."§7/§4".$template->maxPlayerCount."\n";
        }
        return $message;
    }
}