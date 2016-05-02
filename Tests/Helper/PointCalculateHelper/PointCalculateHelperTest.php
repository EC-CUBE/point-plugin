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
//    public function testGetAddPointByCart()
//    {
//
//    }
//

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

    /**
     * ポイント利用時の加算ポイント減算処理のテスト
     */
    public function testGetSubtractionCalculate()
    {
        $testData = array(
            array(1, 1, 0, 1, 50, 48),
            array(1, 1, 1, 1, 50, 49),
            array(1, 1, 1, 1, 50, 49),
        );

        /** @var $calculater \Plugin\Point\Helper\PointCalculateHelper\PointCalculateHelper **/
        $calculater = $this->app['eccube.plugin.point.calculate.helper.factory'];
        /** @var $PointInfo \Plugin\Point\Entity\PointInfo **/
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        $PointInfo->setPlgCalculationType(PointInfo::POINT_CALCULATE_SUBTRACTION);

        $i = 0;
        $max = count($testData);
        for ($i = 0; $i < $max; $i++)  {
            $data = $testData[$i];

            // 基本ポイント付与率
            $PointInfo->setPlgBasicPointRate($data[0]);
            // ポイント換算レート
            $PointInfo->setPlgPointConversionRate($data[1]);
            // 端数計算方法
            $PointInfo->setPlgRoundType($data[2]);
            // 利用ポイント
            $calculater->setUsePoint($data[3]);
            // 加算ポイント
            $calculater->setAddPoint($data[4]);
            // 期待値
            $this->expected = ($data[5]);
            $this->actual = $calculater->getSubtractionCalculate();
            $this->verify('index ' . $i . ' failed.');
        }
    }

    public function testGetAddPointByOrder()
    {
        $testData = array(
            /**
             * - 基本ポイント付与率
             * - ポイント換算レート
             * - 端数計算方法
             * - ポイント利用
             * - ポイント減算方式
             * - 商品毎ポイント付与率
             * - 商品価格
             * - 商品個数
             * - 期待値
             */
            array(1, 1, 0, 0, 1, null, 5000, 1, 50),
            array(1, 1, 0, 50, 0, 1, 5000, 1, 0)
        );

        // テストデータ生成
        $Customer = $this->createCustomer();
        $Order = $this->createOrder($Customer);

        /** @var $calculater \Plugin\Point\Helper\PointCalculateHelper\PointCalculateHelper **/
        $calculater = $this->app['eccube.plugin.point.calculate.helper.factory'];
        /** @var $PointInfo \Plugin\Point\Entity\PointInfo **/
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        $calculater->addEntity('Order', $Order);

        $max = count($testData);
        for ($i = 0; $i < $max; $i++) {
            $data = $testData[$i];
            // 基本ポイント付与率
            $PointInfo->setPlgBasicPointRate($data[0]);
            // ポイント換算レート
            $PointInfo->setPlgPointConversionRate($data[1]);
            // 端数計算方法
            $PointInfo->setPlgRoundType($data[2]);
            // 利用ポイント
            $calculater->setUsePoint($data[3]);
            // ポイント減算方式
            $PointInfo->setPlgCalculationType($data[4]);

            foreach ($Order->getOrderDetails() as $OrderDetail) {
                $ProductClass = $OrderDetail->getProductClass();
                $Product = $ProductClass->getProduct();
                // 商品ごとポイント付与率
                $this->app['eccube.plugin.point.repository.pointproductrate']->savePointProductRate($data[5], $Product);
                // 商品価格
                $ProductClass->setPrice02($data[6]);
                // 商品個数
                $OrderDetail->setQuantity($data[7]);
            }

            $this->expected = $data[8];
            $this->actual = $calculater->getAddPointByOrder();
            $this->verify('index ' . $i . ' failed.');
        }
    }
}
