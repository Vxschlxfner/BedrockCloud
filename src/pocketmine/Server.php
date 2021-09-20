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

/**
 * PocketMine-MP is the Minecraft: PE multiplayer server software
 * Homepage: http://www.pocketmine.net/
 */

namespace pocketmine;

use pocketmine\cloud\Cloud;
use pocketmine\command\CommandReader;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\SimpleCommandMap;
use pocketmine\event\HandlerList;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\plugin\PharPluginLoader;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginManager;
use pocketmine\plugin\ScriptPluginLoader;
use pocketmine\scheduler\AsyncPool;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\Config;
use pocketmine\utils\Internet;
use pocketmine\utils\MainLogger;
use pocketmine\utils\Terminal;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\utils\UUID;
use function array_filter;
use function array_key_exists;
use function array_shift;
use function array_sum;
use function asort;
use function assert;
use function base64_encode;
use function class_exists;
use function count;
use function define;
use function explode;
use function extension_loaded;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function function_exists;
use function get_class;
use function getmypid;
use function getopt;
use function gettype;
use function implode;
use function ini_get;
use function ini_set;
use function is_array;
use function is_bool;
use function is_dir;
use function is_object;
use function is_string;
use function is_subclass_of;
use function json_decode;
use function max;
use function microtime;
use function min;
use function mkdir;
use function ob_end_flush;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function preg_replace;
use function random_bytes;
use function random_int;
use function realpath;
use function register_shutdown_function;
use function rename;
use function round;
use function scandir;
use function sleep;
use function spl_object_hash;
use function sprintf;
use function str_repeat;
use function str_replace;
use function stripos;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function time;
use function touch;
use function trim;
use const DIRECTORY_SEPARATOR;
use const INT32_MAX;
use const INT32_MIN;
use const PHP_EOL;
use const PHP_INT_MAX;
use const PTHREADS_INHERIT_NONE;
use const SCANDIR_SORT_NONE;
use const SIGHUP;
use const SIGINT;
use const SIGTERM;

/**
 * The class that manages everything
 */
class Server {

    /** @var Server */
    private static $instance = null;

    /** @var \Threaded */
    private static $sleeper = null;

    /** @var SleeperHandler */
    private $tickSleeper;

    /** @var bool */
    private $isRunning = true;

    /** @var bool */
    private $hasStopped = false;

    /** @var PluginManager */
    private $pluginManager = null;

    /** @var float */
    private $profilingTickRate = 20;

    /** @var AsyncPool */
    private $asyncPool;

    /**
     * Counts the ticks since the server start
     *
     * @var int
     */
    private $tickCounter = 0;
    /** @var int */
    private $nextTick = 0;
    /** @var float[] */
    private $tickAverage = [20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20];
    /** @var float[] */
    private $useAverage = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    /** @var float */
    private $currentTPS = 20;
    /** @var float */
    private $currentUse = 0;

    /** @var bool */
    private $doTitleTick = true;

    /** @var int */
    private $sendUsageTicker = 0;

    /** @var bool */
    private $dispatchSignals = false;

    /** @var \AttachableThreadedLogger */
    private $logger;

    /** @var CommandReader */
    private $console = null;

    /** @var SimpleCommandMap */
    private $commandMap = null;

    /** @var \ClassLoader */
    private $autoloader;
    /** @var string */
    private $dataPath;
    /** @var string */
    private $pluginPath;

    /** @var Config */
    private $properties;

    /** @var Cloud */
    private $cloud;

    /**
     * @return string
     */
    public function getName(): string {
        return \pocketmine\NAME;
    }

    /**
     * @return Cloud
     */
    public function getCloud(): Cloud {
        return $this->cloud;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool {
        return $this->isRunning;
    }

    /**
     * @return string
     */
    public function getPocketMineVersion(): string {
        return \pocketmine\VERSION;
    }

    /**
     * @return string
     */
    public function getApiVersion(): string {
        return \pocketmine\BASE_VERSION;
    }

    /**
     * @return string
     */
    public function getFilePath(): string {
        return \pocketmine\PATH;
    }

    /**
     * @return string
     */
    public function getResourcePath(): string {
        return \pocketmine\RESOURCE_PATH;
    }

    /**
     * @return string
     */
    public function getDataPath(): string {
        return $this->dataPath;
    }

    /**
     * @return string
     */
    public function getPluginPath(): string {
        return $this->pluginPath;
    }

    /**
     * @return int
     */
    public function getPort(): int {
        return (int)$this->properties->get("listener-port");
    }

    /**
     * @return string
     */
    public function getIp(): string {
        return (string)$this->properties->get("listener-ip");
    }

    /**
     * @return \ClassLoader
     */
    public function getLoader() {
        return $this->autoloader;
    }

    /**
     * @return \AttachableThreadedLogger
     */
    public function getLogger() {
        return $this->logger;
    }

    public function getAsyncPool(): AsyncPool {
        return $this->asyncPool;
    }

    /**
     * Returns the last server TPS average measure
     *
     * @return float
     */
    public function getTicksPerSecondAverage(): float {
        return round(array_sum($this->tickAverage) / count($this->tickAverage), 2);
    }

    /**
     * Returns the TPS usage/load average in %
     *
     * @return float
     */
    public function getTickUsageAverage(): float {
        return round((array_sum($this->useAverage) / count($this->useAverage)) * 100, 2);
    }

    /**
     * @return SimpleCommandMap
     */
    public function getCommandMap() {
        return $this->commandMap;
    }

    /**
     * @return Server
     */
    public static function getInstance(): Server {
        if (self::$instance === null) {
            throw new \RuntimeException("Attempt to retrieve Server instance outside server thread");
        }
        return self::$instance;
    }

    public static function microSleep(int $microseconds) {
        Server::$sleeper->synchronized(function (int $ms) {
            Server::$sleeper->wait($ms);
        }, $microseconds);
    }

    /**
     * @param \ClassLoader $autoloader
     * @param \AttachableThreadedLogger $logger
     * @param string $dataPath
     * @param string $pluginPath
     */
    public function __construct(\ClassLoader $autoloader, \AttachableThreadedLogger $logger, string $dataPath, string $pluginPath) {
        if (self::$instance !== null) {
            throw new \InvalidStateException("Only one server instance can exist at once");
        }
        self::$instance = $this;
        self::$sleeper = new \Threaded;
        $this->tickSleeper = new SleeperHandler();
        $this->autoloader = $autoloader;
        $this->logger = $logger;

        try {
            if (!file_exists($dataPath . "templates/")) {
                mkdir($dataPath . "templates/", 0777);
            }

            if (!file_exists($dataPath . "servers/")) {
                mkdir($dataPath . "servers/", 0777);
            }

            if (!file_exists($pluginPath)) {
                mkdir($pluginPath, 0777);
            }

            $this->dataPath = realpath($dataPath) . DIRECTORY_SEPARATOR;
            $this->pluginPath = realpath($pluginPath) . DIRECTORY_SEPARATOR;

            $this->logger->info("Loading options...");
            $this->properties = new Config($this->dataPath . "options.yml", Config::YAML);

            define('pocketmine\DEBUG', 1);

            if (((int)ini_get('zend.assertions')) !== -1) {
                $this->logger->warning("Debugging assertions are enabled, this may impact on performance. To disable them, set `zend.assertions = -1` in php.ini.");
            }

            ini_set('assert.exception', '1');

            if ($this->logger instanceof MainLogger) {
                $this->logger->setLogDebug(\pocketmine\DEBUG > 1);
            }

            $this->logger->info("§aCloud is starting§8...");

            $poolSize = 2;
            $processors = Utils::getCoreCount() - 2;

            if ($processors > 0) {
                $poolSize = max(1, $processors);
            }

            $this->asyncPool = new AsyncPool($this, $poolSize, -1, $this->autoloader, $this->logger);

            $this->doTitleTick = true;

            $consoleSender = new ConsoleCommandSender();

            $consoleNotifier = new SleeperNotifier();
            $this->console = new CommandReader($consoleNotifier);
            $this->tickSleeper->addNotifier($consoleNotifier, function () use ($consoleSender) : void {
                while (($line = $this->console->getLine()) !== null) {
                    $ev = new ServerCommandEvent($consoleSender, $line);
                    $ev->call();
                    if (!$ev->isCancelled()) {
                        $this->dispatchCommand($ev->getSender(), $ev->getCommand());
                    }
                }
            });
            $this->console->start(PTHREADS_INHERIT_NONE);

            if (\pocketmine\DEBUG >= 0) {
                @cli_set_process_title($this->getName() . " " . $this->getPocketMineVersion());
            }

            $this->logger->info("Starting listener on " . $this->getIp() . ":" . $this->getPort());

            $this->commandMap = new SimpleCommandMap($this);

            #$this->pluginManager = new PluginManager($this, $this->commandMap, $this->getDataPath() . "plugin_data" . DIRECTORY_SEPARATOR);
            $this->profilingTickRate = 20;

            register_shutdown_function([$this, "crashDump"]);

            $this->cloud = new Cloud($this);

            #$this->pluginManager->loadPlugins($this->pluginPath);

            #$this->enablePlugins();

            $this->start();
        } catch (\Throwable $e) {
            $this->exceptionHandler($e);
        }
    }

    /**
     * Executes a command from a CommandSender
     *
     * @param CommandSender $sender
     * @param string $commandLine
     * @param bool $internal
     *
     * @return bool
     */
    public function dispatchCommand(CommandSender $sender, string $commandLine, bool $internal = false): bool {
        if (!$internal) {
            $ev = new CommandEvent($sender, $commandLine);
            $ev->call();
            if ($ev->isCancelled()) {
                return false;
            }

            $commandLine = $ev->getCommand();
        }

        if ($this->commandMap->dispatch($sender, $commandLine)) {
            return true;
        }


        $sender->sendMessage("Command not found!");

        return false;
    }

    /**
     * Shuts the server down correctly
     */
    public function shutdown() {
        $this->isRunning = false;
    }

    public function forceShutdown() {
        if ($this->hasStopped) {
            return;
        }

        if ($this->doTitleTick) {
            echo "\x1b]0;\x07";
        }

        try {
            $this->hasStopped = true;

            $this->shutdown();
            $this->cloud->onCloudStop();
            if ($this->pluginManager instanceof PluginManager) {
                $this->getLogger()->debug("Disabling all plugins");
                $this->pluginManager->disablePlugins();
            }
            $this->getLogger()->debug("Removing event handlers");
            HandlerList::unregisterAll();

            if ($this->asyncPool instanceof AsyncPool) {
                $this->getLogger()->debug("Shutting down async task worker pool");
                $this->asyncPool->shutdown();
            }

            if ($this->properties !== null and $this->properties->hasChanged()) {
                $this->getLogger()->debug("Saving properties");
                $this->properties->save();
            }

            if ($this->console instanceof CommandReader) {
                $this->getLogger()->debug("Closing console");
                $this->console->shutdown();
                $this->console->notify();
            }
        } catch (\Throwable $e) {
            $this->logger->logException($e);
            $this->logger->emergency("Crashed while crashing, killing process");
            @Utils::kill(getmypid());
        }

    }

    /**
     * Starts the PocketMine-MP server and starts processing ticks and packets
     */
    private function start() {
        $this->tickCounter = 0;

        if (function_exists("pcntl_signal")) {
            pcntl_signal(SIGTERM, [$this, "handleSignal"]);
            pcntl_signal(SIGINT, [$this, "handleSignal"]);
            pcntl_signal(SIGHUP, [$this, "handleSignal"]);
            $this->dispatchSignals = true;
        }

        $this->logger->info("§aCloud started after§e ".round(microtime(true) - \pocketmine\START_TIME, 3));

        $this->tickProcessor();
        $this->forceShutdown();
    }

    public function handleSignal($signo) {
        if ($signo === SIGTERM or $signo === SIGINT or $signo === SIGHUP) {
            $this->shutdown();
        }
    }

    /**
     * @param \Throwable $e
     * @param array|null $trace
     */
    public function exceptionHandler(\Throwable $e, $trace = null) {
        while (@ob_end_flush()) {
        }
        global $lastError;

        if ($trace === null) {
            $trace = $e->getTrace();
        }

        $errstr = $e->getMessage();
        $errfile = $e->getFile();
        $errline = $e->getLine();

        $errstr = preg_replace('/\s+/', ' ', trim($errstr));

        $errfile = Utils::cleanPath($errfile);

        $this->logger->logException($e, $trace);

        $lastError = [
            "type" => get_class($e),
            "message" => $errstr,
            "fullFile" => $e->getFile(),
            "file" => $errfile,
            "line" => $errline,
            "trace" => $trace
        ];

        global $lastExceptionError, $lastError;
        $lastExceptionError = $lastError;
        $this->crashDump();
    }

    public function crashDump() {
        while (@ob_end_flush()) {
        }
        if (!$this->isRunning) {
            return;
        }
        $this->hasStopped = false;

        $this->forceShutdown();
        $this->isRunning = false;

        //Force minimum uptime to be >= 120 seconds, to reduce the impact of spammy crash loops
        $spacing = ((int)\pocketmine\START_TIME) - time() + 0;
        if ($spacing > 0) {
            echo "--- Waiting $spacing seconds to throttle automatic restart (you can kill the process safely now) ---" . PHP_EOL;
            sleep($spacing);
        }
        @Utils::kill(getmypid());
        exit(1);
    }

    public function __debugInfo() {
        return [];
    }

    public function getTickSleeper(): SleeperHandler {
        return $this->tickSleeper;
    }

    private function tickProcessor() {
        $this->nextTick = microtime(true);

        while ($this->isRunning) {
            $this->tick();

            //sleeps are self-correcting - if we undersleep 1ms on this tick, we'll sleep an extra ms on the next tick
            $this->tickSleeper->sleepUntil($this->nextTick);
        }
    }

    private function titleTick() {
        $d = Utils::getRealMemoryUsage();

        $u = Utils::getMemoryUsage(true);
        $usage = sprintf("%g/%g/%g/%g MB @ %d threads", round(($u[0] / 1024) / 1024, 2), round(($d[0] / 1024) / 1024, 2), round(($u[1] / 1024) / 1024, 2), round(($u[2] / 1024) / 1024, 2), Utils::getThreadCount());

        echo "\x1b]0;" . $this->getName() . " " .
            $this->getPocketMineVersion() .
            " | Memory " . $usage .
            " | TPS " . $this->getTicksPerSecondAverage() .
            " | Load " . $this->getTickUsageAverage() . "%\x07";
    }


    /**
     * Tries to execute a server tick
     */
    private function tick(): void {
        $tickTime = microtime(true);
        if (($tickTime - $this->nextTick) < -0.025) { //Allow half a tick of diff
            return;
        }
        ++$this->tickCounter;
        $this->cloud->getScheduler()->mainThreadHeartbeat($this->tickCounter);
        $this->asyncPool->collectTasks();

        if (($this->tickCounter % 20) === 0) {
            if ($this->doTitleTick) {
                $this->titleTick();
            }
            $this->currentTPS = 20;
            $this->currentUse = 0;
        }

        if ($this->dispatchSignals and $this->tickCounter % 5 === 0) {
            pcntl_signal_dispatch();
        }

        $now = microtime(true);
        $this->currentTPS = min(20, 1 / max(0.001, $now - $tickTime));
        $this->currentUse = min(1, ($now - $tickTime) / 0.05);

        $idx = $this->tickCounter % 20;
        $this->tickAverage[$idx] = $this->currentTPS;
        $this->useAverage[$idx] = $this->currentUse;

        if (($this->nextTick - $tickTime) < -1) {
            $this->nextTick = $tickTime;
        } else {
            $this->nextTick += 0.05;
        }
    }

    /**
     * Called when something attempts to serialize the server instance.
     *
     * @throws \BadMethodCallException because Server instances cannot be serialized
     */
    public function __sleep() {
        throw new \BadMethodCallException("Cannot serialize Server instance");
    }
}
