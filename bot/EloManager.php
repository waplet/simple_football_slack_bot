<?php

namespace w\Bot;

class EloManager
{
    /**
     * K-Factor
     * @var int
     */
    const K = 16; // Default is 32, but as we account for 2 games, we divide by 2

    /**
     * @param int $rankingA current ranking of A team
     * @param int $rankingB current ranking of B team
     * @param bool $aWon
     * @return int
     */
    public static function getTempElo($rankingA, $rankingB, $aWon)
    {
        $ratingA = self::getTransformedRating($rankingA);
        $ratingB = self::getTransformedRating($rankingB);

        $S = self::getActualScore($aWon);
        $E = self::getExpectedRating($ratingA, $ratingB);

        return (int)(self::K * ($S - $E));
    }

    /**
     * @param int $currentRating
     * @return int
     */
    private static function getTransformedRating($currentRating)
    {
        return (int)pow(10, ($currentRating / 400.0));
    }

    /**
     * @param int $firstRating
     * @param int $secondRating
     * @return int
     */
    private static function getExpectedRating($firstRating, $secondRating)
    {
        return $firstRating  / ($firstRating + $secondRating);
    }

    /**
     * @param bool $forTeamA
     * @return int
     */
    private static function getActualScore($forTeamA)
    {
        return $forTeamA ? 1 : 0;
    }
}