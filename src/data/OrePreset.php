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

use kim\present\libmultilingual\Translator;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;

/**
     * Immutable ore preset: id, name key, fill (base) block, and weighted random ore list.
 * Used by OrePresetRegistry; stone / nether / deepslate are built-in and can be overridden.
 *
 * @see OrePresetRegistry
 */
final class OrePreset{

    /**
     * @param array<int, array{block: Block, weight: int}> $randomOre    Weighted list for regen. At least one entry.
     * @param Block                                        $previewGlass Glass block used for selection preview in
     *                                                                   creation mode.
     */
    public function __construct(
        private string $id,
        private string $nameKey,
        private Block $baseBlock,
        private array $randomOre,
        private Block $previewGlass,
    ){
        if($this->randomOre === []){
            throw new \InvalidArgumentException('randomOre must have at least one entry');
        }
    }

    public function getId() : string{
        return $this->id;
    }

    /**
     * Returns the translation key for this preset's display name.
     *
     * Example: "afkmine.orePreset.stone"
     */
    public function getNameKey() : string{
        return $this->nameKey;
    }

    /**
     * Returns the localized display name using the given Translator.
     *
     * @param Translator         $translator Translator instance
     * @param CommandSender|null $target     Optional translation target (player/console)
     */
    public function getName(Translator $translator, ?CommandSender $target = null) : string{
        return $translator->translate($this->nameKey, [], $target);
    }

    public function getDisplayName() : string{
        return $this->nameKey;
    }

    /** Returns the block to place when an ore at this spot is mined. */
    public function getBaseBlock() : Block{
        return $this->baseBlock;
    }

    /** Returns a random ore block from the weighted list (for regen). */
    public function getRandomOre() : Block{
        $total = 0;
        foreach($this->randomOre as $entry){
            $total += $entry['weight'];
        }
        $r = mt_rand(1, $total);
        foreach($this->randomOre as $entry){
            $r -= $entry['weight'];
            if($r <= 0){
                return $entry['block'];
            }
        }
        return $this->randomOre[array_key_first($this->randomOre)]['block'];
    }

    /** Returns a representative ore block for preview (first in the list). */
    public function getPreviewOre() : Block{
        return $this->randomOre[array_key_first($this->randomOre)]['block'];
    }

    /** Returns whether the given block is one of this preset's ore options. */
    public function containsOreBlock(Block $block) : bool{
        $typeId = $block->getTypeId();
        foreach($this->randomOre as $entry){
            if($entry['block']->getTypeId() === $typeId){
                return true;
            }
        }
        return false;
    }

    /** Returns the glass block used for selection preview in creation mode. */
    public function getPreviewGlass() : Block{
        return $this->previewGlass;
    }

    /** Returns whether the given block is this preset's fill (base) block. */
    public function isFillBlock(Block $block) : bool{
        return $block->getTypeId() === $this->baseBlock->getTypeId();
    }

    /**
     * Builds the default stone preset (overworld).
     *
     * @return array<int, array{block: Block, weight: int}>
     */
    public static function defaultStoneOres() : array{
        $v = VanillaBlocks::class;
        return [
            ['block' => $v::COAL_ORE(), 'weight' => 1],
            ['block' => $v::COPPER_ORE(), 'weight' => 1],
            ['block' => $v::REDSTONE_ORE(), 'weight' => 1],
            ['block' => $v::LAPIS_LAZULI_ORE(), 'weight' => 1],
            ['block' => $v::IRON_ORE(), 'weight' => 1],
            ['block' => $v::GOLD_ORE(), 'weight' => 1],
            ['block' => $v::DIAMOND_ORE(), 'weight' => 1],
            ['block' => $v::EMERALD_ORE(), 'weight' => 1],
        ];
    }

    /**
     * Builds the default nether preset.
     *
     * @return array<int, array{block: Block, weight: int}>
     */
    public static function defaultNetherOres() : array{
        $v = VanillaBlocks::class;
        return [
            ['block' => $v::NETHER_GOLD_ORE(), 'weight' => 1],
            ['block' => $v::NETHER_QUARTZ_ORE(), 'weight' => 1],
        ];
    }

    /**
     * Builds the default deepslate preset.
     *
     * @return array<int, array{block: Block, weight: int}>
     */
    public static function defaultDeepslateOres() : array{
        $v = VanillaBlocks::class;
        return [
            ['block' => $v::DEEPSLATE_COAL_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_COPPER_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_REDSTONE_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_LAPIS_LAZULI_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_IRON_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_GOLD_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_DIAMOND_ORE(), 'weight' => 1],
            ['block' => $v::DEEPSLATE_EMERALD_ORE(), 'weight' => 1],
        ];
    }
}
