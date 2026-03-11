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

namespace kim\present\afkmine;

use kim\present\afkmine\config\PluginConfig;
use kim\present\afkmine\data\MineManager;
use kim\present\afkmine\listener\EventListener;
use kim\present\afkmine\session\AFKSession;
use kim\present\afkmine\session\CreatorSession;
use kim\present\libmultilingual\traits\MultilingualConfigTrait;
use kim\present\libmultilingual\traits\MultilingualPluginModifiableTrait;
use kim\present\utils\selectionvisualize\BlockPreview;
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

    /** @var CreatorSession[] */
    private array $creatorSessions = [];

    /** @var AFKSession[] */
    private array $afkSessions = [];

    private PluginConfig $pluginConfig;
    private BlockPreview $blockPreview;

    protected function onLoad() : void{
        self::setInstance($this);
    }

    protected function onEnable() : void{
        $this->reloadPluginConfig();
        $this->blockPreview = new BlockPreview($this);
        new MineManager($this);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            MineManager::getInstance()->tick();
            foreach($this->afkSessions as $session){
                $session->tick();
            }
        }), 1);
    }

    protected function onDisable() : void{
        foreach($this->creatorSessions as $creatorSession){
            $creatorSession->stop();
        }
        $this->creatorSessions = [];

        foreach($this->afkSessions as $afkSession){
            $afkSession->stop();
        }
        $this->afkSessions = [];
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
                    if(isset($this->creatorSessions[$sender->getName()])){
                        $sender->sendMessage($this->translate("afkmine.cmd.admin.create.already", [], $sender));
                        return true;
                    }
                    $this->creatorSessions[$sender->getName()] = new CreatorSession($this, $sender);
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
                        $sender->sendMessage($this->translate("afkmine.cmd.admin.delete.notFound", ["0" => $name], $sender));
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
            if(isset($this->afkSessions[$sender->getName()])){
                $this->removeAFKSession($sender);
                return true;
            }
            $session = new AFKSession($this, $sender);
            if($session->start()){
                $this->afkSessions[$sender->getName()] = $session;
            }
            return true;
        }

        return false;
    }

    public function getCreatorSession(Player $player) : ?CreatorSession{
        return $this->creatorSessions[$player->getName()] ?? null;
    }

    public function removeCreatorSession(Player $player) : void{
        if(isset($this->creatorSessions[$player->getName()])){
            $this->creatorSessions[$player->getName()]->stop();
            unset($this->creatorSessions[$player->getName()]);
        }
    }

    public function getAFKSession(Player $player) : ?AFKSession{
        return $this->afkSessions[$player->getName()] ?? null;
    }

    public function removeAFKSession(Player $player) : void{
        if(isset($this->afkSessions[$player->getName()])){
            $this->afkSessions[$player->getName()]->stop();
            unset($this->afkSessions[$player->getName()]);
        }
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
