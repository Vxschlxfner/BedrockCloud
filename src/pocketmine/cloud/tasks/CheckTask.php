<?php

namespace pocketmine\cloud\tasks;

use pocketmine\cloud\Cloud;
use pocketmine\scheduler\Task;

class CheckTask extends Task {
    private $cloud;

    public function __construct(Cloud $cloud) {
        $this->cloud = $cloud;
    }

    public function onRun(int $currentTick) {
        $this->cloud->checkQueries();
        $this->cloud->checkMinServices();
        $this->cloud->checkMaxPlayerCounts();
        $this->cloud->checkMinPlayerCounts();
    }
}