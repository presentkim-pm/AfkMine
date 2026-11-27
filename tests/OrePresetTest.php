<?php

declare(strict_types=1);

namespace kim\present\afkmine\tests;

use kim\present\afkmine\data\OrePreset;
use kim\present\afkmine\data\OrePresetRegistry;
use kim\present\afkmine\data\OreStyle;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;

final class OrePresetTest extends TestCase{

    public function testDefaultPresetsAreRegistered() : void{
        $registry = OrePresetRegistry::getInstance();
        foreach($registry->getDefaultIds() as $id){
            self::assertTrue($registry->isValid($id), $id);
            $preset = $registry->get($id);
            self::assertNotNull($preset);
            self::assertSame($id, $preset->getId());
            self::assertSame("afkmine.orePreset.$id", $preset->getNameKey());
        }
    }

    public function testRandomOreBelongsToThePreset() : void{
        $registry = OrePresetRegistry::getInstance();
        foreach($registry->getDefaultIds() as $id){
            $preset = $registry->get($id);
            self::assertNotNull($preset);
            for($i = 0; $i < 30; ++$i){
                self::assertTrue($preset->containsOreBlock($preset->getRandomOre()));
            }
            self::assertTrue($preset->containsOreBlock($preset->getPreviewOre()));
            self::assertFalse($preset->containsOreBlock($preset->getBaseBlock()));
            self::assertTrue($preset->isFillBlock($preset->getBaseBlock()));
        }
    }

    public function testOreStyleFacade() : void{
        self::assertTrue(OreStyle::isOre(VanillaBlocks::DIAMOND_ORE()));
        self::assertTrue(OreStyle::isOre(VanillaBlocks::NETHER_QUARTZ_ORE()));
        self::assertFalse(OreStyle::isOre(VanillaBlocks::STONE()));
        self::assertTrue(OreStyle::isFillBlock(VanillaBlocks::NETHERRACK(), OreStyle::NETHER));
        self::assertSame("afkmine.orePreset.stone", OreStyle::getNameKey(OreStyle::STONE));
        self::assertSame("unknown", OreStyle::getNameKey("unknown"));
    }

    public function testWeightedRandomOre() : void{
        $preset = new OrePreset("weighted", "key", VanillaBlocks::STONE(), [
            ['block' => VanillaBlocks::COAL_ORE(), 'weight' => 1],
            ['block' => VanillaBlocks::GOLD_ORE(), 'weight' => 0],
        ], VanillaBlocks::GLASS());

        for($i = 0; $i < 30; ++$i){
            self::assertSame(VanillaBlocks::COAL_ORE()->getTypeId(), $preset->getRandomOre()->getTypeId());
        }
    }

    public function testPresetNeedsAnOre() : void{
        $this->expectException(\InvalidArgumentException::class);
        new OrePreset("empty", "key", VanillaBlocks::STONE(), [], VanillaBlocks::GLASS());
    }
}
