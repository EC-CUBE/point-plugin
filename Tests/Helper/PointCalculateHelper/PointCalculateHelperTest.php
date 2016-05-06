<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2016 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
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
            array(1, 1, 0, 0, 0, 0),
            array(1, 1, 1, 0, 0, 0),
            array(1, 1, 2, 0, 0, 0),
            array(1, 1, 0, 0, 50, 50),
            array(1, 1, 1, 0, 50, 50),
            array(1, 1, 2, 0, 50, 50),
            array(1, 1, 0, 50, 0, 0),
            array(1, 1, 1, 50, 0, 0),
            array(1, 1, 2, 50, 0, 0),
            array(1, 1, 0, 1, 50, 48),
            array(1, 1, 1, 1, 50, 49),
            array(1, 1, 2, 1, 50, 49),
            array(1, 1, 0, 49, 50, 0),
            array(1, 1, 1, 49, 50, 1),
            array(1, 1, 2, 49, 50, 1),
            array(1, 1, 0, 50, 50, 0),
            array(1, 1, 1, 50, 50, 0),
            array(1, 1, 2, 50, 50, 0),
            array(5, 5, 0, 0, 0, 0),
            array(5, 5, 1, 0, 0, 0),
            array(5, 5, 2, 0, 0, 0),
            array(5, 5, 0, 0, 50, 50),
            array(5, 5, 1, 0, 50, 50),
            array(5, 5, 2, 0, 50, 50),
            array(5, 5, 0, 50, 0, 0),
            array(5, 5, 1, 50, 0, 0),
            array(5, 5, 2, 50, 0, 0),
            array(5, 5, 0, 1, 50, 44),
            array(5, 5, 1, 1, 50, 45),
            array(5, 5, 2, 1, 50, 45),
            array(5, 5, 0, 49, 50, 0),
            array(5, 5, 1, 49, 50, 0),
            array(5, 5, 2, 49, 50, 0),
            array(5, 5, 0, 50, 50, 0),
            array(5, 5, 1, 50, 50, 0),
            array(5, 5, 2, 50, 50, 0)
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
            0 => array(1, 1, 0, 0, 1, null, 5000, 1, 50),
            1 => array(1, 1, 1, 0, 1, null, 5000, 1, 50),
            2 => array(1, 1, 2, 0, 1, null, 5000, 1, 50),
            3 => array(1, 1, 0, 50, 1, null, 5000, 1, 50),
            4 => array(1, 1, 1, 50, 1, null, 5000, 1, 50),
            5 => array(1, 1, 2, 50, 1, null, 5000, 1, 50),
            6 => array(1, 1, 0, 0, 0, null, 5000, 1, 50),
            7 => array(1, 1, 1, 0, 0, null, 5000, 1, 50),
            8 => array(1, 1, 2, 0, 0, null, 5000, 1, 50),
            9 => array(1, 1, 0, 50, 0, null, 5000, 1, 0),
            10 => array(1, 1, 1, 50, 0, null, 5000, 1, 0),
            11 => array(1, 1, 2, 50, 0, null, 5000, 1, 0),
            12 => array(1, 1, 0, 0, 1, 0, 5000, 1, 0),
            13 => array(1, 1, 1, 0, 1, 0, 5000, 1, 0),
            14 => array(1, 1, 2, 0, 1, 0, 5000, 1, 0),
            15 => array(1, 1, 0, 50, 1, 0, 5000, 1, 0),
            16 => array(1, 1, 1, 50, 1, 0, 5000, 1, 0),
            17 => array(1, 1, 2, 50, 1, 0, 5000, 1, 0),
            18 => array(1, 1, 0, 0, 0, 0, 5000, 1, 0),
            19 => array(1, 1, 1, 0, 0, 0, 5000, 1, 0),
            20 => array(1, 1, 2, 0, 0, 0, 5000, 1, 0),
            21 => array(1, 1, 0, 50, 0, 0, 5000, 1, 0),
            22 => array(1, 1, 1, 50, 0, 0, 5000, 1, 0),
            23 => array(1, 1, 2, 50, 0, 0, 5000, 1, 0),
            24 => array(1, 1, 0, 0, 1, 1, 5000, 1, 50),
            25 => array(1, 1, 1, 0, 1, 1, 5000, 1, 50),
            26 => array(1, 1, 2, 0, 1, 1, 5000, 1, 50),
            27 => array(1, 1, 0, 50, 1, 1, 5000, 1, 50),
            28 => array(1, 1, 1, 50, 1, 1, 5000, 1, 50),
            29 => array(1, 1, 2, 50, 1, 1, 5000, 1, 50),
            30 => array(1, 1, 0, 0, 0, 1, 5000, 1, 50),
            31 => array(1, 1, 1, 0, 1, 1, 5000, 1, 50),
            32 => array(1, 1, 2, 0, 1, 1, 5000, 1, 50),
            33 => array(1, 1, 0, 50, 0, 1, 5000, 1, 0),
            34 => array(1, 1, 1, 50, 0, 1, 5000, 1, 0),
            35 => array(1, 1, 2, 50, 0, 1, 5000, 1, 0),
            36 => array(5, 5, 0, 0, 1, null, 5000, 1, 250),
            37 => array(5, 5, 1, 0, 1, null, 5000, 1, 250),
            38 => array(5, 5, 2, 0, 1, null, 5000, 1, 250),
            39 => array(5, 5, 0, 50, 1, null, 5000, 1, 250),
            40 => array(5, 5, 1, 50, 1, null, 5000, 1, 250),
            41 => array(5, 5, 2, 50, 1, null, 5000, 1, 250),
            42 => array(5, 5, 0, 0, 0, null, 5000, 1, 250),
            43 => array(5, 5, 1, 0, 0, null, 5000, 1, 250),
            44 => array(5, 5, 2, 0, 0, null, 5000, 1, 250),
            45 => array(5, 5, 0, 50, 0, null, 5000, 1, 0),
            46 => array(5, 5, 1, 50, 0, null, 5000, 1, 0),
            47 => array(5, 5, 2, 50, 0, null, 5000, 1, 0),
            48 => array(5, 5, 0, 0, 1, 0, 5000, 1, 0),
            49 => array(5, 5, 1, 0, 1, 0, 5000, 1, 0),
            50 => array(5, 5, 2, 0, 1, 0, 5000, 1, 0),
            51 => array(5, 5, 0, 50, 1, 0, 5000, 1, 0),
            52 => array(5, 5, 1, 50, 1, 0, 5000, 1, 0),
            53 => array(5, 5, 2, 50, 1, 0, 5000, 1, 0),
            54 => array(5, 5, 0, 0, 0, 0, 5000, 1, 0),
            55 => array(5, 5, 1, 0, 0, 0, 5000, 1, 0),
            56 => array(5, 5, 2, 0, 0, 0, 5000, 1, 0),
            57 => array(5, 5, 0, 50, 0, 0, 5000, 1, 0),
            58 => array(5, 5, 1, 50, 0, 0, 5000, 1, 0),
            59 => array(5, 5, 2, 50, 0, 0, 5000, 1, 0),
            60 => array(5, 5, 0, 0, 1, 1, 5000, 1, 50),
            61 => array(5, 5, 1, 0, 1, 1, 5000, 1, 50),
            62 => array(5, 5, 2, 0, 1, 1, 5000, 1, 50),
            63 => array(5, 5, 0, 50, 1, 1, 5000, 1, 50),
            64 => array(5, 5, 1, 50, 1, 1, 5000, 1, 50),
            65 => array(5, 5, 2, 50, 1, 1, 5000, 1, 50),
            66 => array(5, 5, 0, 0, 0, 1, 5000, 1, 50),
            67 => array(5, 5, 1, 0, 0, 1, 5000, 1, 50),
            68 => array(5, 5, 2, 0, 0, 1, 5000, 1, 50),
            69 => array(5, 5, 0, 50, 0, 1, 5000, 1, 0),
            70 => array(5, 5, 1, 50, 0, 1, 5000, 1, 0),
            71 => array(5, 5, 2, 50, 0, 1, 5000, 1, 0),
        );

        // テストデータ生成
        $Customer = $this->createCustomer();
        $Order = $this->createOrder($Customer);

        /** @var $calculater \Plugin\Point\Helper\PointCalculateHelper\PointCalculateHelper **/
        $calculater = $this->app['eccube.plugin.point.calculate.helper.factory'];
        /** @var $PointInfo \Plugin\Point\Entity\PointInfo **/
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        $calculater->addEntity('Order', $Order);

        foreach ($testData as $i => $data) {
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

    public function testGetAddPointByCart()
    {
        $testData = array(
            /**
             * - 基本ポイント付与率
             * - 端数計算方法
             * - 商品毎ポイント付与率
             * - 商品価格
             * - 商品個数
             * - 期待値
             */
            0 => array(1, 0, null, 50, 1, 0),
            1 => array(1, 1, null, 50, 1, 1),
            2 => array(1, 2, null, 50, 1, 1),
            3 => array(1, 0, 5, 50, 1, 2),
            4 => array(1, 1, 5, 50, 1, 3),
            5 => array(1, 2, 5, 50, 1, 3),
        );

        $Product = $this->createProduct();
        $ProductClasses = $Product->getProductClasses();
        $ProductClass = $ProductClasses[0];

        /** @var $calculater \Plugin\Point\Helper\PointCalculateHelper\PointCalculateHelper **/
        $calculater = $this->app['eccube.plugin.point.calculate.helper.factory'];
        /** @var $PointInfo \Plugin\Point\Entity\PointInfo **/
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        $calculater->addEntity('Cart', $this->app['eccube.service.cart']->getCart());

        foreach ($testData as $i => $data) {
            // 基本ポイント付与率
            $PointInfo->setPlgBasicPointRate($data[0]);
            // 端数計算方法
            $PointInfo->setPlgRoundType($data[1]);

            // 商品ごとポイント付与率
            $this->app['eccube.plugin.point.repository.pointproductrate']->savePointProductRate($data[2], $Product);
            // 商品価格
            $ProductClass->setPrice02($data[3]);

            // 商品個数
            $this->app['eccube.service.cart']->clear();
            $this->app['eccube.service.cart']->setProductQuantity($ProductClass, $data[4]);
            $this->app['eccube.service.cart']->save();

            $Cart = $this->app['session']->get('cart');
            $CartItems = $Cart->getCartItems();
            foreach ($CartItems as $item) {
                $item->setObject($ProductClass);
            }
            $calculater->addEntity('Cart', $Cart);

            $this->expected = $data[5];
            $this->actual = $calculater->getAddPointByCart();
            $this->verify('index ' . $i . ' failed.');
        }
    }

    public function testGetAddPointByProduct()
    {
        $testData = array(
            /**
             * - 基本ポイント付与率
             * - 端数計算方法
             * - 商品毎ポイント付与率
             * - 商品価格(最小)
             * - 商品価格(最大)
             * - 期待値(最小)
             * - 期待値(最大)
             */
            array(1, 0, null, 50, 490, 0, 4),
            array(1, 1, null, 50, 490, 1, 5),
            array(1, 2, null, 50, 490, 1, 5),
            array(1, 0, 5, 50, 490, 2, 24),
            array(1, 1, 5, 50, 490, 3, 25),
            array(1, 2, 5, 50, 490, 3, 25),
        );

        $Product = $this->createProduct('test', 2);
        $ProductClasses = $Product->getProductClasses();
        $ProductClass = $ProductClasses[0];

        /** @var $calculater \Plugin\Point\Helper\PointCalculateHelper\PointCalculateHelper **/
        $calculater = $this->app['eccube.plugin.point.calculate.helper.factory'];
        /** @var $PointInfo \Plugin\Point\Entity\PointInfo **/
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        $calculater->addEntity('Product', $Product);

        $max = count($testData);
        for ($i = 0; $i < $max; $i++) {
            $data = $testData[$i];

            // 基本ポイント付与率
            $PointInfo->setPlgBasicPointRate($data[0]);
            // 端数計算方法
            $PointInfo->setPlgRoundType($data[1]);

            // 商品ごとポイント付与率
            $this->app['eccube.plugin.point.repository.pointproductrate']->savePointProductRate($data[2], $Product);
            // 商品価格
            $ProductClasses[0]->setPrice02($data[3]);
            $ProductClasses[1]->setPrice02($data[4]);

            $point = $calculater->getAddPointByProduct();

            // min
            $this->expected = $data[5];
            $this->actual = $point['min'];
            $this->verify('index ' . $i . ' min failed.');

            // max
            $this->expected = $data[6];
            $this->actual = $point['max'];
            $this->verify('index ' . $i . ' max failed.');
        }
    }
}
