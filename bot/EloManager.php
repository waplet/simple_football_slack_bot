<?php

namespace w\Bot;

class EloManager
{
    /**
     * K-Factor
     * @var int
     */
    const K = 32;

    /**
     * @param int $rankingA current ranking of A team
     * @param int $rankingB current ranking of B team
     * @param bool $aWon
     * @return int
     */
    public static function getElo($rankingA, $rankingB, $aWon)
    {
        $ratingA = self::getTransformedRating($rankingA);
        $ratingB = self::getTransformedRating($rankingB);

        $S = self::getActualScore($aWon);
        $E = self::getExpectedRating(
            $aWon ? $ratingA : $ratingB,
            $aWon ? $ratingB : $ratingA
        );

        return $ratingA + self::K * ($S - $E);
    }

    /**
     * @param int $rankingA current ranking of A team
     * @param int $rankingB current ranking of B team
     * @param bool $aWon
     * @return int
     */
    public static function getTempElo($rankingA, $rankingB, $aWon)
    {
        return $aWon
            ? $rankingA - self::getElo($rankingA, $rankingB, true)
            : $rankingB - self::getElo($rankingA, $rankingB, false);
    }

    /**
     * @param int $currentRating
     * @return int
     */
    private static function getTransformedRating($currentRating)
    {
        return (int)10 ^ ($currentRating / 400.0);
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