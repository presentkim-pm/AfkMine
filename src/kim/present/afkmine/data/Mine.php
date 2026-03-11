<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\afkmine\data;

use kim\present\afkmine\entity\MinerEntity;
use kim\present\afkmine\Main;
use kim\present\afkmine\utils\ArrayUtils;
use kim\present\cameraapi\Camera;
use kim\present\cameraapi\camera\builder\CameraFadeBuilder;
use kim\present\cameraapi\camera\builder\CameraSetBuilder;
use kim\present\cameraapi\camera\builder\CameraTargetBuilder;
use kim\present\cameraapi\camera\preset\CameraPresetRegistry;
use kim\present\cameraapi\timeline\CameraTimeline;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEaseType as EaseType;
use pocketmine\Server;

class Mine{

    public MineData $data;
    private int $tick = 0;
    private ?MinerEntity $miner = null;
    private CameraTimeline $loopCameraTimeline;
    private CameraTimeline $initCameraTimeline;

    /** @var array<string, Item[]> */
    private array $pendingDropsByPlayer = [];

    private ?string $currentPlayerName = null;

    /** @var array<Vector3> */
    private array $miningQueue = [];

    /** @var OreSpotEntry[] */
    private array $regenQueue = [];

    public function __construct(MineData $data){
        $this->data = $data;
    }

    /**
     * Places a random ore block at every ore spot in the world.
     * Called once when a mine is first created to populate the area.
     */
    public function fillAllOres() : void{
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->data->worldName);
        if($world === null){
            return;
        }

        foreach($this->data->oreSpots as $entry){
            $world->setBlock($entry->vec, $entry->preset->getRandomOre());
        }
    }

    public function tick() : void{
        $this->tick++;

        // Spawn/Check Miner
        if($this->miner === null || $this->miner->isClosed() || !$this->miner->isAlive()){
            $this->spawnMinerEntity();
        }

        // Ore Regen logic (Basic implementation)
        if($this->tick % $this->data->regenInterval === 0){
            $this->regenOres();
        }
    }

    public function getNextMiningVector() : Vector3{
        if($this->miningQueue === []){
            $this->miningQueue = array_values($this->data->getOreSpotsFlat());

            $world = Server::getInstance()->getWorldManager()->getWorldByName($this->data->worldName);
            if($world !== null){
                $filteredMiningQueue = array_filter(
                    $this->miningQueue,
                    fn(Vector3 $vec) => OreStyle::isOre($world->getBlock($vec))
                );
                if($filteredMiningQueue !== []){
                    $this->miningQueue = $filteredMiningQueue;
                }
            }
            ArrayUtils::fisherYatesShuffle($this->miningQueue);
        }

        $next = array_pop($this->miningQueue);
        if($next instanceof Vector3){
            return $next;
        }

        return $this->data->spawnPos ?? new Vector3(0, 0, 0);
    }

    private function getNextRegenEntry() : ?OreSpotEntry{
        if($this->regenQueue === []){
            $world = Server::getInstance()->getWorldManager()->getWorldByName($this->data->worldName);
            if($world === null){
                return null;
            }

            $this->regenQueue = $this->data->getRegenCandidates($world);
            if($this->regenQueue === []){
                return null;
            }

            ArrayUtils::fisherYatesShuffle($this->regenQueue);
        }

        return array_pop($this->regenQueue);
    }

    private function spawnMinerEntity() : void{
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->data->worldName);
        if($world === null){
            return;
        }

        $pos = $this->data->spawnPos;
        if($pos === null){
            return;
        }
        if(!$world->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4)){
            $world->loadChunk($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
        }

        $location = new Location($pos->x, $pos->y, $pos->z, $world, 0, 0);
        $miner = new MinerEntity($location);
        $miner->setMineData($this->data);
        $miner->setMine($this);
        $miner->spawnToAll();
        $this->miner = $miner;
    }

    public function occupy(string $playerName) : bool{
        if($this->currentPlayerName !== null && $this->currentPlayerName !== $playerName){
            return false;
        }
        $this->currentPlayerName = $playerName;
        return true;
    }

    public function release(string $playerName) : void{
        if($this->currentPlayerName === $playerName){
            $this->currentPlayerName = null;
            unset($this->pendingDropsByPlayer[$playerName]);
        }
    }

    public function getCurrentPlayerName() : ?string{
        return $this->currentPlayerName;
    }

    /**
     * @param string $playerName
     * @param Item[] $items
     */
    public function addPendingDropsFor(string $playerName, array $items) : void{
        foreach($items as $item){
            if($item->isNull()){
                continue;
            }
            $this->pendingDropsByPlayer[$playerName][] = clone $item;
        }
    }

    /**
     * @param string $playerName
     * @param int    $limit 0 or less means no limit
     *
     * @return Item[]
     */
    public function takePendingDropsFor(string $playerName, int $limit = 0) : array{
        if(!isset($this->pendingDropsByPlayer[$playerName]) || $this->pendingDropsByPlayer[$playerName] === []){
            return [];
        }

        $queue = $this->pendingDropsByPlayer[$playerName];
        $count = count($queue);

        if($limit <= 0 || $limit >= $count){
            unset($this->pendingDropsByPlayer[$playerName]);
            return $queue;
        }

        $drops = array_slice($queue, 0, $limit);
        $this->pendingDropsByPlayer[$playerName] = array_slice($queue, $limit);

        return $drops;
    }

    private function regenOres() : void{
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->data->worldName);
        if($world === null){
            return;
        }

        $entry = $this->getNextRegenEntry();
        if($entry === null){
            return;
        }

        $world->setBlock($entry->vec, $entry->preset->getRandomOre());
    }

    public function getMiner() : ?MinerEntity{
        return $this->miner;
    }

    public function getInitCameraTimeline() : CameraTimeline{
        return $this->initCameraTimeline ??=
            Camera::timeline()
                  ->set(fn(CameraSetBuilder $b) => $b
                      ->preset(CameraPresetRegistry::PRESET_FREE)
                      ->position($this->data->cameraPoints[0])
                  )
                  ->target(fn(CameraTargetBuilder $b) => $b->entity($this->getMiner()))
                  ->fade(fn(CameraFadeBuilder $b) => $b
                      ->color(DyeColor::BLACK())
                      ->in(0.1)
                      ->stay(1.0)
                      ->out(0.5)
                  );
    }

    public function getLoopCameraTimeline() : CameraTimeline{
        if(isset($this->loopCameraTimeline)){
            return $this->loopCameraTimeline;
        }

        $points = $this->data->cameraPoints !== [] ? array_values($this->data->cameraPoints) : [];

        $timeline = Camera::timeline();

        // Build ping-pong sequence of indices: 0..n-1..1
        $count = count($points);
        if($count === 0){
            $timeline->setLoop();
            return $this->loopCameraTimeline = $timeline;
        }

        $indices = range(0, $count - 1);
        if($count >= 2){
            $back = range($count - 2, 1);
            $indices = array_merge($indices, $back);
        }

        $config = Main::getInstance()->getPluginConfig();
        $holdDuration = $config->cameraHoldSeconds;
        $maxOffsetRatio = $config->cameraOffsetMax;

        foreach($indices as $index){
            $timeline
                ->wait($holdDuration)
                ->fade(fn(CameraFadeBuilder $b) => $b
                    ->color(DyeColor::BLACK())
                    ->in(0.25)
                    ->stay(0.5)
                    ->out(0.25)
                )
                ->wait(0.5)
                ->set(fn(CameraSetBuilder $b) => $b
                    ->preset(CameraPresetRegistry::PRESET_FREE)
                    ->position((function() use ($points, $index, $maxOffsetRatio){
                        $base = $points[$index];

                        $miner = $this->getMiner();
                        if($miner === null){
                            return $base;
                        }

                        $minerVec = $miner->getLocation()->asVector3();

                        // 0.0 ~ $maxOffsetRatio ratio toward miner position
                        $max = (int) round($maxOffsetRatio * 100);
                        $factor = mt_rand(0, $max) / 100;
                        $offset = $minerVec->subtractVector($base)->multiply($factor);

                        return $base->addVector($offset);
                    })())
                );
        }

        // Loop the entire path until the AFK session stops the camera.
        $timeline->setLoop();

        return $this->loopCameraTimeline = $timeline;
    }
}
