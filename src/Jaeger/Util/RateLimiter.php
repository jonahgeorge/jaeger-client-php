<?php

namespace Jaeger\Util;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class RateLimiter
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var CacheItemInterface
     */
    private $balance;

    /**
     * @var CacheItemInterface
     */
    private $lastTick;

    /**
     * @var float
     */
    private $creditsPerNanosecond = 0;

    /**
     * @var float
     */
    private $maxBalance = 0;

    /**
     * RateLimiter constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param string $currentBalanceKey key of current balance value in $cache
     * @param string $lastTickKey key of last tick value in $cache
     * @throws \Psr\Cache\InvalidArgumentException
     */
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

        $currentTick = hrtime(true);
        $elapsedTime = $currentTick - $lastTick;
        $balance += $elapsedTime * $this->creditsPerNanosecond;
        if ($balance > $this->maxBalance) {
            $balance = $this->maxBalance;
        }

        $result = false;
        if ($balance >= $itemCost) {
            $balance -= $itemCost;
            $result = true;
        }

        $this->saveState($currentTick, $balance);

        return $result;
    }


    /**
     * Initializes limiter costs and boundaries
     *
     * @param float $creditsPerNanosecond
     * @param float $maxBalance
     */
    public function initialize(float $creditsPerNanosecond, float $maxBalance)
    {
        $this->creditsPerNanosecond = $creditsPerNanosecond;
        $this->maxBalance = $maxBalance;
    }

    /**
     * Method loads last tick and current balance from cache
     *
     * @return array [$lastTick, $balance]
     */
    private function getState() : array
    {
        return [
            $this->lastTick->get(),
            $this->balance->get()
        ];
    }

    /**
     * Method saves last tick and current balance into cache
     *
     * @param integer $lastTick
     * @param float $balance
     */
    private function saveState($lastTick, $balance)
    {
        $this->lastTick->set($lastTick);
        $this->balance->set($balance);
        $this->cache->saveDeferred($this->lastTick);
        $this->cache->saveDeferred($this->balance);
        $this->cache->commit();
    }
}
