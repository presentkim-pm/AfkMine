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

use pocketmine\math\Vector3;
use pocketmine\world\World;

class MineData implements \JsonSerializable{

    public string $name;
    public string $worldName;

    /** @var array<int, OreSpotEntry> blockHash => OreSpotEntry */
    public array $oreSpots = [];

    /**
     * @var array<int, int> blockHash => original block state ID
     *
     * Stores the original block state at each ore spot position so that
     * the world can be restored when the mine is deleted.
     */
    public array $originalBlockStates = [];

    /** @var Vector3[] Camera path points in creation order */
    public array $cameraPoints = [];
    public ?Vector3 $spawnPos = null; // Dummy spawn
    public ?Vector3 $hidePos = null; // Player hide pos

    public int $regenInterval = 100; // Ticks

    public function __construct(string $name, string $worldName){
        $this->name = $name;
        $this->worldName = $worldName;
    }

    public function isValid() : bool{
        return $this->oreSpots !== []
            && $this->cameraPoints !== []
            && $this->spawnPos !== null
            && $this->hidePos !== null;
    }

    /** Returns the OrePreset for the given block position, or null if not an ore spot. */
    public function getPresetAt(int $x, int $y, int $z) : ?OrePreset{
        $hash = World::blockHash($x, $y, $z);
        return ($this->oreSpots[$hash] ?? null)?->preset;
    }

    /** @return Vector3[] */
    public function getOreSpotsFlat() : array{
        return array_map(fn(OreSpotEntry $entry) => $entry->vec, $this->oreSpots);
    }

    /**
     * @param World $world
     *
     * @return OreSpotEntry[]
     */
    public function getRegenCandidates(World $world) : array{
        return array_filter($this->oreSpots, fn($e) => $e->preset->isFillBlock($world->getBlock($e->vec)));
    }

    /** @return array<string, mixed> */
    public function jsonSerialize() : array{
        return [
            'name' => $this->name,
            'worldName' => $this->worldName,
            'oreSpots' => array_map(fn(OreSpotEntry $entry) => $entry->preset->getId(), $this->oreSpots),
            'originalBlockStates' => $this->originalBlockStates,
            'cameraPoints' => array_map($this->vecToArr(...), $this->cameraPoints),
            'spawnPos' => $this->spawnPos !== null ? $this->vecToArr($this->spawnPos) : null,
            'hidePos' => $this->hidePos !== null ? $this->vecToArr($this->hidePos) : null,
            'regenInterval' => $this->regenInterval
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data) : self{
        $mine = new self($data['name'], $data['worldName']);
        $registry = OrePresetRegistry::getInstance();

        if(isset($data['oreSpots']) && is_array($data['oreSpots'])){
            $firstValue = reset($data['oreSpots']);
            if($firstValue === false || is_string($firstValue)){
                // Current format: blockHash => presetId
                foreach($data['oreSpots'] as $blockHash => $presetId){
                    if(!is_string($presetId)){
                        continue;
                    }
                    $preset = $registry->get($presetId);
                    if($preset === null){
                        continue;
                    }
                    $hash = (int) $blockHash;
                    World::getBlockXYZ($hash, $x, $y, $z);
                    $mine->oreSpots[$hash] = new OreSpotEntry(new Vector3($x, $y, $z), $preset);
                }
            }else{
                // Legacy v1: [{x,y,z}, ...] + baseType
                $preset = $registry->get($data['baseType'] ?? '') ?? $registry->get(OrePresetRegistry::DEFAULT_STONE);
                if($preset !== null){
                    foreach($data['oreSpots'] as $vecArr){
                        if(!is_array($vecArr)){
                            continue;
                        }
                        $vec = self::arrToVec($vecArr);
                        $mine->oreSpots[World::blockHash((int) $vec->x, (int) $vec->y, (int) $vec->z)] =
                            new OreSpotEntry($vec, $preset);
                    }
                }
            }
        }elseif(isset($data['oreSpotsByType']) && is_array($data['oreSpotsByType'])){
            // Legacy v2: baseType => [{x,y,z}, ...]
            foreach($data['oreSpotsByType'] as $presetId => $arr){
                $preset = $registry->get($presetId);
                if($preset === null || !is_array($arr)){
                    continue;
                }
                foreach($arr as $vecArr){
                    if(!is_array($vecArr)){
                        continue;
                    }
                    $vec = self::arrToVec($vecArr);
                    $mine->oreSpots[World::blockHash((int) $vec->x, (int) $vec->y, (int) $vec->z)] =
                        new OreSpotEntry($vec, $preset);
                }
            }
        }else{
            // Legacy v0: coalSpots + diamondSpots
            $stonePreset = $registry->get(OrePresetRegistry::DEFAULT_STONE);
            if($stonePreset !== null){
                $coal = array_map([self::class, 'arrToVec'], $data['coalSpots'] ?? []);
                $diamond = array_map([self::class, 'arrToVec'], $data['diamondSpots'] ?? []);
                foreach(array_merge($coal, $diamond) as $vec){
                    $hash = World::blockHash((int) $vec->x, (int) $vec->y, (int) $vec->z);
                    $mine->oreSpots[$hash] = new OreSpotEntry($vec, $stonePreset);
                }
            }
        }

        if(isset($data['originalBlockStates']) && is_array($data['originalBlockStates'])){
            foreach($data['originalBlockStates'] as $hash => $stateId){
                if(!is_int($stateId)){
                    continue;
                }
                $mine->originalBlockStates[(int) $hash] = $stateId;
            }
        }

        $mine->cameraPoints = array_map([self::class, 'arrToVec'], $data['cameraPoints'] ?? []);
        $mine->spawnPos = isset($data['spawnPos']) ? self::arrToVec($data['spawnPos']) : null;
        $mine->hidePos = isset($data['hidePos']) ? self::arrToVec($data['hidePos']) : null;
        $mine->regenInterval = $data['regenInterval'] ?? 100;
        return $mine;
    }

    /** @return array{x: float, y: float, z: float} */
    private function vecToArr(Vector3 $vec) : array{
        return ['x' => $vec->x, 'y' => $vec->y, 'z' => $vec->z];
    }

    /** @param array{x?: float, y?: float, z?: float} $arr */
    private static function arrToVec(array $arr) : Vector3{
        return new Vector3(
            (float) ($arr['x'] ?? 0),
            (float) ($arr['y'] ?? 0),
            (float) ($arr['z'] ?? 0)
        );
    }
}
