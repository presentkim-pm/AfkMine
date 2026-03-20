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
use kim\present\playerbackup\PlayerStateBackupRegistry;
use kim\present\utils\playsound\VanillaPlaySounds as Sounds;
use kim\present\utils\selectionvisualize\PreviewEntry;
use kim\present\utils\session\AbstractSession;
use kim\present\utils\session\listener\attribute\SessionEventHandler;
use kim\present\utils\session\SessionManager;
use kim\present\utils\session\SessionTerminateReasons;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * Session that manages the interactive mine creation workflow for a single player.
 *
 * Activated when an operator enters mine creation mode. Replaces the player's
 * inventory with creation tools, tracks temporary mine configuration via
 * {@link MineData}, and terminates with {@link SessionTerminateReasons::COMPLETED}
 * once the operator confirms and saves the mine.
 *
 * Lifecycle:
 * - {@link onStart()}: Backs up inventory, equips creation tools, shows preview.
 * - {@link onTerminate()}: Restores inventory, clears block preview and camera markers.
 *
 * Event handlers (auto-wired via {@link SessionEventHandler}):
 * - {@link onBlockBreak()}: Removes ore spots by breaking their blocks.
 * - {@link onInteract()}: Places ore spots, spawn point, or hide point by right-clicking.
 * - {@link onItemUse()}: Adds camera points or triggers save flow via held item.
 */
final class CreatorSession extends AbstractSession{

    /**
     * NBT key used to tag creation tool items.
     * Stores the tool mode constant ({@see MODE_*}) as an int.
     */
    private const NBT_KEY = "AfkMineCreationMode";

    /**
     * NBT key used to store the ore preset ID on ore tool items.
     * Only present on items with {@link MODE_ORE_PRESET}.
     */
    private const NBT_PRESET_KEY = "AfkMinePresetId";

    /** Tool mode: place or remove an ore spot using the selected preset. */
    private const MODE_ORE_PRESET = 1;

    /** Tool mode: set the player spawn position for the mine. */
    private const MODE_SPAWN = 2;

    /** Tool mode: set the hide position for the mine. */
    private const MODE_HIDE = 3;

    /** Tool mode: add a camera point at the player's current eye position. */
    private const MODE_CAMERA = 4;

    /** Tool mode: validate and open the save form. */
    private const MODE_SAVE = 5;

    private Main $plugin;

    /** Manages backup and restoration of the player's inventory around the session. */
    private PlayerStateBackupRegistry $backupRegistry;

    /**
     * Mutable mine configuration being built during this session.
     * Saved to {@link MineManager} when the operator confirms.
     */
    public MineData $tempData;

    /**
     * Camera markers spawned for preview purposes, keyed by camera point index.
     * Removed on termination or when the operator attacks a marker.
     *
     * @var array<int, CameraMarker>
     */
    private array $cameraMarkers = [];

    /**
     * @param SessionManager $manager The manager that owns this session.
     * @param Player         $player  The operator entering creation mode.
     */
    public function __construct(SessionManager $manager, Player $player){
        parent::__construct($manager, $player);
        $this->plugin = Main::getInstance();
        $this->backupRegistry = new PlayerStateBackupRegistry();
        $this->tempData = new MineData("", $player->getWorld()->getFolderName());
    }

    /**
     * Backs up the player's inventory, equips creation tools, and shows the initial block preview.
     *
     * Tools placed in the hotbar:
     * - Slots 0–2: Ore preset items (up to 3 default presets)
     * - Slot 4: Spawn point setter
     * - Slot 5: Hide point setter
     * - Slot 6: Camera point adder
     * - Slot 8: Save / confirm
     */
    protected function onStart() : void{
        $this->backupRegistry->captureInventoryOnly($this->player);
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
     * Restores the player's inventory and cleans up all session artifacts.
     *
     * Removes all spawned {@link CameraMarker} instances and clears the block preview.
     *
     * @param string $reason Termination reason. See {@link SessionTerminateReasons}.
     */
    protected function onTerminate(string $reason) : void{
        $player = $this->getPlayer();
        $this->plugin->getBlockPreview()->clear($player);
        $this->backupRegistry->restoreAndForget($player);

        foreach($this->cameraMarkers as $marker){
            $marker->remove();
        }
        $this->cameraMarkers = [];
        $this->backupRegistry->clear();
    }

    /**
     * Removes an ore spot when the player breaks its block.
     *
     * If the broken block is registered as an ore spot in {@link $tempData},
     * the spot is removed and the block preview is refreshed. The break event
     * is cancelled to prevent the block from actually being destroyed.
     */
    #[SessionEventHandler(BlockBreakEvent::class)]
    public function onBlockBreak(BlockBreakEvent $event) : void{
        $pos = $event->getBlock()->getPosition();
        $blockHash = World::blockHash((int) $pos->x, (int) $pos->y, (int) $pos->z);

        if(!isset($this->tempData->oreSpots[$blockHash])){
            return;
        }

        unset($this->tempData->oreSpots[$blockHash]);
        Sounds::VAULT_BREAK()->send($this->player);
        $this->refreshBlockPreview();
        $event->cancel();
    }

    /**
     * Handles right-click interactions with creation tools.
     *
     * Reads the {@link NBT_KEY} mode tag from the held item and delegates to
     * the appropriate handler:
     * - {@link MODE_ORE_PRESET}: {@link handleOrePreset()} — toggle ore spot at clicked block
     * - {@link MODE_SPAWN}: {@link handleSpawn()} — set mine spawn position
     * - {@link MODE_HIDE}: {@link handleHide()} — set mine hide position
     *
     * Non-tagged items and non-right-click actions are ignored.
     * The interact event is always cancelled when a tagged item is held.
     */
    #[SessionEventHandler(PlayerInteractEvent::class)]
    public function onInteract(PlayerInteractEvent $event) : void{
        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            return;
        }

        $item = $event->getItem();
        $mode = $item->getNamedTag()->getInt(self::NBT_KEY, -1);
        if($mode === -1){
            return;
        }

        $pos = $event->getBlock()->getPosition();
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
        $event->cancel();
    }

    /**
     * Toggles an ore spot at the given position for the selected preset.
     *
     * If a spot with the same preset already exists at the position, it is removed.
     * Otherwise a new {@link OreSpotEntry} is registered and the original block state
     * is captured for later restoration. Refreshes the block preview after any change.
     *
     * @param Item    $item The ore preset tool held by the player.
     * @param Vector3 $pos  The clicked block position.
     */
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

    /**
     * Sets the mine spawn position to one block above the clicked position (centered).
     *
     * @param Vector3 $pos The clicked block position.
     */
    private function handleSpawn(Vector3 $pos) : void{
        $this->tempData->spawnPos = $pos->add(0.5, 1, 0.5);
        Sounds::VAULT_REJECT_REWARDED_PLAYER()->setPitch(1.75)->send($this->player);
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.spawnSet", [], $this->player)
        );
        $this->refreshBlockPreview();
    }

    /**
     * Sets the mine hide position to one block above the clicked position (centered).
     *
     * @param Vector3 $pos The clicked block position.
     */
    private function handleHide(Vector3 $pos) : void{
        $this->tempData->hidePos = $pos->add(0.5, 1, 0.5);
        Sounds::PLACE_COPPER_BULB()->send($this->player);
        $this->player->sendMessage(
            $this->plugin->translate("afkmine.create.hideSet", [], $this->player)
        );
        $this->refreshBlockPreview();
    }

    /**
     * Captures the player's current eye position as a new camera point.
     *
     * Spawns a {@link CameraMarker} at the point with:
     * - A preview button that plays a 2-second camera timeline from that position.
     * - An attack handler that removes the marker and its associated camera point.
     *
     * Camera points are appended to {@link MineData::$cameraPoints} and indexed
     * in {@link $cameraMarkers} for cleanup on termination.
     */
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
     * Handles item-use events for camera and save tools.
     *
     * Reads the {@link NBT_KEY} mode tag from the used item and delegates to:
     * - {@link MODE_CAMERA}: {@link handleCamera()} — capture current eye position as a camera point
     * - {@link MODE_SAVE}: {@link attemptSave()} — validate and open the save form
     *
     * Non-tagged items are ignored.
     * The use event is always cancelled when a tagged item is used.
     */
    #[SessionEventHandler(PlayerItemUseEvent::class)]
    public function onItemUse(PlayerItemUseEvent $event) : void{
        $item = $event->getItem();
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
        $event->cancel();
    }

    /**
     * Rebuilds and sends the block preview from the current {@link $tempData} state.
     *
     * Renders ore spots, spawn position, and hide position as colored glass overlays
     * using the block preview subsystem. Called after any change to {@link $tempData}.
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

    /**
     * Validates the current {@link $tempData} and opens the save form if valid.
     *
     * If validation fails, lists the missing fields (spawn, hide, ore spots) and
     * plays a rejection sound. On form submission, saves the mine via
     * {@link MineManager::saveMine()} and terminates this session with
     * {@link SessionTerminateReasons::COMPLETED}.
     */
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

            $this->terminate(SessionTerminateReasons::COMPLETED);
        });

        $this->player->sendForm($form);
    }

    /**
     * Tags an item with the given creation tool mode for later identification in event handlers.
     *
     * @param Item $item The item to tag.
     * @param int  $mode One of the {@see MODE_*} constants.
     *
     * @return Item The same item instance with the NBT tag applied.
     */
    private static function tagItem(Item $item, int $mode) : Item{
        $nbt = $item->getNamedTag();
        return $item->setNamedTag($nbt->setInt(self::NBT_KEY, $mode));
    }
}
