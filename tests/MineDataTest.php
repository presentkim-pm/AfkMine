<?php

declare(strict_types=1);

namespace kim\present\afkmine\tests;

use kim\present\afkmine\data\MineData;
use kim\present\afkmine\data\OrePresetRegistry;
use kim\present\afkmine\data\OreSpotEntry;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class MineDataTest extends TestCase{

    private function createMineData() : MineData{
        $stone = OrePresetRegistry::getInstance()->get(OrePresetRegistry::DEFAULT_STONE);
        self::assertNotNull($stone);

        $data = new MineData("test", "world");
        $data->oreSpots[World::blockHash(1, 2, 3)] = new OreSpotEntry(new Vector3(1, 2, 3), $stone);
        $data->originalBlockStates[World::blockHash(1, 2, 3)] = 5;
        $data->cameraPoints = [new Vector3(0, 70, 0), new Vector3(4, 70, 4)];
        $data->spawnPos = new Vector3(1.5, 3, 3.5);
        $data->hidePos = new Vector3(10.5, 3, 10.5);
        $data->regenInterval = 20;
        return $data;
    }

    public function testIsValidNeedsEveryPart() : void{
        $data = $this->createMineData();
        self::assertTrue($data->isValid());

        $copy = clone $data;
        $copy->oreSpots = [];
        self::assertFalse($copy->isValid());

        $copy = clone $data;
        $copy->cameraPoints = [];
        self::assertFalse($copy->isValid());

        $copy = clone $data;
        $copy->spawnPos = null;
        self::assertFalse($copy->isValid());

        $copy = clone $data;
        $copy->hidePos = null;
        self::assertFalse($copy->isValid());
    }

    public function testJsonRoundTrip() : void{
        $data = $this->createMineData();
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $restored = MineData::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        self::assertSame("test", $restored->name);
        self::assertSame("world", $restored->worldName);
        self::assertSame(20, $restored->regenInterval);
        self::assertTrue($restored->isValid());
        self::assertEquals($data->cameraPoints, $restored->cameraPoints);
        self::assertEquals($data->spawnPos, $restored->spawnPos);
        self::assertEquals($data->hidePos, $restored->hidePos);
        self::assertSame($data->originalBlockStates, $restored->originalBlockStates);
        self::assertSame("stone", $restored->getPresetAt(1, 2, 3)?->getId());
        self::assertNull($restored->getPresetAt(9, 9, 9));
    }

    public function testRemovedCameraPointDoesNotLeaveAGapInSavedData() : void{
        $data = $this->createMineData();
        unset($data->cameraPoints[0]);

        $json = json_encode($data, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"cameraPoints":[{', $json, "camera points must be saved as a JSON list");

        $restored = MineData::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame([0], array_keys($restored->cameraPoints));
        self::assertEquals(new Vector3(4, 70, 4), $restored->cameraPoints[0]);
    }

    public function testCameraPointsSavedAsObjectAreReindexed() : void{
        $restored = MineData::fromArray([
            "name" => "old",
            "worldName" => "world",
            "oreSpots" => [],
            "cameraPoints" => [1 => ["x" => 1, "y" => 2, "z" => 3]],
        ]);
        self::assertSame([0], array_keys($restored->cameraPoints));
    }

    public function testRegenIntervalIsNeverZero() : void{
        foreach([0, -5] as $value){
            $restored = MineData::fromArray(["name" => "m", "worldName" => "w", "regenInterval" => $value]);
            self::assertSame(1, $restored->regenInterval);
        }
        $restored = MineData::fromArray(["name" => "m", "worldName" => "w"]);
        self::assertSame(100, $restored->regenInterval);
    }

    public function testLegacyOreSpotFormats() : void{
        $v1 = MineData::fromArray([
            "name" => "m", "worldName" => "w", "baseType" => "nether",
            "oreSpots" => [["x" => 1, "y" => 2, "z" => 3], ["x" => 4, "y" => 5, "z" => 6]],
        ]);
        self::assertCount(2, $v1->oreSpots);
        self::assertSame("nether", $v1->getPresetAt(1, 2, 3)?->getId());

        $v2 = MineData::fromArray([
            "name" => "m", "worldName" => "w",
            "oreSpotsByType" => ["deepslate" => [["x" => 1, "y" => 2, "z" => 3]], "unknown" => [["x" => 9, "y" => 9, "z" => 9]]],
        ]);
        self::assertCount(1, $v2->oreSpots);
        self::assertSame("deepslate", $v2->getPresetAt(1, 2, 3)?->getId());

        $v0 = MineData::fromArray([
            "name" => "m", "worldName" => "w",
            "coalSpots" => [["x" => 1, "y" => 2, "z" => 3]], "diamondSpots" => [["x" => 4, "y" => 5, "z" => 6]],
        ]);
        self::assertCount(2, $v0->oreSpots);
        self::assertSame("stone", $v0->getPresetAt(4, 5, 6)?->getId());
    }

    public function testUnknownPresetsAreSkipped() : void{
        $data = MineData::fromArray([
            "name" => "m", "worldName" => "w",
            "oreSpots" => [World::blockHash(1, 2, 3) => "stone", World::blockHash(4, 5, 6) => "missing"],
        ]);
        self::assertCount(1, $data->oreSpots);
        self::assertCount(1, $data->getOreSpotsFlat());
    }
}
