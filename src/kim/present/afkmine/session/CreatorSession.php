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

use kim\present\afkmine\data\MineData;
use kim\present\afkmine\data\MineManager;
use kim\present\afkmine\data\OrePresetRegistry;
use kim\present\afkmine\data\OreSpotEntry;
use kim\present\afkmine\form\CustomForm;
use kim\present\afkmine\Main;
use kim\present\cameraapi\Camera;
use kim\present\cameraapi\marker\CameraMarker;
use kim\present\cameraapi\session\CameraSession;
use kim\present\utils\playsound\VanillaPlaySounds as Sounds;
use kim\present\utils\selectionvisualize\PreviewEntry;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

class CreatorSession{

    private const NBT_KEY = "AfkMineCreationMode";
    private const NBT_PRESET_KEY = "AfkMinePresetId";

    private const MODE_ORE_PRESET = 1;
    private const MODE_SPAWN = 2;
    private const MODE_HIDE = 3;
    private const MODE_CAMERA = 4;
    private const MODE_SAVE = 5;

    private Main $plugin;
    private Player $player;
    /** @var Item[] */
    private array $backupInventory = [];

    public MineData $tempData;

    /** @var CameraMarker[] */
    private array $cameraMarkers = [];

    public function __construct(Main $plugin, Player $player){
        $this->plugin = $plugin;
        $this->player = $player;
        $this->tempData = new MineData("", $player->getWorld()->getFolderName());
        $this->start();
    }

    private function start() : void{
        $this->backupInventory = $this->player->getInventory()->getContents();
        $this->player->getInventory()->clearAll();

        $inv = $this->player->getInventory();
        $registry = OrePresetRegistry::getInstance();
        $defaultIds = $registry->getDefaultIds();

        foreach([0, 1, 2] as $index){
            $presetId = $defaultIds[$index] ?? null;
            if($presetId === null){
                break;
            }
            $colors = ["§7", "§c", "§8"];
            $prefix = $colors[$index];
            $preset = $registry->get($presetId);
            $name = $preset !== null
                ? $preset->getName(Main::getInstance()->getTranslator(), $this->player)
                : $presetId;
            $label = $this->plugin->translate(
                "afkmine.create.ore.itemName",
                ["0" => $name],
                $this->player
            );
            $item = $registry->getPreviewOre($presetId)->asItem()
                             ->setCustomName("§r" . $prefix . $label);
            $nbt = $item->getNamedTag();
            $nbt->setInt(self::NBT_KEY, self::MODE_ORE_PRESET);
            $nbt->setString(self::NBT_PRESET_KEY, $presetId);
            $item->setNamedTag($nbt);
            $inv->setItem($index, $item);
        }

        $inv->setItem(4, self::tagItem(
            VanillaBlocks::COPPER_LANTERN()->asItem()->setCustomName(
                $this->plugin->translate("afkmine.create.item.spawn", [], $this->player)
            ),
            self::MODE_SPAWN
        ));
        $inv->setItem(5, self::tagItem(
            VanillaBlocks::MOB_HEAD()
                         ->setMobHeadType(MobHeadType::WITHER_SKELETON)
                         ->asItem()
                         ->setCustomName(
                             $this->plugin->translate("afkmine.create.item.hide", [], $this->player)
                         ),
            self::MODE_HIDE
        ));
        $inv->setItem(6, self::tagItem(
            VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(
                $this->plugin->translate("afkmine.create.item.camera", [], $this->player)
            ),
            self::MODE_CAMERA
        ));
        $inv->setItem(8, self::tagItem(
            VanillaBlocks::BARRIER()->asItem()->setCustomName(
                $this->plugin->translate("afkmine.create.item.save", [], $this->player)
            ),
            self::MODE_SAVE
        ));

        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.banner", [], $this->player)
        );
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.guide.touchItems", [], $this->player)
        );
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.guide.oreSpots", [], $this->player)
        );
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.guide.camera", [], $this->player)
        );

        Sounds::OPEN_SHUTTER()->send($this->player);
        $this->refreshBlockPreview();
    }

    /**
     * Builds BlockPreview entries from current tempData and shows them to the creator.
     */
    private function refreshBlockPreview() : void{
        $world = $this->player->getWorld();
        $entries = [];

        foreach($this->tempData->oreSpots as $entry){
            $entries[] = new PreviewEntry(
                Position::fromObject($entry->vec, $world),
                $entry->preset->getPreviewOre(),
                $entry->preset->getPreviewGlass()
            );
        }

        if($this->tempData->spawnPos !== null){
            $entries[] = new PreviewEntry(
                Position::fromObject($this->tempData->spawnPos, $world),
                VanillaBlocks::COPPER_LANTERN(),
                VanillaBlocks::STAINED_HARDENED_GLASS()->setColor(DyeColor::YELLOW())
            );
        }

        if($this->tempData->hidePos !== null){
            $entries[] = new PreviewEntry(
                Position::fromObject($this->tempData->hidePos, $world),
                VanillaBlocks::MOB_HEAD(),
                VanillaBlocks::STAINED_HARDENED_GLASS()->setColor(DyeColor::LIME())
            );
        }

        $this->plugin->getBlockPreview()->show($this->player, ...$entries);
    }

    public function handleInteract(Item $item, Vector3 $pos) : void{
        $mode = $item->getNamedTag()->getInt(self::NBT_KEY, -1);
        if($mode === -1){
            return;
        }

        switch($mode){
            case self::MODE_ORE_PRESET:
                $this->handleOrePreset($item, $pos);
                break;
            case self::MODE_SPAWN:
                $this->handleSpawn($pos);
                break;
            case self::MODE_HIDE:
                $this->handleHide($pos);
                break;
        }
    }

    public function handleItemUse(Item $item) : void{
        $mode = $item->getNamedTag()->getInt(self::NBT_KEY, -1);
        if($mode === -1){
            return;
        }

        switch($mode){
            case self::MODE_CAMERA:
                $this->handleCamera();
                break;
            case self::MODE_SAVE:
                $this->attemptSave();
                break;
        }
    }

    private function handleOrePreset(Item $item, Vector3 $pos) : void{
        $presetId = $item->getNamedTag()->getString(self::NBT_PRESET_KEY, "");
        $registry = OrePresetRegistry::getInstance();
        if($presetId === "" || !$registry->isValid($presetId)){
            return;
        }

        $blockHash = World::blockHash((int) $pos->x, (int) $pos->y, (int) $pos->z);
        if(isset($this->tempData->oreSpots[$blockHash])
            && $this->tempData->oreSpots[$blockHash]->preset->getId() === $presetId){
            unset(
                $this->tempData->oreSpots[$blockHash],
                $this->tempData->originalBlockStates[$blockHash]
            );
        }else{
            $preset = $registry->get($presetId);
            if($preset !== null){
                $this->tempData->oreSpots[$blockHash] = new OreSpotEntry($pos, $preset);
                if(!isset($this->tempData->originalBlockStates[$blockHash])){
                    $original = $this->player->getWorld()->getBlock($pos);
                    $this->tempData->originalBlockStates[$blockHash] = $original->getStateId();
                }
            }
        }

        Sounds::VAULT_PLACE()->send($this->player);
        $this->refreshBlockPreview();
    }

    private function handleSpawn(Vector3 $pos) : void{
        $this->tempData->spawnPos = $pos->add(0.5, 1, 0.5);
        Sounds::VAULT_REJECT_REWARDED_PLAYER()->setPitch(1.75)->send($this->player);
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.spawnSet", [], $this->player)
        );
        $this->refreshBlockPreview();
    }

    private function handleHide(Vector3 $pos) : void{
        $this->tempData->hidePos = $pos->add(0.5, 1, 0.5);
        Sounds::PLACE_COPPER_BULB()->send($this->player);
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.hideSet", [], $this->player)
        );
        $this->refreshBlockPreview();
    }

    private function handleCamera() : void{
        $location = $this->player->getLocation();
        $location->y += $this->player->getEyeHeight();
        $cameraPos = new Vector3($location->x, $location->y, $location->z);

        $this->tempData->cameraPoints[] = $cameraPos;

        $index = array_key_last($this->tempData->cameraPoints);
        $label = "Camera #" . ($index + 1);

        $marker = Camera::spawnMarker($location, $label)
                        ->setInteractButton(
                            $this->plugin->translate("afkmine.create.camera.previewButton", [], $this->player)
                        )
                        ->setOnClick(function(CameraMarker $marker, Player $player) : void{
                            Sounds::SOUL_ESCAPE()->send($this->player);
                            Camera::timeline()
                                  ->add(fn(CameraSession $session) => $marker->applyToSession($session))
                                  ->wait(2.0)
                                  ->clear()
                                  ->play($player);
                        })
                        ->setOnAttack(function(CameraMarker $marker, Player $player) use ($index) : void{
                            $marker->remove();
                            unset($this->cameraMarkers[$index], $this->tempData->cameraPoints[$index]);

                            Sounds::REMOVE_ONE()->send($this->player);
                            $player->sendMessage(
                                $this->plugin->translate("afkmine.create.camera.removed", [], $player)
                            );
                        });

        $this->cameraMarkers[$index] = $marker;

        Sounds::SCREENSHOT()->setPitch(1.5)->setVolume(0.3)->send($this->player);
        $this->player->sendMessage(
            $this->plugin->translate(
                "afkmine.create.camera.added",
                ["0" => (string) ($index + 1)],
                $this->player
            )
        );
    }

    /**
     * Handles block break during creation: if the broken block is a registered
     * ore spot, removes it from tempData and refreshes the preview.
     *
     * @return bool True if the block was an ore spot and was removed.
     */
    public function handleBlockBreak(Vector3 $pos) : bool{
        $blockHash = World::blockHash((int) $pos->x, (int) $pos->y, (int) $pos->z);

        if(isset($this->tempData->oreSpots[$blockHash])){
            unset($this->tempData->oreSpots[$blockHash]);
            Sounds::VAULT_BREAK()->send($this->player);
            $this->refreshBlockPreview();
            return true;
        }
        return false;
    }

    private function attemptSave() : void{
        if(!$this->tempData->isValid()){
            $lines = [
                $this->plugin->translate("afkmine.create.save.invalid.header", [], $this->player)
            ];
            if($this->tempData->spawnPos === null){
                $lines[] = $this->plugin->translate("afkmine.create.save.invalid.noSpawn", [], $this->player);
            }
            if($this->tempData->hidePos === null){
                $lines[] = $this->plugin->translate("afkmine.create.save.invalid.noHide", [], $this->player);
            }
            if($this->tempData->oreSpots === []){
                $lines[] = $this->plugin->translate("afkmine.create.save.invalid.noOre", [], $this->player);
            }

            Sounds::FALSE_PERMISSIONS()->send($this->player);
            $this->player->sendMessage(implode("\n", $lines));
            return;
        }

        $form = new CustomForm($this->plugin->translate("afkmine.create.form.title", [], $this->player), [
            [
                "type" => "input",
                "text" => $this->plugin->translate("afkmine.create.form.name", [], $this->player),
                "placeholder" => $this->plugin->translate("afkmine.create.form.name.placeholder", [], $this->player)
            ],
            [
                "type" => "slider",
                "text" => $this->plugin->translate("afkmine.create.form.regenInterval", [], $this->player),
                "min" => 1,
                "max" => 60,
                "default" => 20
            ]
        ], function(Player $p, $data){
            if($data === null){
                return;
            }
            $name = $data[0];
            if($name === ''){
                $p->sendMessage(
                    $this->plugin->translate("afkmine.create.form.nameRequired", [], $p)
                );
                return;
            }

            $this->tempData->name = $name;
            $this->tempData->regenInterval = (int) ($data[1] ?? 20);

            MineManager::getInstance()->saveMine($this->tempData);
            Sounds::CARTOGRAPHY_TABLE_TAKE_RESULT()->send($p);
            $p->sendMessage(
                $this->plugin->translate("afkmine.create.form.saved", ["0" => $name], $p)
            );

            Main::getInstance()->removeCreatorSession($this->player);
        });

        $this->player->sendForm($form);
    }

    public function stop() : void{
        $this->plugin->getBlockPreview()->clear($this->player);
        foreach($this->cameraMarkers as $marker){
            $marker->remove();
        }
        $this->cameraMarkers = [];
        $this->player->getInventory()->setContents($this->backupInventory);
    }

    private static function tagItem(Item $item, int $mode) : Item{
        $nbt = $item->getNamedTag();
        return $item->setNamedTag($nbt->setInt(self::NBT_KEY, $mode));
    }
}
