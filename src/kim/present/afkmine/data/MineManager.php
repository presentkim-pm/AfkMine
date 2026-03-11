<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\afkmine\data;

use kim\present\afkmine\Main;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\Server;

class MineManager{

    private static ?self $instance = null;
    private Main $plugin;

    /** @var Mine[] */
    private array $mines = [];

    public function __construct(Main $plugin){
        self::$instance = $this;
        $this->plugin = $plugin;
        $this->loadMines();
    }

    public static function getInstance() : self{
        $instance = self::$instance;
        if($instance === null){
            throw new \LogicException('MineManager not initialized');
        }
        return $instance;
    }

    public function tick() : void{
        foreach($this->mines as $mine){
            $mine->tick();
        }
    }

    public function saveMine(MineData $data) : void{
        $mine = new Mine($data);
        $this->mines[$data->name] = $mine;
        $this->saveToDisk();
        $this->loadChunksForMine($data);
        $mine->fillAllOres();
    }

    /**
     * Returns all registered mines indexed by name.
     *
     * @return Mine[]
     */
    public function getMines() : array{
        return $this->mines;
    }

    /**
     * Deletes a mine by name and removes its miner entity if present.
     *
     * @param string $name
     *
     * @return bool True if a mine was removed.
     */
    public function deleteMine(string $name) : bool{
        if(!isset($this->mines[$name])){
            return false;
        }

        $mine = $this->mines[$name];

        // Restore original blocks for all ore spots before removing the mine.
        $data = $mine->data;
        if($data->originalBlockStates !== []){
            $world = Server::getInstance()->getWorldManager()->getWorldByName($data->worldName);
            if($world === null){
                Server::getInstance()->getWorldManager()->loadWorld($data->worldName);
                $world = Server::getInstance()->getWorldManager()->getWorldByName($data->worldName);
            }

            if($world !== null){
                $registry = RuntimeBlockStateRegistry::getInstance();
                foreach($data->oreSpots as $hash => $entry){
                    $stateId = $data->originalBlockStates[$hash] ?? null;
                    if($stateId === null || !$registry->hasStateId($stateId)){
                        continue;
                    }
                    $world->setBlock($entry->vec, $registry->fromStateId($stateId));
                }
            }
        }

        $miner = $mine->getMiner();
        if($miner !== null && !$miner->isClosed()){
            $miner->flagForDespawn();
        }

        unset($this->mines[$name]);
        $this->saveToDisk();

        return true;
    }

    private function loadMines() : void{
        if(!file_exists($this->plugin->getDataFolder() . "mines.json")){
            return;
        }
        $path = $this->plugin->getDataFolder() . "mines.json";
        $content = file_get_contents($path);
        if($content === false){
            return;
        }
        $data = json_decode($content, true);
        if(!is_array($data)){
            return;
        }

        foreach($data as $mineArr){
            if(!is_array($mineArr)){
                continue;
            }
            $mineData = MineData::fromArray($mineArr);
            if(!$mineData->isValid()){
                continue;
            }
            $this->mines[$mineData->name] = new Mine($mineData);
            $this->loadChunksForMine($mineData);
        }
    }

    private function saveToDisk() : void{
        $data = [];
        foreach($this->mines as $mine){
            $data[] = $mine->data->jsonSerialize();
        }
        file_put_contents($this->plugin->getDataFolder() . "mines.json", json_encode($data, JSON_PRETTY_PRINT));
    }

    private function loadChunksForMine(MineData $data) : void{
        $world = Server::getInstance()->getWorldManager()->getWorldByName($data->worldName);
        if($world === null){
            Server::getInstance()->getWorldManager()->loadWorld($data->worldName);
            $world = Server::getInstance()->getWorldManager()->getWorldByName($data->worldName);
        }

        if($world === null){
            return;
        }

        // Load chunks for all important spots
        $points = array_merge(
            $data->getOreSpotsFlat(),
            $data->cameraPoints,
            [$data->spawnPos, $data->hidePos]
        );

        foreach($points as $pos){
            if($pos === null){
                continue;
            }
            $cx = ((int) $pos->x) >> 4;
            $cz = ((int) $pos->z) >> 4;
            $world->loadChunk($cx, $cz);
            // In PMMP 5, keeping chunk loaded might require adding a chunk loader.
            // For now, we just ensure they are loaded at startup.
            // Ideally, we register a loader for the area.
        }
    }

    public function getMine(string $name) : ?Mine{
        return $this->mines[$name] ?? null;
    }

    public function getRandomMine() : ?Mine{
        if($this->mines === []){
            return null;
        }
        return $this->mines[array_rand($this->mines)];
    }

    public function getRandomAvailableMineFor(string $playerName) : ?Mine{
        if($this->mines === []){
            return null;
        }

        $candidates = [];
        foreach($this->mines as $mine){
            if(!$mine->data->isValid()){
                continue;
            }
            $owner = $mine->getCurrentPlayerName();
            if($owner === null || $owner === $playerName){
                $candidates[] = $mine;
            }
        }

        if($candidates === []){
            return null;
        }

        return $candidates[array_rand($candidates)];
    }
}
