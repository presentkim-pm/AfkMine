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

namespace kim\present\afkmine;

use kim\present\afkmine\config\PluginConfig;
use kim\present\afkmine\data\MineManager;
use kim\present\afkmine\session\AFKSession;
use kim\present\afkmine\session\CreatorSession;
use kim\present\libmultilingual\traits\MultilingualConfigTrait;
use kim\present\libmultilingual\traits\MultilingualPluginModifiableTrait;
use kim\present\utils\selectionvisualize\BlockPreview;
use kim\present\utils\session\SessionManager;
use kim\present\utils\session\SessionTerminateReasons;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;

class Main extends PluginBase{
    use SingletonTrait;
    use MultilingualPluginModifiableTrait;
    use MultilingualConfigTrait;

    private PluginConfig $pluginConfig;
    private BlockPreview $blockPreview;

    /**
     * @var SessionManager                          $afkSessionManager
     * @phpstan-var SessionManager<Main,AFKSession> $afkSessionManager
     */
    private SessionManager $afkSessionManager;

    /**
     * @var SessionManager                              $creatorSessionManager
     * @phpstan-var SessionManager<Main,CreatorSession> $creatorSessionManager
     */
    private SessionManager $creatorSessionManager;

    protected function onLoad() : void{
        self::setInstance($this);
    }

    protected function onEnable() : void{
        $this->reloadPluginConfig();
        $this->blockPreview = new BlockPreview($this);
        $this->afkSessionManager = new SessionManager($this, AFKSession::class);
        $this->creatorSessionManager = new SessionManager($this, CreatorSession::class);
        new MineManager($this);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            MineManager::getInstance()->tick();
            foreach($this->afkSessionManager->getAllSessions() as $session){
                $session->tick();
            }
        }), 1);
    }

    protected function onDisable() : void{
        $this->afkSessionManager->terminateAll(SessionTerminateReasons::PLUGIN_DISABLE);
        $this->creatorSessionManager->terminateAll(SessionTerminateReasons::PLUGIN_DISABLE);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(!$sender instanceof Player){
            $sender->sendMessage($this->translate("afkmine.cmd.onlyPlayer", [], $sender));
            return true;
        }

        if($command->getName() === "afkmineadmin"){
            if(!isset($args[0])){
                $sender->sendMessage($this->translate("afkmine.cmd.admin.usage.main", [], $sender));
                return true;
            }

            $sub = strtolower($args[0]);
            switch($sub){
                case "create":
                    if($this->creatorSessionManager->getSession($sender) !== null){
                        $sender->sendMessage($this->translate("afkmine.cmd.admin.create.already", [], $sender));
                        return true;
                    }
                    $this->creatorSessionManager->createSession($sender);
                    return true;

                case "list":
                    $mines = MineManager::getInstance()->getMines();
                    if($mines === []){
                        $sender->sendMessage($this->translate("afkmine.cmd.admin.list.empty", [], $sender));
                        return true;
                    }
                    $sender->sendMessage($this->translate(
                        "afkmine.cmd.admin.list.header",
                        ["0" => implode(", ", array_keys($mines))],
                        $sender
                    ));
                    return true;

                case "delete":
                    if(!isset($args[1])){
                        $sender->sendMessage($this->translate("afkmine.cmd.admin.delete.usage", [], $sender));
                        return true;
                    }
                    $name = $args[1];
                    if(!MineManager::getInstance()->deleteMine($name)){
                        $sender->sendMessage($this->translate("afkmine.cmd.admin.delete.notFound", ["0" => $name],
                            $sender));
                        return true;
                    }
                    $sender->sendMessage($this->translate("afkmine.cmd.admin.delete.success", ["0" => $name], $sender));
                    return true;

                default:
                    $sender->sendMessage($this->translate("afkmine.cmd.admin.usage.main", [], $sender));
                    return true;
            }
        }

        if($command->getName() === "afkmine"){
            $session = $this->afkSessionManager->getSession($sender);
            if($session !== null){
                $this->afkSessionManager->removeSession($session);
                return true;
            }
            $this->afkSessionManager->createSession($sender);
            return true;
        }

        return false;
    }

    public function getPluginConfig() : PluginConfig{
        return $this->pluginConfig;
    }

    /** Reloads config from disk and rebuilds PluginConfig. */
    public function reloadPluginConfig() : void{
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->pluginConfig = PluginConfig::fromConfig($this->getConfig());
    }

    public function getBlockPreview() : BlockPreview{
        return $this->blockPreview;
    }
}
