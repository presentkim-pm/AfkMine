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

namespace kim\present\afkmine\session;

use kim\present\afkmine\data\Mine;
use kim\present\afkmine\data\MineManager;
use kim\present\afkmine\form\ModalForm;
use kim\present\afkmine\Main;
use kim\present\cameraapi\Camera;
use kim\present\cameraapi\session\CameraSession;
use kim\present\utils\session\listener\attribute\SessionEventHandler;
use kim\present\utils\session\Session;
use kim\present\utils\session\SessionManager;
use kim\present\utils\session\SessionTerminateReasons;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class AFKSession extends Session{

    private Main $plugin;
    private ?Mine $mine = null;

    private ?Position $originalPos = null;
    private bool $isFlying = false;
    private bool $entered = false;
    private bool $confirmingExit = false;

    private int $rewardTick = 0;
    private int $rewardInterval;
    private int $rewardMaxItemsPerTick = 0;

    private float $maxAfkUntil = 0.0;

    private float $movementGraceUntil = 0.0;

    /**
     * @param SessionManager $manager The manager that owns this session.
     * @param Player         $player  The operator entering creation mode.
     */
    public function __construct(SessionManager $manager, Player $player){
        parent::__construct($manager, $player);
        $this->plugin = Main::getInstance();

        $config = $this->plugin->getPluginConfig();
        $this->rewardInterval = $config->rewardIntervalTicks;
        $this->rewardMaxItemsPerTick = $config->rewardMaxItemsPerTick;
    }

    protected function onStart() : void{
        $player = $this->getPlayer();
        $playerName = $player->getName();

        $this->mine = MineManager::getInstance()->getRandomAvailableMineFor($playerName);
        if($this->mine === null || !$this->mine->data->isValid()){
            $player->sendMessage($this->plugin->translate("afkmine.afk.noMine", [], $player));
            $this->terminate(SessionTerminateReasons::START_FAILED);
            return;
        }

        if(!$this->mine->occupy($playerName)){
            $player->sendMessage($this->plugin->translate("afkmine.afk.occupied", [], $player));
            $this->terminate(SessionTerminateReasons::START_FAILED);
            return;
        }

        if($this->mine->getMiner() === null){
            $player->sendMessage($this->plugin->translate("afkmine.afk.initializing", [], $player));
            $this->mine->release($playerName);
            $this->terminate(SessionTerminateReasons::START_FAILED);
            return;
        }

        $this->originalPos = $player->getPosition();
        $this->isFlying = $player->isFlying();

        $hidePos = $this->mine->data->hidePos;
        if($hidePos === null){
            $this->mine->release($playerName);
            $this->terminate(SessionTerminateReasons::START_FAILED);
            return;
        }

        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->mine->data->worldName);
        if($world === null){
            $this->mine->release($playerName);
            $this->terminate(SessionTerminateReasons::START_FAILED);
            return;
        }

        $player->setFlying(true);

        $config = $this->plugin->getPluginConfig();
        if($config->maxAfkSeconds > 0){
            $this->maxAfkUntil = microtime(true) + $config->maxAfkSeconds;
        }else{
            $this->maxAfkUntil = 0.0;
        }

        $this->movementGraceUntil = microtime(true) + $config->movementGraceSeconds;

        Camera::timeline()
              ->add(fn(CameraSession $session) => $this->mine->getInitCameraTimeline()->play($session))
              ->wait(3.0)
              ->add(fn(CameraSession $session) => $this->mine->getLoopCameraTimeline()->play($session))
              ->play($player);

        $player->teleport(new Position($hidePos->x, $hidePos->y, $hidePos->z, $world));
        $this->entered = true;

        $player->sendMessage($this->plugin->translate("afkmine.afk.started", [], $player));
        $player->sendMessage($this->plugin->translate("afkmine.afk.hint.moveToExit", [], $player));
        $player->sendMessage($this->plugin->translate("afkmine.afk.hint.rewardInfo", [], $player));
    }

    /**
     * Returns whether movement checks should be ignored briefly after session start.
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
            $player = $this->getPlayer();
            $player->sendMessage($this->plugin->translate("afkmine.afk.maxTimeEnded", [], $player));
            $this->terminate(SessionTerminateReasons::TIMEOUT);
        }
    }

    private function giveReward() : void{
        if($this->mine === null || !$this->isActive()){
            return;
        }

        $player = $this->getPlayer();
        $drops = $this->mine->takePendingDropsFor($player->getName(), $this->rewardMaxItemsPerTick);
        if($drops === []){
            return;
        }

        $inventory = $player->getInventory();
        $world = $player->getWorld();
        $position = $player->getPosition();

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
            $player->sendTip(
                $this->plugin->translate("afkmine.afk.rewardTip", ["0" => (string) $totalCount], $player)
            );
        }
    }

    #[SessionEventHandler(PlayerMoveEvent::class)]
    public function onMove(PlayerMoveEvent $event) : void{
        if($this->isInMovementGracePeriod()){
            return;
        }

        $from = $event->getFrom();
        $to = $event->getTo();
        $minDistanceSq = $this->plugin->getPluginConfig()->movementMinDistanceSquared;
        if($from->distanceSquared($to) < $minDistanceSq){
            return;
        }

        $event->cancel();
        if($this->confirmingExit){
            return;
        }

        $player = $this->getPlayer();
        if(!$player instanceof Player){
            return;
        }

        $this->confirmingExit = true;
        $form = new ModalForm(
            $this->plugin->translate("afkmine.afk.exit.title", [], $player),
            $this->plugin->translate("afkmine.afk.exit.content", [], $player),
            $this->plugin->translate("afkmine.afk.exit.yes", [], $player),
            $this->plugin->translate("afkmine.afk.exit.no", [], $player),
            function(Player $player, $data) : void{
                $this->confirmingExit = false;
                if($data === true){
                    $this->terminate("manual_exit");
                }
            }
        );
        $player->sendForm($form);
    }

    protected function onTerminate(string $reason) : void{
        $player = $this->getPlayer();
        Camera::of($player)->stop()->clear();

        $this->mine?->release($player->getName());

        if($this->entered && $this->originalPos instanceof Position){
            $player->teleport($this->originalPos);
            $player->setFlying($this->isFlying);
            $player->sendMessage($this->plugin->translate("afkmine.afk.stopped", [], $player));
        }

        $this->entered = false;
        $this->confirmingExit = false;
        $this->mine = null;
    }
}
