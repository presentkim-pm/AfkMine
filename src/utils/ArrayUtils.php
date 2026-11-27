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

namespace kim\present\afkmine\utils;

use Random\RandomException;

final class ArrayUtils{

    /**
     * Fisher-Yates shuffle (shuffles array in place).
     * Pass a copy if you need to preserve the original.
     * Re-indexes to 0..n-1 so return type has int keys.
     *
     * @template T of mixed
     * @param array<int|string, T> $arr Array to shuffle (by reference)
     *
     * @return array<int, T> Shuffled array with contiguous int keys
     */
    public static function fisherYatesShuffle(array &$arr) : array{
        // Re-index to ensure we have 0..n-1 contiguous numeric keys.
        $arr = array_values($arr);
        $length = count($arr);

        if($length < 2){
            return $arr;
        }

        for($i = $length - 1; $i > 0; $i--){
            try{
                $j = random_int(0, $i);
            }catch(RandomException){
                $j = mt_rand(0, $i);
            }

            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }

        return $arr;
    }
}
