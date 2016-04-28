<?php

namespace Eccube\Tests\Helper\PointCalculateHelper;

use Eccube\Application;
use Eccube\Tests\EccubeTestCase;
use Plugin\Point\Entity\PointInfo;

/**
 * Class PointCalculateHelperTest
 *
 * @package Eccube\Tests\Helper\PointCalculateHelper
 */
class PointCalculateHelperTest extends EccubeTestCase
{
//    public function testGetProvisionalAddPoint()
//    {
//    }
//
//    public function testGetProvisionalAddPointByOrder()
//    {
//
//    }
//    public function testGetAddPointByCart()
//    {
//
//    }
//
//    public function testGetAddPointByOrder()
//    {
//
//    }
//
//    public function testGetAddPointByProduct()
//    {
//
//    }

    /**
     * 端数計算のテスト
     */
    public function testGetRoundValue()
    {
        $calculater = $this->app['eccube.plugin.point.calculate.helper.factory'];
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        // 切り上げ
        $PointInfo->setPlgRoundType(PointInfo::POINT_ROUND_CEIL);
        $this->expected = 11;
        $this->actual = $calculater->getRoundValue(10.5);
        $this->verify();
        $this->actual = $calculater->getRoundValue(10.4);
        $this->verify();
        $this->actual = $calculater->getRoundValue(10.05);
        $this->verify();
        $this->actual = $calculater->getRoundValue(10.04);
        $this->verify();

        // 切り捨て
        $PointInfo->setPlgRoundType(PointInfo::POINT_ROUND_FLOOR);
        $this->expected = 10;
        $this->actual = $calculater->getRoundValue(10.5);
        $this->verify();
        $this->actual = $calculater->getRoundValue(10.4);
        $this->verify();
        $this->actual = $calculater->getRoundValue(10.05);
        $this->verify();
        $this->actual = $calculater->getRoundValue(10.04);
        $this->verify();

        // 四捨五入(少数点第一位を四捨五入する
        $PointInfo->setPlgRoundType(PointInfo::POINT_ROUND_ROUND);
        $this->expected = 11;
        $this->actual = $calculater->getRoundValue(10.5);
        $this->verify();
        $this->expected = 10;
        $this->actual = $calculater->getRoundValue(10.4);
        $this->verify();
        $this->expected = 10;
        $this->actual = $calculater->getRoundValue(10.05);
        $this->verify();
        $this->expected = 10;
        $this->actual = $calculater->getRoundValue(10.04);
        $this->verify();
    }
}
