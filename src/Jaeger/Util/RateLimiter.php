<?php

namespace Jaeger\Util;

use Psr\Cache\CacheItemPoolInterface;

class RateLimiter
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var float
     */
    private $balance;

    /**
     * @var integer
     */
    private $lastTick;

    /**
     * @var int
     */
    private $creditsPerNanosecond = 0;

    /**
     * @var float
     */
    private $maxBalance = 0;

    public function __construct(
        CacheItemPoolInterface $cache,
        string $currentBalanceKey,
        string $lastTickKey
    ) {
        $this->cache = $cache;
        $this->balance = $this->cache->getItem($currentBalanceKey);
        $this->lastTick = $this->cache->getItem($lastTickKey);
    }

    /**
     * @param $itemCost
     * @return bool
     */
    public function checkCredit($itemCost)
    {
        if (!$this->creditsPerNanosecond) {
            return false;
        }

        list($lastTick, $balance) = $this->getState();

        if (!$lastTick) {
            $this->saveState(hrtime(true), 0);
            return true;
        }

        $currentTime = hrtime(true);
        $elapsedTime = $currentTime - $lastTick;
        $balance += $elapsedTime * $this->creditsPerNanosecond;

        if ($balance > $this->maxBalance) {
            $balance = $this->maxBalance;
        }

        $result = false;
        if ($balance >= $itemCost) {
            $balance -= $itemCost;
            $result = true;
        }

        $this->saveState($currentTime, $balance);

        return $result;
    }

    /**
     * @param int $creditsPerNanosecond
     */
    public function setCreditsPerNanosecond($creditsPerNanosecond)
    {
        $this->creditsPerNanosecond = $creditsPerNanosecond;
    }

    /**
     * @param float $maxBalance
     */
    public function setMaxBalance(float $maxBalance)
    {
        $this->maxBalance = $maxBalance;
    }

    private function getState()
    {
        return [
            $this->lastTick->get(),
            $this->balance->get()
        ];
    }

    private function saveState($lastTick, $balance)
    {
        $this->lastTick->set($lastTick);
        $this->balance->set($balance);
        $this->cache->saveDeferred($this->lastTick);
        $this->cache->saveDeferred($this->balance);
        $this->cache->commit();
    }
}
