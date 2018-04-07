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
    public static function getElo($rankingA, $rankingB, $aWon)
    {
        $ratingA = self::getTransformedRating($rankingA);
        $ratingB = self::getTransformedRating($rankingB);

        $S = self::getActualScore($aWon);
        $E = self::getExpectedRating(
            $aWon ? $ratingA : $ratingB,
            $aWon ? $ratingB : $ratingA
        );

        return (int)(($aWon ? $rankingA : $rankingB) + self::K * ($S - $E));
    }

    /**
     * Temp Elo is just a diff from current Elo to new elo
     * @param int $rankingA current ranking of A team
     * @param int $rankingB current ranking of B team
     * @param bool $aWon
     * @return int
     */
    public static function getTempElo($rankingA, $rankingB, $aWon)
    {
        return $aWon
            ? self::getElo($rankingA, $rankingB, true) - $rankingA
            : self::getElo($rankingA, $rankingB, false) - $rankingB;
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