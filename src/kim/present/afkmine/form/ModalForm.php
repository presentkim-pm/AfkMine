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

use pocketmine\player\Player;

class ModalForm extends Form{
    /** @var callable */
    private $onSubmit;

    public function __construct(string $title, string $content, string $yesButton, string $noButton, callable $onSubmit
    ){
        $this->data["type"] = "modal";
        $this->data["title"] = $title;
        $this->data["content"] = $content;
        $this->data["button1"] = $yesButton;
        $this->data["button2"] = $noButton;
        $this->onSubmit = $onSubmit;
    }

    public function handleResponse(Player $player, $data) : void{
        ($this->onSubmit)($player, $data);
    }
}
