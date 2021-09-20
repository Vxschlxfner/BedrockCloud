<?php

namespace pocketmine\cloud;

use pocketmine\cloud\tasks\AsyncQuery;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\MainLogger;

class Template{
	/** @var Cloud */
	private $cloud;
	/** @var string */
	private $name;
	/** @var int */
	private $minServiceCount;
	/** @var int */
	private $maxPlayersPercent;
	/** @var int */
	public $maxPlayerCount;
	/** @var int */
	public $minPlayersPercent;

	public function __construct(Cloud $cloud, string $name, int $minServiceCount, int $maxPlayersPercent, int $minPlayersPercent){
		$this->cloud = $cloud;
		$this->name = $name;
		$this->minServiceCount = $minServiceCount;
		$this->maxPlayersPercent = $maxPlayersPercent;
		$this->minPlayersPercent = $minPlayersPercent;
		$cfg = new Config($this->getPath() . "server.properties");
		$this->maxPlayerCount = $cfg->get("max-players");
	}

	/**
	 * @return string
	 */
	public function getName(): string{
		return $this->name;
	}

	public function getPath(): string{
		return $this->cloud->getTemplateFolder() . $this->name . "/";
	}

	/**
	 * @return int
	 */
	public function getMinServiceCount(): int{
		return $this->minServiceCount;
	}

	/**
	 * @return int
	 */
	public function getMaxPlayersPercent(): int{
		return $this->maxPlayersPercent;
	}


	//SERVER MANAGEMENT

	/** @var CloudServer[] */
	public $servers = [];

	/**
	 * @return CloudServer[]
	 */
	public function getServers(){
		return $this->servers;
	}

	public function getServerByID(string $id){
		if (isset($this->servers[$id])) {
			return $this->servers[$id];
		}
		return false;
	}

	public function unregisterServer(CloudServer $server): void{
		unset($this->servers[$server->getID()]);
	}

	/**
	 * Function getAvailableID
	 * @return int
	 */
	public function getAvailableID(): int{
		$current = 1;
		foreach ($this->servers as $server) {
			if ($server->id == $current) {
				$current++;
			}
		}
		return $current;
	}

	/**
	 * Function existsServer
	 * @param string $dir
	 * @return bool
	 */
	public function existsServer(string $dir){
		if (!is_dir($dir)) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Function createNewServer
	 * @return CloudServer
	 */
	public function createNewServer(): CloudServer
    {
        $start = microtime(true);
        $id = $this->getAvailableID();
        $port = $this->cloud->getAvabilePort();

        $server = new CloudServer($this->cloud, $this, $id, "Cloud", $port);
        $serverpath1 = $server->getFolder();
        if (is_dir($serverpath1) === true) {
            $this->unregisterServer($server);
        } else {
            $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cFolder don't exists§7!");
        }
        $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aCreating server§e " . $server->getID());
        $serverpath = $server->getFolder();
        if (!is_dir($serverpath)) {
            @mkdir($serverpath);
        }
        passthru("cp -r " . $this->getPath() . ". " . $serverpath);
        passthru("cp -r " . $this->cloud->getPluginFolder() . ". " . $serverpath . "/plugins/");
        passthru("cp -r " . $this->cloud->getPluginDataFolder() . ". " . $serverpath . "/plugin_data/");
        passthru("cp -r " . $this->getPath() . ". " . $serverpath);
        $properties = $server->getProperties();
        $properties->set("xbox-auth", false);
        $properties->set("server-group", $this->getName());
        $properties->set("server-name", $server->getID());
        $properties->set("server-id", $server->getID());
        $properties->set("server-uuid", $server->getUuid());
        $properties->set("server-port", $port);
        $properties->set("cloud-ip", $this->cloud->getServer()->getIp());
        $properties->set("cloud-port", $this->cloud->getServer()->getPort());
        $properties->set("motd", $server->getID());
        $properties->set("cloud-password", (new Config("{$this->cloud->getServer()->getDataPath()}options.yml"))->get("password"));
        $properties->set("private", false);
        $properties->save();
        $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§aCreation took§e " . (microtime(true) - $start));
        $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§e" . $server->getID() . "§a is running on Port§e " . $port);
        $this->servers[$server->getID()] = $server;
        return $server;
    }

	public function stopAllServers(): void{
		foreach ($this->servers as $server) {
			$server->stopServer();
			$this->unregisterServer($server);
			$server->deleteServer();
		}
	}

	public function queryServers(): void{
		foreach ($this->servers as $server) {
			$this->cloud->getServer()->getAsyncPool()->submitTask(new AsyncQuery($server->getPort(), $server->getID(), $this->getName()));
		}
	}

	public function checkMinServiceCount() {
		$current = count($this->servers);
		if ($current < $this->getMinServiceCount()) {
			for ($i=$current; $i < $this->getMinServiceCount(); $i++) {
				$server = $this->createNewServer();
				if (!is_null($server)) {
					$server->startServer();
				}
			}
		}
	}

	public function getMaxPlayers(): int {
		return count($this->servers) * $this->maxPlayerCount;
	}

	public function getOnlinePlayers(): int {
		$i = 0;
		foreach ($this->servers as $server) {
			if ($server->getPlayerCount() !== null && ($server->created + 20) < time()) {
				$i += $server->getPlayerCount();
			}
		}
		return $i;
	}

	public function checkMaxPlayers(): void {
		$max = $this->getMaxPlayers();
		$online = $this->getOnlinePlayers();

		if ($max > 0){
			if((100 / $max * $online) >= $this->maxPlayersPercent){
				$server = $this->createNewServer();
				if (!is_null($server)) {
					$server->startServer();
				}
			}
		}
	}

	public function checkMinPlayers(): void {
		$max = $this->getMaxPlayers();
		$online = $this->getOnlinePlayers();

		if ($max > 0) {
			if((100 / $max * $online) < $this->minPlayersPercent){
				$this->stopEmptyServers();
			}
		}
	}

	public function stopEmptyServers():void{
		foreach ($this->servers as $server){
			if(count($this->servers) <= $this->minServiceCount){
				return;
			}
			if($server->isServerEmpty()){
				$server->stopServer();
			}
		}
	}
}