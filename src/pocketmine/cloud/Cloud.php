<?php

namespace pocketmine\cloud;

use pocketmine\cloud\commands\CreateCommand;
use pocketmine\cloud\commands\EndCommand;
use pocketmine\cloud\commands\HelpCommand;
use pocketmine\cloud\commands\ListCommand;
use pocketmine\cloud\commands\SaveCommand;
use pocketmine\cloud\commands\StartCommand;
use pocketmine\cloud\commands\StopCommand;
use pocketmine\cloud\network\BaseHost;
use pocketmine\cloud\network\protocol\Packet;
use pocketmine\cloud\tasks\CheckTask;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\utils\Config;
use raklib\utils\InternetAddress;

class Cloud {
    private $server;
    /** @var Template[] */
    private $templates = [];
    /** @var Config */
    private $config;
    /** @var TaskScheduler */
    private $scheduler;
    private $uuid;
    /** @var BaseHost */
    private $host;
    private $cloud;
    /** @var Config */
    public $group_file;

    /**
     * @return Server
     */
    public function getServer(): Server {
        return $this->server;
    }

    /**
     * @return TaskScheduler
     */
    public function getScheduler(): TaskScheduler {
        return $this->scheduler;
    }

    public function __construct(Server $server) {
        @mkdir("/home/mcpe/CloudDatabase/");

        $this->group_file = "/home/mcpe/CloudDatabase/server_groups.json";
        $this->server = $server;
        $this->config = new Config($server->getDataPath() . "templates.yml");
        $this->scheduler = new TaskScheduler($server->getLogger(), "Cloud");
		$this->socket  = new BaseHost($this, new InternetAddress($server->getIp(), $server->getPort(), 4), (new Config(Server::getInstance()->getDataPath() . "options.yml", Config::YAML))->get("password"));

        if (file_exists($server->getDataPath()."server.log")) {
            unlink($server->getDataPath() . "server.log");
        }

        $this->cloudInit();
        $this->clearServersFolder();

        $this->registerCommands();
        $this->loadTemplates();

        $this->startTasks();
    }

    public function cloudInit() {
        @mkdir($this->getServer()->getDataPath()."server_crashdumps/");
        $this->getServer()->getLogger()->info(Options::PREFIX . "§aInit Cloud§7!");
        popen("rm -r /home/mcpe/CloudDatabase/temp/", "r");
        @mkdir("/home/mcpe/CloudDatabase/temp/");
        $cfg = new Config($this->group_file, Config::JSON);
        $cfg->setAll([]);
        $cfg->save();

        $this->getServer()->getLogger()->info(Options::PREFIX . "§aFinished§7!");
    }

    public function clearServersFolder(): void {
        passthru("rm -r " . $this->getServerFolder() . "*");
    }

    public function checkMinServices(): void {
        foreach ($this->templates as $template) {
            $template->checkMinServiceCount();
        }
    }

    public function checkQueries():void{
        foreach ($this->templates as $template){
            $template->queryServers();
        }
    }

    public function checkMaxPlayerCounts(): void {
        foreach ($this->templates as $template) {
            $template->checkMaxPlayers();
        }
    }

    public function checkMinPlayerCounts(): void {
        foreach ($this->templates as $template) {
            $template->checkMinPlayers();
        }
    }

    private function registerCommands(): void {
        $this->getServer()->getCommandMap()->registerAll("cloud", [
            new CreateCommand($this, "create"),
            new EndCommand($this, "end"),
            new ListCommand($this, "list"),
            new StartCommand($this, "start"),
            new StopCommand($this, "stop"),
            new SaveCommand($this, "save"),
			new HelpCommand($this, "help")
        ]);
    }

    public function loadTemplates(): void
    {
        $this->getServer()->getLogger()->info(Options::PREFIX . "§aLoading templates...");

        if (count($this->getRegisteredTemplates()) <= 0) {
            Server::getInstance()->getLogger()->info("§cNo templates found.");
            return;
        }

        foreach ($this->getRegisteredTemplates() as $name => $templateData) {
            if ($templateData["enabled"]) {
                $minServiceCount = $templateData["minServiceCount"];
                $maxPlayersPercent = $templateData["maxPlayersPercent"];
                $minPlayersPercent = $templateData["minPlayersPercent"];
                $this->templates[$name] = new Template($this, $name, $minServiceCount, $maxPlayersPercent, $minPlayersPercent);
            }
        }
        $this->getServer()->getLogger()->info(Options::PREFIX . "§e" . count($this->templates) . " §atemplates loaded.");
    }

    public function startTasks(): void {
        $scheduler = $this->getScheduler();
        $scheduler->setEnabled(true);
        $scheduler->scheduleRepeatingTask(new CheckTask($this), 20);
    }

    /**
     * @return Template[]
     */
    public function getTemplates(): array {
        return $this->templates;
    }

    /**
     * @param string $name
     * @return bool|Template
     */
    public function getTemplateByName(string $name) {
        if (isset($this->templates[$name])) {
            return $this->templates[$name];
        }
        return false;
    }

    public function isTemplate(string $name): bool {
        return $this->config->exists($name);
    }

    public function getRegisteredTemplates(): array {
        return $this->config->getAll();
    }

    public function getTemplateFolder(): string {
        return $this->getServer()->getDataPath() . "templates/";
    }

    public function initNewTemplate(string $name): void {
        if (!is_dir($this->getTemplateFolder() . $name)) {
            mkdir($this->getTemplateFolder() . $name, 0777);
        }

        $this->config->set($name, [
            "enabled" => false,
            "minServiceCount" => 1,
            "maxPlayersPercent" => 69,
            "minPlayersPercent" => 20
        ]);
        $this->config->save();
    }

	public static function getAvabilePort(): int
	{
		while(true) {
			$port = rand(40000, 65535);
			$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			$state = socket_connect($socket, "127.0.0.1", $port);
			if ($state) {
				socket_close($socket);
				break;
			}
		}
		return $port;
	}

    /**
     * @param string $id
     * @return bool|CloudServer
     */
    public function getServerByID(string $id) {
        foreach ($this->templates as $template) {
            foreach ($template->servers as $server) {
                if($server->getID() == $id){
                    return $server;
                }
            }
        }
        return false;
    }

    public function onCloudStop(): void {
        $this->getServer()->getLogger()->info(Options::PREFIX . "§cStopping all services.");
        $this->stopAll();
        $this->unregisterAllServer();
		$this->socket->getSocket()->close();
        Server::getInstance()->shutdown();
        popen("killall -9 php", "r");
    }

    public function stopAll(): void {
        foreach ($this->templates as $template) {
            $template->stopAllServers();
        }
    }

    public function unregisterAllServer(){
		foreach ($this->templates as $template) {
			foreach ($template->getServers() as $server){
				$template->unregisterServer($server);
			}
		}
	}
    
    public function getPluginFolder(): string {
        return $this->getServer()->getDataPath() . "plugins/";
    }

    public function getPluginDataFolder(): string {
        return $this->getServer()->getDataPath() . "plugin_data/";
    }

    public function getServerFolder(): string {
        return $this->getServer()->getDataPath() . "servers/";
    }

    public static function query(string $host, int $port, int $timeout=10) {

        $socket = @fsockopen("udp://" . $host, $port, $errno, $errstr, $timeout);

        if ($errno || $socket === false) {
            return false;
        }

        stream_set_timeout($socket, $timeout);
        stream_set_Blocking($socket, true);
        $randInt = mt_rand(1, 999999999);
        $reqPacket = "\x01";
        $reqPacket .= pack('Q*', $randInt);
        $reqPacket .= "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";
        $reqPacket .= pack('Q*', 0);
        fwrite($socket, $reqPacket, strlen($reqPacket));
        $response = fread($socket, 4096);
        fclose($socket);

        if (empty($response)) {
            return false;
        }
        if (substr($response, 0, 1) !== "\x1C") {
            return false;
        }
        $serverInfo = substr($response, 35);
        $serverInfo = preg_replace("#ยง.#", "", $serverInfo);
        $serverInfo = explode(';', $serverInfo);
        return [
            'motd'     => $serverInfo[1] ?? null,
            'version'  => $serverInfo[3] ?? null,
            'num'      => $serverInfo[4] ?? null,
            'max'      => $serverInfo[5] ?? null,
            'id' 	   => "",
            'platform' => "PE"
        ];
    }
}