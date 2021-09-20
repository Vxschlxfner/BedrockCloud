<?php

namespace pocketmine\cloud;

use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\MainLogger;
use pocketmine\utils\UUID;

class CloudServer {

    private $cloud;

    /** @var Template */
    private $template;
    /** @var int */
    public $id;
    /** @var string */
    public $owner;
    /** @var UUID */
    protected $uuid = null;
    /** @var int */
    private $port;
    /** @var int */
    public $created;
    /** @var null | Config */
    private static $group_config = null;

    public function __construct(Cloud $cloud, Template $template, int $id, String $owner, int $port) {
        $this->cloud = $cloud;
        $this->template = $template;
        $this->id = $id;
        $this->owner = $owner;
        $this->port = $port;
        $this->created = time();
        $this->uuid = UUID::fromRandom();
    }

    /**
     * @return int
     */
    public function getPort(): int {
        return $this->port;
    }

	/**
	 * Function getUuid
	 * @return UUID
	 */
	public function getUuid(): UUID{
		return $this->uuid;
	}
    /**
     * @return Template
     */
    public function getTemplate(): Template {
        return $this->template;
    }

    public function getID() {
        return $this->template->getName()."-".$this->id;
    }

    public function getFolder():string {
        return $this->cloud->getServerFolder().$this->getID()."/";
    }

	public function getCrashdumps():string {
    	if (is_dir($this->cloud->getServerFolder() . $this->getID() . "/crashdumps/")) {
			return $this->cloud->getServerFolder() . $this->getID() . "/crashdumps/";
		} else {
    		return false;
		}
	}

    public function getProperties():Config{
        return new Config($this->getFolder() . "server.properties", Config::PROPERTIES);
    }

    public static function getGroupConfig()
    {
        if (self::$group_config != null) return self::$group_config;
        self::$group_config = new Config('/home/mcpe/CloudDatabase/server_groups.json', Config::JSON);
        return self::$group_config;
    }

	public function registerServer(String $serverName, String $ip, int $port){
		$this->passProxyCommand("registerserver " . $serverName . " " . $ip . " " . $port); //Only if a Screen named Proxy exists
	}

	public function unregisterServer(String $serverName){
		$this->passProxyCommand("unregisterserver " . $serverName); //Only if a Screen named Proxy exists
	}

    /**
     * @return array
     */
    public static function getGroups()
    {
        return array_keys(self::getGroupConfig()->getAll());
    }

    public static function getGroupServers(string $group)
    {
        return self::getGroupConfig()->get($group);
    }

    /**
     * @param string $group
     * @return bool
     */
    public static function isGroup(string $group)
    {
        return self::getGroupConfig()->exists($group);
    }

    /**
     * @param string $group
     */
    public static function addGroup(string $group)
    {
        self::getGroupConfig()->set($group, []);
        self::getGroupConfig()->save();
    }

    /**
     * @param string $group
     */
    public static function removeGroup(string $group)
    {
        if (!self::isGroup($group)) return;
        self::getGroupConfig()->remove($group);
        self::getGroupConfig()->save();
    }

    /**
     * @param string $server
     * @param string $group
     * @return bool
     */
    public static function isServerInGroup(string $group, string $server)
    {
        if (!self::isGroup($group)) return false;
        return in_array($server, self::getGroupServers($group));
    }

    /**
     * @param string $server
     * @param string $group
     */
    public static function addServerToGroup(string $group, string $server)
    {
        if (!self::isGroup($group)) return;
        if (self::isServerInGroup($group, $server)) return;
        self::getGroupConfig()->set($group, array_merge(self::getGroupServers($group), [$server]));
        self::getGroupConfig()->save();
        self::getGroupConfig()->reload();
    }

    /**
     * @param string $server
     * @param string $group
     */
    public static function removeServerFromGroup(string $group, string $server)
    {
        if (!self::isGroup($group)) return;
        if (!self::isServerInGroup($group, $server)) return;
        $array = self::getGroupServers($group);
        unset($array[array_search($server, $array)]);
        self::getGroupConfig()->set($group, $array);
        self::getGroupConfig()->save();
        self::getGroupConfig()->reload();
    }

    public function saveCrashdumps()
	{
		if ($this->getCrashdumps() != false) {
			if (!empty($this->getCrashdumps())) {
				popen("cp -r " . $this->getCrashdumps() . "* {$this->cloud->getServer()->getDataPath()}/" . $this->getID(), "r");
			}
		}
	}

    public function startServer():void
    {
        $server = $this->getID();
        $ip = "127.0.0.1";
        $port = (int)$this->getProperties()->get("server-port");

        $this->killScreen();
        if (is_dir($this->getFolder())) {
            //AddGroup
            if (!self::isGroup($this->template->getName())) {
                self::addGroup($this->template->getName());
                $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aGroup added to Cloud-config§7!");
            }

            //RemoveServer
            if (self::isServerInGroup($this->template->getName(), $this->getID())) {
                self::removeServerFromGroup($this->template->getName(), $this->getID());
                $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aRemoved Server from Cloud-config§7!");
            }

            //AddServer
            if (!self::isServerInGroup($this->template->getName(), $this->getID())) {
                self::addServerToGroup($this->template->getName(), $this->getID());
                $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aAdded Server to Cloud-config§7!");
            }

            $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aStarting server§e " . $this->getID());
            popen("chmod 0777 " . $this->getFolder() . "start.sh", "r");
            popen("tmux new-session -d -s " . $this->getID() . " '" . $this->getFolder() . "start.sh'", "r");
            $this->unregisterServer($server);
            $this->registerServer($server, $ip, $port);
            sleep(0.5);
        } else {
            MainLogger::getLogger()->info(Options::PREFIX . "§cError whilst starting server§7!\n§cServer§7-§cFolder don't exists§7!");
        }
    }

    public function stopServer():void
    {
        $server = $this->getID();
        $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cStopping server§e " . $this->getID());

        $this->passCommand("shutdown");

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $state = socket_connect($socket, "127.0.0.1", $this->getPort() + 1);
        if ($state) {
            socket_close($socket);
        }

        $this->unregisterServer($server);

        //RemoveServer
        if (self::isServerInGroup($this->template->getName(), $this->getID())) {
            self::removeServerFromGroup($this->template->getName(), $this->getID());
            $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aRemoved Server from Database§7!");
        }
        $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cDeleting server§e " . $this->getID());
        $this->deleteServer();
        $this->template->unregisterServer($this);

        $this->killScreen();
    }

    public function copyTemplate():void
    {

        $this->passCommand("save-all");
        $path = Server::getInstance()->getDataPath();
        $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aSave server§e " . $this->getID());
        if (is_dir("{$path}templates/") && is_dir($this->getFolder())) {
            passthru("rm -r {$path}templates/" . $this->template->getName() . "/worlds/");
            passthru("mkdir {$path}templates/" . $this->template->getName() . "/worlds/");
            passthru("cp -r " . $this->getFolder() . "worlds/* {$path}templates/" . $this->template->getName() . "/worlds/");
            passthru("rm {$path}templates/" . $this->template->getName() . "/ops.txt");
            passthru("rm {$path}templates/" . $this->template->getName() . "/server.log");
            passthru("rm -r {$path}templates/" . $this->template->getName() . "/players/");
            passthru("rm -r {$path}templates/" . $this->template->getName() . "/crashdumps/");
            $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aServer saved§8.");
        } else {
            $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cError whilst saving files§7!§c Folder don' exists§7!");
        }
    }

    public function passCommand(string $command):void
    {
		popen("tmux send -t " . $this->getID() . " " . $command . " ENTER", "r");
    }

    public function deleteServer():void
    {
		$this->saveCrashdumps();
		$this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cSaving crashdumps of Server§e " . $this->getID());
		$this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cDeleting server§e " . $this->getID());
		if (is_dir($this->getFolder())) {
			popen("rm -r " . $this->getFolder(), "r");
		}
		$this->template->unregisterServer($this);
    }

    public function passProxyCommand(string $command):void
    {
		popen("screen -S Proxy -X stuff '" . $command . "
        '", "r");
    }

    public function killScreen():void
    {
		popen("tmux kill-session -t " . $this->getID(), "r");
    }

    //Query stuff
    private $playerCount = null;

    /**
     * @return null
     */
    public function getPlayerCount() {
        return $this->playerCount;
    }

    public function isServerEmpty(): bool{
        return $this->playerCount <= 0;
    }

    /**
     * @param null $playerCount
     */
    public function setPlayerCount($playerCount): void {
        $this->playerCount = $playerCount;
    }

    public function isServerConnected(): bool{
		return (in_array($this->getID(), $this->cloud->socket->clients));
	}
}
