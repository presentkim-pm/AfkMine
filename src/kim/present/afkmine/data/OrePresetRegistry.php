<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \ / __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___/|_| |_|\__|_|\_\_|_| |_| |_|
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

use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;

/**
 * Registry of ore presets. Default IDs (stone, nether, deepslate) are registered on first use
 * and can be overridden by registering again with the same id.
 *
 * @see OrePreset
 * @see OreStyle
 */
final class OrePresetRegistry{

    public const DEFAULT_STONE = "stone";
    public const DEFAULT_NETHER = "nether";
    public const DEFAULT_DEEPSLATE = "deepslate";

    private static ?self $instance = null;

    /** @var array<string, OrePreset> */
    private array $presets = [];

    private bool $defaultsRegistered = false;

    public static function getInstance() : self{
        if(self::$instance === null){
            self::$instance = new self();
        }
        $registry = self::$instance;
        if(!$registry->defaultsRegistered){
            $registry->registerDefaults();
            $registry->defaultsRegistered = true;
        }
        return $registry;
    }

    /** Registers a preset; overwrites if id already exists. */
    public function register(OrePreset $preset) : void{
        $this->presets[$preset->getId()] = $preset;
    }

    public function get(string $id) : ?OrePreset{
        return $this->presets[$id] ?? null;
    }

    /** @return string[] */
    public function all() : array{
        return array_keys($this->presets);
    }

    /** @return list<string> Default preset IDs used by creation UI (slots 1-3). */
    public function getDefaultIds() : array{
        return [self::DEFAULT_STONE, self::DEFAULT_NETHER, self::DEFAULT_DEEPSLATE];
    }

    public function isValid(string $id) : bool{
        return isset($this->presets[$id]);
    }

    public function getBaseBlock(string $id) : Block{
        $preset = $this->get($id);
        return $preset !== null ? $preset->getBaseBlock() : VanillaBlocks::STONE();
    }

    public function getRandomOre(string $id) : Block{
        $preset = $this->get($id);
        if($preset !== null){
            return $preset->getRandomOre();
        }
        return VanillaBlocks::IRON_ORE();
    }

    public function getPreviewOre(string $id) : Block{
        $preset = $this->get($id);
        return $preset !== null ? $preset->getPreviewOre() : VanillaBlocks::IRON_ORE();
    }

    /** Returns whether the block is an ore in any registered preset. */
    public function isOre(Block $block) : bool{
        foreach($this->presets as $preset){
            if($preset->containsOreBlock($block)){
                return true;
            }
        }
        return false;
    }

    /** Returns whether the block is the fill (base) block for the given preset id. */
    public function isFillBlock(Block $block, string $id) : bool{
        $preset = $this->get($id);
        return $preset !== null && $preset->isFillBlock($block);
    }

    private function registerDefaults() : void{
        $this->register(new OrePreset(
            self::DEFAULT_STONE,
            "afkmine.orePreset.stone",
            VanillaBlocks::STONE(),
            OrePreset::defaultStoneOres(),
            VanillaBlocks::STAINED_HARDENED_GLASS()->setColor(DyeColor::LIGHT_BLUE()),
        ));
        $this->register(new OrePreset(
            self::DEFAULT_NETHER,
            "afkmine.orePreset.nether",
            VanillaBlocks::NETHERRACK(),
            OrePreset::defaultNetherOres(),
            VanillaBlocks::STAINED_HARDENED_GLASS()->setColor(DyeColor::RED()),
        ));
        $this->register(new OrePreset(
            self::DEFAULT_DEEPSLATE,
            "afkmine.orePreset.deepslate",
            VanillaBlocks::COBBLED_DEEPSLATE(),
            OrePreset::defaultDeepslateOres(),
            VanillaBlocks::STAINED_HARDENED_GLASS()->setColor(DyeColor::GRAY()),
        ));
    }
}
