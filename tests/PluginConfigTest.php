<?php

declare(strict_types=1);

namespace kim\present\afkmine\tests;

use kim\present\afkmine\config\PluginConfig;
use PHPUnit\Framework\TestCase;
use pocketmine\utils\Config;

final class PluginConfigTest extends TestCase{

    private string $file;

    protected function setUp() : void{
        $this->file = sys_get_temp_dir() . "/afkmine-config-" . bin2hex(random_bytes(6)) . ".json";
    }

    protected function tearDown() : void{
        @unlink($this->file);
    }

    public function testDefaults() : void{
        $config = PluginConfig::fromConfig(new Config($this->file, Config::JSON, []));

        self::assertSame(200, $config->rewardIntervalTicks);
        self::assertSame(0, $config->rewardMaxItemsPerTick);
        self::assertSame(3.0, $config->movementGraceSeconds);
        self::assertEqualsWithDelta(0.01, $config->movementMinDistanceSquared, 1e-9);
        self::assertSame(0.6, $config->cameraOffsetMax);
        self::assertSame(5.0, $config->cameraHoldSeconds);
        self::assertSame(0.0, $config->maxAfkSeconds);
    }

    public function testValuesAreClamped() : void{
        $config = PluginConfig::fromConfig(new Config($this->file, Config::JSON, [
            "reward-interval" => 2.5,
            "movement-grace-seconds" => -1,
            "movement-min-distance" => -3,
            "camera-offset-max" => 5,
            "camera-hold-seconds" => -2,
            "max-afk-seconds" => -10,
        ]));

        self::assertSame(50, $config->rewardIntervalTicks);
        self::assertSame(0.0, $config->movementGraceSeconds);
        self::assertSame(0.0, $config->movementMinDistanceSquared);
        self::assertSame(1.0, $config->cameraOffsetMax);
        self::assertSame(0.0, $config->cameraHoldSeconds);
        self::assertSame(0.0, $config->maxAfkSeconds);
    }
}
