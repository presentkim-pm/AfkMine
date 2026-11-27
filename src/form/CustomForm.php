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

namespace kim\present\afkmine\form;

use pocketmine\form\Form as IForm;
use pocketmine\player\Player;

class CustomForm implements IForm{
    /** @var array<string, mixed> */
    private array $data;
    /** @var callable */
    private $onSubmit;

    /** @param array<int, mixed> $elements */
    public function __construct(string $title, array $elements, callable $onSubmit){
        $this->data = ["type" => "custom_form", "title" => $title, "content" => $elements];
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
