<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\plugin;

use pocketmine\command\PluginCommand;
use pocketmine\command\SimpleCommandMap;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerList;
use pocketmine\event\Listener;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\Server;
use pocketmine\utils\Utils;
use function array_intersect;
use function array_map;
use function array_pad;
use function class_exists;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function get_class;
use function gettype;
use function implode;
use function is_a;
use function is_array;
use function is_bool;
use function is_dir;
use function is_string;
use function is_subclass_of;
use function iterator_to_array;
use function mkdir;
use function shuffle;
use function stripos;
use function strpos;
use function strtolower;
use function strtoupper;
use const DIRECTORY_SEPARATOR;

/**
 * Manages all the plugins
 */
class PluginManager{

	/** @var Server */
	private $server;

	/** @var SimpleCommandMap */
	private $commandMap;

	/**
	 * @var Plugin[]
	 */
	protected $plugins = [];

	/**
	 * @var Plugin[]
	 */
	protected $enabledPlugins = [];

	/**
	 * @var PluginLoader[]
	 */
	protected $fileAssociations = [];

	/** @var string|null */
	private $pluginDataDirectory;

	/**
	 * @param Server           $server
	 * @param SimpleCommandMap $commandMap
	 * @param null|string      $pluginDataDirectory
	 */
	public function __construct(Server $server, SimpleCommandMap $commandMap, ?string $pluginDataDirectory){
		$this->server = $server;
		$this->commandMap = $commandMap;
		$this->pluginDataDirectory = $pluginDataDirectory;
		if($this->pluginDataDirectory !== null){
			if(!file_exists($this->pluginDataDirectory)){
				@mkdir($this->pluginDataDirectory, 0777, true);
			}elseif(!is_dir($this->pluginDataDirectory)){
				throw new \RuntimeException("Plugin data path $this->pluginDataDirectory exists and is not a directory");
			}
		}
	}

	/**
	 * @param string $name
	 *
	 * @return null|Plugin
	 */
	public function getPlugin(string $name){
		if(isset($this->plugins[$name])){
			return $this->plugins[$name];
		}

		return null;
	}

	/**
	 * @param PluginLoader $loader
	 */
	public function registerInterface(PluginLoader $loader) : void{
		$this->fileAssociations[get_class($loader)] = $loader;
	}

	/**
	 * @return Plugin[]
	 */
	public function getPlugins() : array{
		return $this->plugins;
	}

	private function getDataDirectory(string $pluginPath, string $pluginName) : string{
		if($this->pluginDataDirectory !== null){
			return $this->pluginDataDirectory . $pluginName;
		}
		return dirname($pluginPath) . DIRECTORY_SEPARATOR . $pluginName;
	}

	/**
	 * @param string         $path
	 * @param PluginLoader[] $loaders
	 *
	 * @return Plugin|null
	 */
	public function loadPlugin(string $path, array $loaders = null) : ?Plugin{
		foreach($loaders ?? $this->fileAssociations as $loader){
			if($loader->canLoadPlugin($path)){
				$description = $loader->getPluginDescription($path);
				if($description instanceof PluginDescription){
					$this->server->getLogger()->info("Loading plugin ". $description->getFullName());
					try{
						$description->checkRequiredExtensions();
					}catch(PluginException $ex){
						$this->server->getLogger()->error($ex->getMessage());
						return null;
					}

					$dataFolder = $this->getDataDirectory($path, $description->getName());
					if(file_exists($dataFolder) and !is_dir($dataFolder)){
						$this->server->getLogger()->error("Projected dataFolder '" . $dataFolder . "' for " . $description->getName() . " exists and is not a directory");
						return null;
					}
					if(!file_exists($dataFolder)){
						mkdir($dataFolder, 0777, true);
					}

					$prefixed = $loader->getAccessProtocol() . $path;
					$loader->loadPlugin($prefixed);

					$mainClass = $description->getMain();
					if(!class_exists($mainClass, true)){
						$this->server->getLogger()->error("Main class for plugin " . $description->getName() . " not found");
						return null;
					}
					if(!is_a($mainClass, Plugin::class, true)){
						$this->server->getLogger()->error("Main class for plugin " . $description->getName() . " is not an instance of " . Plugin::class);
						return null;
					}

					try{
						/**
						 * @var Plugin $plugin
						 * @see Plugin::__construct()
						 */
						$plugin = new $mainClass($loader, $this->server, $description, $dataFolder, $prefixed);
						$plugin->onLoad();
						$this->plugins[$plugin->getDescription()->getName()] = $plugin;

						$pluginCommands = $this->parseYamlCommands($plugin);

						if(count($pluginCommands) > 0){
							$this->commandMap->registerAll($plugin->getDescription()->getName(), $pluginCommands);
						}

						return $plugin;
					}catch(\Throwable $e){
						$this->server->getLogger()->logException($e);
						return null;
					}
				}
			}
		}

		return null;
	}

	/**
	 * @param string $directory
	 * @param array  $newLoaders
	 *
	 * @return Plugin[]
	 */
	public function loadPlugins(string $directory, array $newLoaders = null){
		if(!is_dir($directory)){
			return [];
		}

		$plugins = [];
		$loadedPlugins = [];
		$dependencies = [];
		$softDependencies = [];
		if(is_array($newLoaders)){
			$loaders = [];
			foreach($newLoaders as $key){
				if(isset($this->fileAssociations[$key])){
					$loaders[$key] = $this->fileAssociations[$key];
				}
			}
		}else{
			$loaders = $this->fileAssociations;
		}

		$files = iterator_to_array(new \FilesystemIterator($directory, \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS));
		shuffle($files); //this prevents plugins implicitly relying on the filesystem name order when they should be using dependency properties
		foreach($loaders as $loader){
			foreach($files as $file){
				if(!$loader->canLoadPlugin($file)){
					continue;
				}
				try{
					$description = $loader->getPluginDescription($file);
					if($description === null){
						continue;
					}

					$name = $description->getName();
					if(strpos($name, " ") !== false){
						$this->server->getLogger()->warning("Plugin names should not contain spaces!");
					}

					if(isset($plugins[$name]) or $this->getPlugin($name) instanceof Plugin){
						$this->server->getLogger()->error("Duplicate plugin: ".$name);
						continue;
					}

					$plugins[$name] = $file;

					$softDependencies[$name] = $description->getSoftDepend();
					$dependencies[$name] = $description->getDepend();

					foreach($description->getLoadBefore() as $before){
						if(isset($softDependencies[$before])){
							$softDependencies[$before][] = $name;
						}else{
							$softDependencies[$before] = [$name];
						}
					}
				}catch(\Throwable $e){
					$this->server->getLogger()->error($file. $directory. $e->getMessage());
					$this->server->getLogger()->logException($e);
				}
			}
		}


		while(count($plugins) > 0){
			$missingDependency = true;
			foreach($plugins as $name => $file){
				if(isset($dependencies[$name])){
					foreach($dependencies[$name] as $key => $dependency){
						if(isset($loadedPlugins[$dependency]) or $this->getPlugin($dependency) instanceof Plugin){
							unset($dependencies[$name][$key]);
						}elseif(!isset($plugins[$dependency])){
							$this->server->getLogger()->critical("Plugin ".$name." unknown dependency ".$dependency);
							unset($plugins[$name]);
							continue 2;
						}
					}

					if(count($dependencies[$name]) === 0){
						unset($dependencies[$name]);
					}
				}

				if(isset($softDependencies[$name])){
					foreach($softDependencies[$name] as $key => $dependency){
						if(isset($loadedPlugins[$dependency]) or $this->getPlugin($dependency) instanceof Plugin){
							unset($softDependencies[$name][$key]);
						}
					}

					if(count($softDependencies[$name]) === 0){
						unset($softDependencies[$name]);
					}
				}

				if(!isset($dependencies[$name]) and !isset($softDependencies[$name])){
					unset($plugins[$name]);
					$missingDependency = false;
					if($plugin = $this->loadPlugin($file, $loaders) and $plugin instanceof Plugin){
						$loadedPlugins[$name] = $plugin;
					}else{
						$this->server->getLogger()->critical("Could not load plugin ".$name);
					}
				}
			}

			if($missingDependency){
				foreach($plugins as $name => $file){
					if(!isset($dependencies[$name])){
						unset($softDependencies[$name]);
						unset($plugins[$name]);
						$missingDependency = false;
						if($plugin = $this->loadPlugin($file, $loaders) and $plugin instanceof Plugin){
							$loadedPlugins[$name] = $plugin;
						}else{
                            $this->server->getLogger()->critical("Could not load plugin ".$name);
						}
					}
				}

				//No plugins loaded :(
				if($missingDependency){
					foreach($plugins as $name => $file){
                        $this->server->getLogger()->critical("Circular dependency ".$name);
					}
					$plugins = [];
				}
			}
		}

		return $loadedPlugins;
	}

	/**
	 * Returns whether a specified API version string is considered compatible with the server's API version.
	 *
	 * @param string ...$versions
	 *
	 * @return bool
	 */
	public function isCompatibleApi(string ...$versions) : bool{
		return true;
	}

	/**
	 * @param Plugin $plugin
	 *
	 * @return bool
	 */
	public function isPluginEnabled(Plugin $plugin) : bool{
		return isset($this->plugins[$plugin->getDescription()->getName()]) and $plugin->isEnabled();
	}

	/**
	 * @param Plugin $plugin
	 */
	public function enablePlugin(Plugin $plugin){
		if(!$plugin->isEnabled()){
			try{
				$this->server->getLogger()->info("Enabling plugin ".$plugin->getDescription()->getFullName());

				$plugin->getScheduler()->setEnabled(true);
				$plugin->setEnabled(true);

				$this->enabledPlugins[$plugin->getDescription()->getName()] = $plugin;

				(new PluginEnableEvent($plugin))->call();
			}catch(\Throwable $e){
				$this->server->getLogger()->logException($e);
				$this->disablePlugin($plugin);
			}
		}
	}

	/**
	 * @param Plugin $plugin
	 *
	 * @return PluginCommand[]
	 */
	protected function parseYamlCommands(Plugin $plugin) : array{
		$pluginCmds = [];

		foreach($plugin->getDescription()->getCommands() as $key => $data){
			if(strpos($key, ":") !== false){
				$this->server->getLogger()->critical("Plugin command error: ".$key. $plugin->getDescription()->getFullName());
				continue;
			}
			if(is_array($data)){
				$newCmd = new PluginCommand($key, $plugin);
				if(isset($data["description"])){
					$newCmd->setDescription($data["description"]);
				}

				if(isset($data["usage"])){
					$newCmd->setUsage($data["usage"]);
				}

				if(isset($data["aliases"]) and is_array($data["aliases"])){
					$aliasList = [];
					foreach($data["aliases"] as $alias){
						if(strpos($alias, ":") !== false){
							$this->server->getLogger()->critical("Plugin alias error: ". $alias. $plugin->getDescription()->getFullName());
							continue;
						}
						$aliasList[] = $alias;
					}

					$newCmd->setAliases($aliasList);
				}
				$pluginCmds[] = $newCmd;
			}
		}

		return $pluginCmds;
	}

	public function disablePlugins(){
		foreach($this->getPlugins() as $plugin){
			$this->disablePlugin($plugin);
		}
	}

	/**
	 * @param Plugin $plugin
	 */
	public function disablePlugin(Plugin $plugin){
		if($plugin->isEnabled()){
			$this->server->getLogger()->info("Disabling plugin ".$plugin->getDescription()->getFullName());
			(new PluginDisableEvent($plugin))->call();

			unset($this->enabledPlugins[$plugin->getDescription()->getName()]);

			try{
				$plugin->setEnabled(false);
			}catch(\Throwable $e){
				$this->server->getLogger()->logException($e);
			}
			$plugin->getScheduler()->shutdown();
			HandlerList::unregisterAll($plugin);
		}
	}

	public function tickSchedulers(int $currentTick) : void{
		foreach($this->enabledPlugins as $p){
			$p->getScheduler()->mainThreadHeartbeat($currentTick);
		}
	}

	public function clearPlugins(){
		$this->disablePlugins();
		$this->plugins = [];
		$this->enabledPlugins = [];
		$this->fileAssociations = [];
	}

	/**
	 * Calls an event
	 *
	 * @deprecated
	 * @see Event::call()
	 *
	 * @param Event $event
	 */
	public function callEvent(Event $event){
		$event->call();
	}

	/**
	 * Registers all the events in the given Listener class
	 *
	 * @param Listener $listener
	 * @param Plugin   $plugin
	 *
	 * @throws PluginException
	 */
	public function registerEvents(Listener $listener, Plugin $plugin) : void{
		if(!$plugin->isEnabled()){
			throw new PluginException("Plugin attempted to register " . get_class($listener) . " while not enabled");
		}

		$reflection = new \ReflectionClass(get_class($listener));
		foreach($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method){
			if(!$method->isStatic() and $method->getDeclaringClass()->implementsInterface(Listener::class)){
				$tags = Utils::parseDocComment((string) $method->getDocComment());
				if(isset($tags["notHandler"])){
					continue;
				}

				$parameters = $method->getParameters();
				if(count($parameters) !== 1){
					continue;
				}

				$handlerClosure = $method->getClosure($listener);

				try{
					$eventClass = $parameters[0]->getClass();
				}catch(\ReflectionException $e){ //class doesn't exist
					if(isset($tags["softDepend"]) && !isset($this->plugins[$tags["softDepend"]])){
						$this->server->getLogger()->debug("Not registering @softDepend listener " . Utils::getNiceClosureName($handlerClosure) . "(" . $parameters[0]->getType()->getName() . ") because plugin \"" . $tags["softDepend"] . "\" not found");
						continue;
					}

					throw $e;
				}
				if($eventClass === null or !$eventClass->isSubclassOf(Event::class)){
					continue;
				}

				try{
					$priority = isset($tags["priority"]) ? EventPriority::fromString($tags["priority"]) : EventPriority::NORMAL;
				}catch(\InvalidArgumentException $e){
					throw new PluginException("Event handler " . Utils::getNiceClosureName($handlerClosure) . "() declares invalid/unknown priority \"" . $tags["priority"] . "\"");
				}

				$ignoreCancelled = false;
				if(isset($tags["ignoreCancelled"])){
					switch(strtolower($tags["ignoreCancelled"])){
						case "true":
						case "":
							$ignoreCancelled = true;
							break;
						case "false":
							$ignoreCancelled = false;
							break;
						default:
							throw new PluginException("Event handler " . Utils::getNiceClosureName($handlerClosure) . "() declares invalid @ignoreCancelled value \"" . $tags["ignoreCancelled"] . "\"");
					}
				}

				$this->registerEvent($eventClass->getName(), $listener, $priority, new MethodEventExecutor($method->getName()), $plugin, $ignoreCancelled);
			}
		}
	}

	/**
	 * @param string        $event Class name that extends Event
	 * @param Listener      $listener
	 * @param int           $priority
	 * @param EventExecutor $executor
	 * @param Plugin        $plugin
	 * @param bool          $ignoreCancelled
	 *
	 * @throws PluginException
	 */
	public function registerEvent(string $event, Listener $listener, int $priority, EventExecutor $executor, Plugin $plugin, bool $ignoreCancelled = false) : void{
		if(!is_subclass_of($event, Event::class)){
			throw new PluginException($event . " is not an Event");
		}


		if(!$plugin->isEnabled()){
			throw new PluginException("Plugin attempted to register " . $event . " while not enabled");
		}
		$this->getEventListeners($event)->register(new RegisteredListener($listener, $executor, $priority, $plugin, $ignoreCancelled));
	}

	/**
	 * @param string $event
	 *
	 * @return HandlerList
	 */
	private function getEventListeners(string $event) : HandlerList{
		$list = HandlerList::getHandlerListFor($event);
		if($list === null){
			throw new PluginException("Abstract events not declaring @allowHandle cannot be handled (tried to register listener for $event)");
		}
		return $list;
	}
}
