<?php

declare(strict_types=1);

namespace kim\present\afkmine\tests;

use kim\present\afkmine\utils\ArrayUtils;
use PHPUnit\Framework\TestCase;

final class ArrayUtilsTest extends TestCase{

    public function testShuffleKeepsElementsAndReindexes() : void{
        $array = ["a" => 1, "b" => 2, 7 => 3, 9 => 4, 10 => 5];
        $result = ArrayUtils::fisherYatesShuffle($array);

        self::assertSame($array, $result);
        self::assertSame(range(0, 4), array_keys($array));
        $values = $array;
        sort($values);
        self::assertSame([1, 2, 3, 4, 5], $values);
    }

    public function testShuffleSmallArrays() : void{
        $empty = [];
        self::assertSame([], ArrayUtils::fisherYatesShuffle($empty));

        $single = ["x" => 1];
        self::assertSame([1], ArrayUtils::fisherYatesShuffle($single));
    }
}
