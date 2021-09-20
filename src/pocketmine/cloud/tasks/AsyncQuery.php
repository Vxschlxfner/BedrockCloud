<?php
namespace pocketmine\cloud\tasks;
use pocketmine\cloud\Cloud;
use pocketmine\cloud\CloudServer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class AsyncQuery extends AsyncTask{
	protected $template;
	/** @var int */
	protected $port;
	/** @var string */
	protected $id;

	/**
	 * AsyncQuery constructor.
	 * @param int $port
	 * @param string $id
	 * @param string $template
	 */
	public function __construct(int $port, string $id, string $template){
		$this->template = $template;
		$this->port = $port;
		$this->id = $id;
	}

	/**
	 * Function onRun
	 * @return void
	 */
	public function onRun(){
		$data = Cloud::query("127.0.0.1", $this->port);
		$this->setResult($data);
	}

	/**
	 * Function onCompletion
	 * @param Server $server
	 * @return void
	 */
	public function onCompletion(Server $server): void{
        $cloud = $server->getCloud();
        $template = $cloud->getTemplateByName($this->template);
        $serv = $template->getServerByID($this->id);

        //CloudServer
        if ($serv instanceof CloudServer) {
            $result = $this->getResult();
            if ($result && !is_null($result["num"]) && ($serv->created + 5) < time()) {
                $serv->setPlayerCount($result["num"]);
            } else {
                if (($serv->created + 20) < time()) {
                    $server->getLogger()->info("Server " . $serv->getID() . " stopped.");
                    $serv->stopServer();
                }
            }
        }
	}
}
