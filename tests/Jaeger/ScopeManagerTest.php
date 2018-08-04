<?php

namespace Jaeger\Tests;

use Jaeger\ScopeManager;
use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class ScopeManagerTest extends TestCase
{
    /**
     * @var ScopeManager
     */
    private $scopeManager;

    function setUp()
    {
        $this->scopeManager = new ScopeManager();
    }

    function testActivate()
    {
        $span = $this->createMock(Span::class);

        $scope = $this->scopeManager->activate($span, true);

        $this->assertEquals($scope->getSpan(), $span);
    }

    function testAbleGetActiveScope()
    {
        $span = $this->createMock(Span::class);

        $this->assertNull($this->scopeManager->getActive());
        $scope = $this->scopeManager->activate($span, false);

        $this->assertEquals($scope, $this->scopeManager->getActive());
    }

    function testScopeClosingDeactivates()
    {
        $span = $this->createMock(Span::class);

        $scope = $this->scopeManager->activate($span, false);
        $scope->close();

        $this->assertNull($this->scopeManager->getActive());
    }
}
