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

namespace kim\present\afkmine\session;

use kim\present\afkmine\data\Mine;
use kim\present\afkmine\data\MineManager;
use kim\present\afkmine\Main;
use kim\present\cameraapi\Camera;
use kim\present\cameraapi\session\CameraSession;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class AFKSession{

    private Main $plugin;
    private Player $player;
    private ?Mine $mine = null;

    private Position $originalPos;
    private bool $isFlying;

    private int $rewardTick = 0;
    private int $rewardInterval;
    private int $rewardMaxItemsPerTick = 0;

    private float $maxAfkUntil = 0.0;

    /** @var float 잠수 시작 후 일정 시간(초)까지는 이동 확인 폼을 띄우지 않기 위한 기준 시각 */
    private float $movementGraceUntil = 0.0;

    public function __construct(Main $plugin, Player $player){
        $this->plugin = $plugin;
        $this->player = $player;

        $config = $this->plugin->getPluginConfig();
        $this->rewardInterval = $config->rewardIntervalTicks;
        $this->rewardMaxItemsPerTick = $config->rewardMaxItemsPerTick;
    }

    public function start() : bool{
        $this->mine = MineManager::getInstance()->getRandomAvailableMineFor($this->player->getName());
        if($this->mine === null || !$this->mine->data->isValid()){
            $this->player->sendMessage(
                $this->plugin->translate("afkmine.afk.noMine", [], $this->player)
            );
            return false;
        }

        if(!$this->mine->occupy($this->player->getName())){
            $this->player->sendMessage(
                $this->plugin->translate("afkmine.afk.occupied", [], $this->player)
            );
            return false;
        }

        if($this->mine->getMiner() === null){
            $this->player->sendMessage(
                $this->plugin->translate("afkmine.afk.initializing", [], $this->player)
            );
            return false;
        }

        $this->originalPos = $this->player->getPosition();
        $this->isFlying = $this->player->isFlying();

        // Teleport to Hide Pos
        $hidePos = $this->mine->data->hidePos;
        if($hidePos === null){
            return false;
        }
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->mine->data->worldName);
        if($world === null){
            return false;
        }
        $this->player->setFlying(true);

        $config = $this->plugin->getPluginConfig();
        if($config->maxAfkSeconds > 0){
            $this->maxAfkUntil = microtime(true) + $config->maxAfkSeconds;
        }else{
            $this->maxAfkUntil = 0.0;
        }

        $this->movementGraceUntil = microtime(true) + $config->movementGraceSeconds;

        // Apply cinematic camera and teleport player to the hidden position
        Camera::timeline()
              ->add(fn(CameraSession $session) => $this->mine->getInitCameraTimeline()->play($session))
              ->wait(3.0)
              ->add(fn(CameraSession $session) => $this->mine->getLoopCameraTimeline()->play($session))
              ->play($this->player);

        $this->player->teleport(new Position($hidePos->x, $hidePos->y, $hidePos->z, $world));

        $this->player->sendMessage($this->plugin->translate("afkmine.afk.started", [], $this->player));
        $this->player->sendMessage($this->plugin->translate("afkmine.afk.hint.moveToExit", [], $this->player));
        $this->player->sendMessage($this->plugin->translate("afkmine.afk.hint.rewardInfo", [], $this->player));
        return true;
    }

    /**
     * 잠수 시작 후 이동 확인 폼을 무시해야 하는 그레이스 기간인지 여부를 반환한다.
     */
    public function isInMovementGracePeriod() : bool{
        return microtime(true) < $this->movementGraceUntil;
    }

    public function tick() : void{
        $this->rewardTick++;
        if($this->rewardTick >= $this->rewardInterval){
            $this->rewardTick = 0;
            $this->giveReward();
        }

        if($this->maxAfkUntil > 0.0 && microtime(true) >= $this->maxAfkUntil){
            $this->plugin->removeAFKSession($this->player);
            $this->player->sendMessage(
                $this->plugin->translate("afkmine.afk.maxTimeEnded", [], $this->player)
            );
        }
    }

    private function giveReward() : void{
        if($this->mine === null){
            return;
        }

        $drops = $this->mine->takePendingDropsFor($this->player->getName(), $this->rewardMaxItemsPerTick);
        if($drops === []){
            return;
        }

        $inventory = $this->player->getInventory();
        $world = $this->player->getWorld();
        $position = $this->player->getPosition();

        $totalCount = 0;
        $totalKinds = 0;

        foreach($drops as $item){
            if($item->isNull()){
                continue;
            }

            $totalKinds++;
            $totalCount += $item->getCount();

            if($inventory->canAddItem($item)){
                $inventory->addItem($item);
            }else{
                $world->dropItem($position, $item);
            }
        }

        if($totalKinds > 0){
            $this->player->sendTip(
                $this->plugin->translate("afkmine.afk.rewardTip", ["0" => (string) $totalCount], $this->player)
            );
        }
    }

    public function stop() : void{
        // Reset camera back to default view
        Camera::of($this->player)->stop()->clear();

        if($this->mine !== null){
            $this->mine->release($this->player->getName());
        }

        $this->player->teleport($this->originalPos);
        $this->player->setFlying($this->isFlying);

        $this->player->sendMessage(
            $this->plugin->translate("afkmine.afk.stopped", [], $this->player)
        );
    }
}
