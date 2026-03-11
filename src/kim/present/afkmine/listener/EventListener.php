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

namespace kim\present\afkmine\listener;

use kim\present\afkmine\form\ModalForm;
use kim\present\afkmine\Main;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

class EventListener implements Listener{

    private Main $plugin;

    /** @var array<string, true> */
    private array $confirmingExit = [];

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onBlockBreak(BlockBreakEvent $event) : void{
        $player = $event->getPlayer();
        $session = $this->plugin->getCreatorSession($player);
        if($session === null){
            return;
        }

        $session->handleBlockBreak($event->getBlock()->getPosition());
        $event->cancel();
    }

    public function onInteract(PlayerInteractEvent $event) : void{
        $player = $event->getPlayer();
        $session = $this->plugin->getCreatorSession($player);

        if($session !== null && $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            $item = $event->getItem();
            $block = $event->getBlock();
            $session->handleInteract($item, $block->getPosition());
            $event->cancel();
        }
    }

    public function onItemUse(PlayerItemUseEvent $event) : void{
        $session = $this->plugin->getCreatorSession($event->getPlayer());
        if($session !== null){
            $session->handleItemUse($event->getItem());
            $event->cancel();
        }
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $player = $event->getPlayer();
        $this->plugin->removeCreatorSession($player);
        $this->plugin->removeAFKSession($player);
    }

    public function onMove(PlayerMoveEvent $event) : void{
        $player = $event->getPlayer();
        $session = $this->plugin->getAFKSession($player);

        if($session === null){
            return;
        }

        // 시작 직후 잠시 동안은 플레이어가 위치를 미세 조정해도 종료 폼을 띄우지 않는다.
        if($session->isInMovementGracePeriod()){
            return;
        }

        // Check if significant movement occurred (ignore small camera movements if we want strictness,
        // but for AFK mining usually any movement is a trigger, or position change)
        $from = $event->getFrom();
        $to = $event->getTo();

        $minDistanceSq = $this->plugin->getPluginConfig()->movementMinDistanceSquared;
        if($from->distanceSquared($to) < $minDistanceSq){
            return;
        }

        // Prevent movement
        $event->cancel();

        // Check if already in confirmation
        if(isset($this->confirmingExit[$player->getName()])){
            return;
        }

        $this->confirmingExit[$player->getName()] = true;

        $form = new ModalForm(
            $this->plugin->translate("afkmine.afk.exit.title", [], $player),
            $this->plugin->translate("afkmine.afk.exit.content", [], $player),
            $this->plugin->translate("afkmine.afk.exit.yes", [], $player),
            $this->plugin->translate("afkmine.afk.exit.no", [], $player),
            function(Player $player, $data){
                unset($this->confirmingExit[$player->getName()]);

                // True = Button 1 (Yes), False = Button 2 (No) or Null (Closed)
                if($data === true){
                    $this->plugin->removeAFKSession($player);
                }
            }
        );

        $player->sendForm($form);
    }
}
