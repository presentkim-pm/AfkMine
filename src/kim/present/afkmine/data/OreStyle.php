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

use pocketmine\block\Block;

/**
 * Facade for ore preset registry. Default preset IDs (stone, nether, deepslate) can be
 * overridden by registering presets with the same id via OrePresetRegistry.
 *
 * @see OrePresetRegistry
 * @see OrePreset
 * @see MineData::$baseType
 */
final class OreStyle{

    /** @deprecated Use OrePresetRegistry::DEFAULT_STONE */
    public const STONE = "stone";

    /** @deprecated Use OrePresetRegistry::DEFAULT_NETHER */
    public const NETHER = "nether";

    /** @deprecated Use OrePresetRegistry::DEFAULT_DEEPSLATE */
    public const DEEPSLATE = "deepslate";

    /** Returns the block to place when an ore is mined (no empty air). */
    public static function getFillBlock(string $baseType) : Block{
        return OrePresetRegistry::getInstance()->getBaseBlock($baseType);
    }

    /** Returns a random ore block for the given style (for regen). */
    public static function getRandomOre(string $baseType) : Block{
        return OrePresetRegistry::getInstance()->getRandomOre($baseType);
    }

    /** Returns whether the block is an ore (any registered preset). */
    public static function isOre(Block $block) : bool{
        return OrePresetRegistry::getInstance()->isOre($block);
    }

    /** Returns whether the block is the fill block for the given style (regen candidates). */
    public static function isFillBlock(Block $block, string $baseType) : bool{
        return OrePresetRegistry::getInstance()->isFillBlock($block, $baseType);
    }

    /**
     * Returns the translation key for UI (e.g. "afkmine.orePreset.stone").
     *
     * Use libmultilingual Translator to localize this key when displaying
     * to players.
     */
    public static function getNameKey(string $baseType) : string{
        $preset = OrePresetRegistry::getInstance()->get($baseType);
        return $preset !== null ? $preset->getNameKey() : $baseType;
    }

    /** @return string[] */
    public static function all() : array{
        return OrePresetRegistry::getInstance()->all();
    }

    public static function isValid(string $baseType) : bool{
        return OrePresetRegistry::getInstance()->isValid($baseType);
    }
}
