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

namespace kim\present\afkmine\entity;

use kim\present\afkmine\data\Mine;
use kim\present\afkmine\data\MineData;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\BlockPunchParticle;
use pocketmine\world\sound\BlockPunchSound;

class MinerEntity extends Entity implements NeverSavedWithChunkEntity{

    private const TICKS_FOR_MINING = 60; // 3 seconds

    private const STATE_IDLE = 0;
    private const STATE_MOVING = 1;
    private const STATE_MINING = 2;

    private ?Mine $mine = null;
    private ?MineData $mineData = null;
    private int $state = self::STATE_IDLE;
    private ?Vector3 $targetBlock = null;
    private int $mineTicks = 0;
    private int $moveTicks = 0;

    public function setMine(Mine $mine) : void{
        $this->mine = $mine;
    }

    public function setMineData(MineData $data) : void{
        $this->mineData = $data;
    }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->setCanSaveWithChunk(false);
        $this->setScale(0.66);
    }

    public function onUpdate(int $currentTick) : bool{
        if($this->mineData === null){
            $this->flagForDespawn();
            return false;
        }

        $hasUpdate = parent::onUpdate($currentTick);
        if($this->isClosed() || !$this->isAlive()){
            return false;
        }

        switch($this->state){
            case self::STATE_IDLE: // IDLE
                $this->findTarget();
                break;
            case self::STATE_MOVING: // MOVING
                $this->moveToTarget();
                break;
            case self::STATE_MINING: // MINING
                $this->mineTarget();
                break;
        }

        return $hasUpdate;
    }

    private function findTarget() : void{
        if($this->mineData === null){
            return;
        }

        $candidates = $this->mineData->getOreSpotsFlat();
        if($candidates === []){
            return;
        }

        $this->targetBlock = $candidates[array_rand($candidates)];
        $this->state = self::STATE_MOVING;
    }

    private function moveToTarget() : void{
        if($this->targetBlock === null){
            $this->state = self::STATE_IDLE;
            return;
        }

        $this->moveTicks++;
        if($this->moveTicks > 100){
            $this->state = self::STATE_IDLE;
            $this->targetBlock = null;
            $this->moveTicks = 0;
            return;
        }

        $standPos = $this->findMiningPosition($this->targetBlock);

        if($standPos === null){
            $this->state = self::STATE_IDLE;
            $this->targetBlock = null;
            return;
        }

        $targetCenter = $standPos->add(0.5, 0, 0.5);
        $diff = $targetCenter->subtractVector($this->getPosition());
        $flatDiff = new Vector3($diff->x, 0, $diff->z);
        $distance = $flatDiff->length();

        if($distance < 0.5){
            $this->state = self::STATE_MINING;
            $this->mineTicks = 0;
            $this->moveTicks = 0;
            return;
        }

        $speed = 0.25;
        $moveVec = $diff->normalize()->multiply($speed);

        $this->lookAt($this->targetBlock->add(0.5, 0.5, 0.5));
        $this->move($moveVec->x, $moveVec->y, $moveVec->z);
    }

    private function findMiningPosition(Vector3 $target) : ?Vector3{
        $world = $this->getWorld();
        $bestPos = null;
        $minDistSq = PHP_INT_MAX;
        $currentPos = $this->getPosition();

        $sides = [
            [0, 1, 0], [0, -1, 0],
            [1, 0, 0], [-1, 0, 0],
            [0, 0, 1], [0, 0, -1]
        ];

        foreach($sides as $offset){
            $checkPos = $target->add($offset[0], $offset[1], $offset[2]);
            $block = $world->getBlock($checkPos);

            if($block->getTypeId() !== VanillaBlocks::AIR()->getTypeId()){
                continue;
            }

            $distSq = $checkPos->add(0.5, 0.5, 0.5)->distanceSquared($currentPos);
            if($distSq < $minDistSq){
                $minDistSq = $distSq;
                $bestPos = $checkPos;
            }
        }

        return $bestPos;
    }


    /**
     * Changes the entity's yaw and pitch to make it look at the specified Vector3 position. For mobs, this will cause
     * their heads to turn.
     */
    public function lookAt(Vector3 $target) : void{
        $xDist = $target->x - $this->location->x;
        $zDist = $target->z - $this->location->z;

        $horizontal = sqrt($xDist ** 2 + $zDist ** 2);
        $vertical = $target->y - ($this->location->y + $this->getEyeHeight());
        $pitch = -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down

        $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
        if($yaw < 0){
            $yaw += 360.0;
        }

        $this->setRotation($yaw, $pitch);
    }

    private function mineTarget() : void{
        if($this->targetBlock === null){
            $this->state = self::STATE_IDLE;
            return;
        }

        $targetPos = $this->targetBlock;
        $this->mineTicks++;
        $this->lookAt($targetPos->add(0.5, 0.5, 0.5));
        $world = $this->getWorld();
        // Start break animation (Cracks)
        if($this->mineTicks === 1){
            $world->broadcastPacketToViewers($targetPos, LevelEventPacket::create(
                LevelEvent::BLOCK_START_BREAK,
                (int) (65535 / self::TICKS_FOR_MINING),
                $targetPos
            ));
        }

        // Swing arm
        if($this->mineTicks % 10 === 0){
            $block = $world->getBlock($targetPos);
            $world->addParticle($targetPos, new BlockPunchParticle($block, mt_rand(0, 5)));
            $world->addSound($targetPos, new BlockPunchSound($block));
            $this->sendAnimation(
                "animation.allay.dance",
                "controller.animation.allay.dancing"
            );
            $this->sendAnimation("animation.warden.attack",
                "controller.animation.warden.melee_attacking"
            );
        }

        // Finish mining
        if($this->mineTicks >= self::TICKS_FOR_MINING){
            // Stop animation
            $world->broadcastPacketToViewers($targetPos, LevelEventPacket::create(
                LevelEvent::BLOCK_STOP_BREAK,
                0,
                $targetPos
            ));

            $this->breakBlock();
            $this->state = self::STATE_IDLE;
            $this->targetBlock = null;
        }
    }


    private function breakBlock() : void{
        $targetPos = $this->targetBlock;
        $mineData = $this->mineData;
        if($targetPos === null || $mineData === null){
            return;
        }
        $world = $this->getWorld();
        $block = $world->getBlock($targetPos);

        if($this->mine !== null){
            $owner = $this->mine->getCurrentPlayerName();
            if($owner !== null){
                $drops = $block->getDrops(VanillaItems::DIAMOND_PICKAXE());
                if($drops !== []){
                    $this->mine->addPendingDropsFor($owner, $drops);
                }
            }
        }

        $preset = $mineData->getPresetAt(
            $targetPos->getFloorX(),
            $targetPos->getFloorY(),
            $targetPos->getFloorZ()
        );
        $fillBlock = $preset?->getBaseBlock() ?? VanillaBlocks::STONE();
        $world->setBlock($targetPos, $fillBlock);
        $world->addParticle($targetPos->add(0.5, 0.5, 0.5), new BlockBreakParticle($fillBlock));
    }

    public function attack(EntityDamageEvent $source) : void{
        $source->cancel();
    }

    private function sendAnimation(string $animationName, string $animationController) : void{
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [
            AnimateEntityPacket::create(
                $animationName,
                "",
                "",
                0,
                $animationController,
                10,
                [$this->getId()]
            )
        ]);
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.5, 1.0);
    }

    protected function getInitialDragMultiplier() : float{
        return 0.02;
    }

    protected function getInitialGravity() : float{
        return 0;
    }

    protected function sendSpawnPacket(Player $player) : void{
        parent::sendSpawnPacket($player);

        $pk = MobEquipmentPacket::create(
            $this->getId(),
            ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaItems::DIAMOND_PICKAXE())),
            0, 0, 0
        );
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::COPPER_GOLEM;
    }
}
