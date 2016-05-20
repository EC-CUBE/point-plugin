<?php

namespace Eccube\Tests\Web;

use Eccube\Tests\Web\AbstractWebTestCase;
use Plugin\Point\Tests\Util\PointTestUtil;
use Plugin\Point\Event\WorkPlace\AdminOrder;

/**
 * @see ShoppingControllerTest
 */
class FrontPointControllerTest extends AbstractWebTestCase
{

    protected $Customer;

    public function setUp()
    {
        parent::setUp();
        $this->BaseInfo = $this->app['eccube.repository.base_info']->get();
        $this->Customer = $this->createCustomer();
        $this->initializeMailCatcher();
    }

    public function tearDown()
    {
        $this->cleanUpMailCatcherMessages();
        parent::tearDown();
    }

    /**
     * カート画面のテストケース.
     */
    public function testPointCart()
    {
        $currentPoint = 1000000;

        $faker = $this->getFaker();
        $Customer = $this->logIn();
        $client = $this->client;

        // 保有ポイントを設定する
        PointTestUtil::saveCustomerPoint($Customer, $currentPoint, $this->app);

        // カート画面
        $this->scenarioCartIn($client);

        $crawler = $client->request('GET', '/cart');
        $this->assertRegExp('/現在の保有ポイントは「'.number_format($currentPoint).'pt」です。/u', $crawler->filter('#cart_item__point_info')->text());
    }

    /**
     * ポイントを使用しないテストケース.
     */
    public function testPointShopping()
    {
        $faker = $this->getFaker();
        $Customer = $this->logIn();
        $client = $this->client;

        // カート画面
        $this->scenarioCartIn($client);

        // 確認画面
        $crawler = $this->scenarioConfirm($client);
        $this->expected = 'ご注文内容のご確認';
        $this->actual = $crawler->filter('h1.page-heading')->text();
        $this->verify();

        // 完了画面
        $crawler = $this->scenarioComplete($client, $this->app->path('shopping_confirm'));
        $this->assertTrue($client->getResponse()->isRedirect($this->app->url('shopping_complete')));

        $Messages = $this->getMailCatcherMessages();
        $Message = $this->getMailCatcherMessage($Messages[0]->id);

        $this->expected = '[' . $this->BaseInfo->getShopName() . '] ご注文ありがとうございます';
        $this->actual = $Message->subject;
        $this->verify();

        $body = $this->parseMailCatcherSource($Message);
        $this->assertRegexp('/利用ポイント：0 pt/u', $body);
        $this->assertRegexp('/加算ポイント：1,100 pt/u', $body);

        // 生成された受注のチェック
        $Order = $this->app['eccube.repository.order']->findOneBy(
            array(
                'Customer' => $Customer
            )
        );

        $this->expected = $Customer->getName01();
        $this->actual = $Order->getName01();
        $this->verify();

        $Points = $this->app['eccube.plugin.point.repository.point']->findBy(array(
            'Customer' => $Customer,
            'Order' => $Order
        ));
        $provisionalPoint = array_reduce(
            array_map(
                function ($Point) {
                    return $Point->getPlgDynamicPoint();
                }, $Points
            ),
            function ($carry, $item) {
                return $carry += $item;
            }
        );
        $this->expected = 1100;
        $this->actual = $provisionalPoint;
        $this->verify('仮ポイントの合計は '.$this->expected);
    }

    /**
     * ポイント利用のテストケース.
     */
    public function testUsePointShopping()
    {
        $currentPoint = 1000;   // 保有ポイント
        $usePoint = 100;        // 利用ポイント

        // ポイント確定ステータスを「発送済み」に設定
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        $PointInfo->setPlgAddPointStatus($this->app['config']['order_deliv']);
        $this->app['orm.em']->flush();

        $faker = $this->getFaker();
        $Customer = $this->logIn();
        $client = $this->client;

        // 保有ポイントを設定する
        PointTestUtil::saveCustomerPoint($Customer, $currentPoint, $this->app);

        // カート画面
        $this->scenarioCartIn($client);

        // 確認画面
        $crawler = $this->scenarioConfirm($client);
        $this->expected = 'ご注文内容のご確認';
        $this->actual = $crawler->filter('h1.page-heading')->text();
        $this->verify();

        // ポイント利用画面
        $crawler = $client->request('GET', $this->app->path('point_use'));
        $this->assertRegexp(
            '/現在の保有ポイントは「'.number_format($currentPoint).' pt」です。/u',
            $crawler->filter('#detail_box')->text()
        );

        // ポイント利用処理
        $crawler = $client->request(
            'POST',
            $this->app->path('point_use'),
            array('front_point_use' =>
                  array(
                      'plg_use_point' => $usePoint,
                      '_token' => 'dummy'
                  )
            )
        );
        $this->assertTrue($client->getResponse()->isRedirect($this->app->url('shopping')));

        // 完了画面
        $crawler = $this->scenarioComplete($client, $this->app->path('shopping_confirm'));
        $this->assertTrue($client->getResponse()->isRedirect($this->app->url('shopping_complete')));

        $Messages = $this->getMailCatcherMessages();
        $Message = $this->getMailCatcherMessage($Messages[0]->id);

        $this->expected = '[' . $this->BaseInfo->getShopName() . '] ご注文ありがとうございます';
        $this->actual = $Message->subject;
        $this->verify();

        $body = $this->parseMailCatcherSource($Message);

        $this->assertRegexp('/利用ポイント：'.$usePoint.' pt/u', $body);
        $this->assertRegexp('/加算ポイント：1,100 pt/u', $body);

        $this->expected = $currentPoint - $usePoint;
        $this->actual = PointTestUtil::calculateCurrentPoint($Customer, $this->app);
        $this->verify('保有ポイントの合計は '.$this->expected);
    }

    /**
     * ポイント利用のテストケース.
     *
     * 長期間利用した際のシナリオ
     * 1. 50回購入
     * 2. 1 のうち、25回ポイント確定
     * 3. 新たに 50回購入し、各100ポイントずつ利用する
     * 4. 2で確定しなかったポイントを確定
     */
    public function testLongUsePointShopping()
    {
        $addPoint = 1100;       // 1回の受注で加算されるポイント
        $purchaseNum = 50;     // 購入回数
        $usePoint = 100;        // 利用ポイント

        // ポイント確定ステータスを「発送済み」に設定
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        $PointInfo->setPlgAddPointStatus($this->app['config']['order_deliv']);
        $this->app['orm.em']->flush();

        $Customer = $this->logIn();
        $client = $this->client;

        for ($i = 0; $i < $purchaseNum; $i++) {
            // カート画面
            $this->scenarioCartIn($client);
            // 確認画面
            $crawler = $this->scenarioConfirm($client);
            // 完了画面
            $crawler = $this->scenarioComplete($client, $this->app->path('shopping_confirm'));
        }

        $Orders = $this->app['eccube.repository.order']->findBy(array('Customer' => $Customer));
        $this->expected = $purchaseNum;
        $this->actual = count($Orders);
        $this->verify('購入回数は'.$purchaseNum.'回');

        $this->expected = 0;
        $this->actual = PointTestUtil::calculateCurrentPoint($Customer, $this->app);
        $this->verify('保有ポイントの合計は '.$this->expected);
        $OrderDeliv = $this->app['eccube.repository.order_status']->find($this->app['config']['order_deliv']);

        // 半分だけ受注ステータスを発送済みに更新
        $i = 0;
        $deliveryNum = 0;
        $orderNewIds = array(); // 新規受付の order_id
        foreach ($Orders as $Order) {
            if (($i % 2) === 0) {
                $Order->setOrderStatus($OrderDeliv);
                $this->app['orm.em']->flush($Order);

                $deliveryNum++;

                // protected なのでリフレクションで AdminOrder::fixPoint をコールする
                $AdminOrder = new AdminOrder();
                $Reflect = new \ReflectionClass($AdminOrder);
                $Method = $Reflect->getMethod('fixPoint');
                $Method->setAccessible(true);
                $Method->invoke($AdminOrder, $Order, $Customer);
            } else {
                $orderNewIds[] = $Order->getId();
            }
            $i++;
        }

        $this->expected = $addPoint * $deliveryNum;
        $this->actual = PointTestUtil::calculateCurrentPoint($Customer, $this->app);
        $this->verify('保有ポイントの合計は '.$this->expected);
        $currentPoint = $this->actual;

        for ($i = 0; $i < $purchaseNum; $i++) {
            // カート画面
            $this->scenarioCartIn($client);
            // 確認画面
            $crawler = $this->scenarioConfirm($client);
            // ポイント利用処理
            $crawler = $client->request(
                'POST',
                $this->app->path('point_use'),
                array('front_point_use' =>
                      array(
                          'plg_use_point' => $usePoint,
                          '_token' => 'dummy'
                      )
                )
            );
            // 完了画面
            $crawler = $this->scenarioComplete($client, $this->app->path('shopping_confirm'));
        }

        $this->expected = $currentPoint - ($usePoint * $purchaseNum);
        $this->actual = PointTestUtil::calculateCurrentPoint($Customer, $this->app);
        $this->verify('保有ポイントの合計は '.$this->expected);
        $currentPoint = $this->expected;

        $deliveryNum2 = 0;
        foreach ($orderNewIds as $order_id) {
            $NewOrder = $this->app['eccube.repository.order']->find($order_id);
            $NewOrder->setOrderStatus($OrderDeliv);
            $this->app['orm.em']->flush($NewOrder);

            $deliveryNum2++;

            // protected なのでリフレクションで AdminOrder::fixPoint をコールする
            $AdminOrder = new AdminOrder();
            $Reflect = new \ReflectionClass($AdminOrder);
            $Method = $Reflect->getMethod('fixPoint');
            $Method->setAccessible(true);
            $Method->invoke($AdminOrder, $NewOrder, $Customer);
        }

        $this->expected = $currentPoint + ($addPoint * $deliveryNum2);
        $this->actual = PointTestUtil::calculateCurrentPoint($Customer, $this->app);
        $this->verify('保有ポイントの合計は '.$this->expected);
    }

    protected function scenarioCartIn($client, $product_class_id = 1)
    {
        $crawler = $client->request('POST', '/cart/add', array('product_class_id' => $product_class_id));
        $this->app['eccube.service.cart']->lock();
        return $crawler;
    }

    protected function scenarioInput($client, $formData)
    {
        $crawler = $client->request(
            'POST',
            $this->app->path('shopping_nonmember'),
            array('nonmember' => $formData)
        );
        $this->app['eccube.service.cart']->lock();
        return $crawler;
    }

    protected function scenarioConfirm($client)
    {
        $crawler = $client->request('GET', $this->app->path('shopping'));
        return $crawler;
    }

    protected function scenarioComplete($client, $confirm_url)
    {
        $faker = $this->getFaker();

        $crawler = $client->request(
            'POST',
            $confirm_url,
            array('shopping' =>
                  array(
                      'shippings' =>
                      array(0 =>
                            array(
                                'delivery' => 1,
                                'deliveryTime' => 1
                            ),
                      ),
                      'payment' => 1,
                      'message' => $faker->text(),
                      '_token' => 'dummy'
                  )
            )
        );

        return $crawler;
    }
}
