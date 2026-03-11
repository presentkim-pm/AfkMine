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

namespace kim\present\afkmine\form;

use pocketmine\form\Form as IForm;
use pocketmine\player\Player;

class SimpleForm implements IForm{
    /** @var array<string, mixed> */
    private array $data;
    /** @var callable */
    private $onSubmit;

    /** @param array<int|string, string> $buttons */
    public function __construct(string $title, string $content, array $buttons, callable $onSubmit){
        $this->data = ["type" => "form", "title" => $title, "content" => $content, "buttons" => []];
        foreach($buttons as $btn){
            $this->data["buttons"][] = ["text" => $btn];
        }
        $this->onSubmit = $onSubmit;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize() : array{
        return $this->data;
    }

    public function handleResponse(Player $player, $data) : void{
        ($this->onSubmit)($player, $data);
    }
}
