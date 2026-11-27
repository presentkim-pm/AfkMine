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

namespace kim\present\afkmine\config;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

/**
 * Typed config values for AFKMine. Load once from Config and reuse.
 *
 * @see PluginBase::getConfig()
 */
final readonly class PluginConfig{

    public int $rewardIntervalTicks;
    public int $rewardMaxItemsPerTick;
    public float $movementGraceSeconds;
    public float $movementMinDistanceSquared;
    public float $cameraOffsetMax;
    public float $cameraHoldSeconds;
    public float $maxAfkSeconds;

    private function __construct(
        int $rewardIntervalTicks,
        int $rewardMaxItemsPerTick,
        float $movementGraceSeconds,
        float $movementMinDistanceSquared,
        float $cameraOffsetMax,
        float $cameraHoldSeconds,
        float $maxAfkSeconds
    ){
        $this->rewardIntervalTicks = $rewardIntervalTicks;
        $this->rewardMaxItemsPerTick = $rewardMaxItemsPerTick;
        $this->movementGraceSeconds = max(0.0, $movementGraceSeconds);
        $this->movementMinDistanceSquared = max(0.0, $movementMinDistanceSquared);
        $this->cameraOffsetMax = max(0.0, min(1.0, $cameraOffsetMax));
        $this->cameraHoldSeconds = max(0.0, $cameraHoldSeconds);
        $this->maxAfkSeconds = max(0.0, $maxAfkSeconds);
    }

    public static function fromConfig(Config $config) : self{
        $rewardIntervalSec = (float) $config->get("reward-interval", 10);
        $rewardIntervalTicks = (int) ($rewardIntervalSec * 20);

        $rewardMaxItems = (int) $config->get("reward-max-items-per-tick", 0);

        $movementGrace = (float) $config->get("movement-grace-seconds", 3.0);
        $movementMinDist = (float) $config->get("movement-min-distance", 0.1);
        $movementMinDistSq = $movementMinDist < 0 ? 0.0 : ($movementMinDist ** 2);

        $cameraOffset = (float) $config->get("camera-offset-max", 0.6);
        $cameraHold = (float) $config->get("camera-hold-seconds", 5.0);
        $maxAfk = (float) $config->get("max-afk-seconds", 0);

        return new self(
            $rewardIntervalTicks,
            $rewardMaxItems,
            $movementGrace,
            $movementMinDistSq,
            $cameraOffset,
            $cameraHold,
            $maxAfk
        );
    }
}
