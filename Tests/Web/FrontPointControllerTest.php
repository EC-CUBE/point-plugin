<?php

namespace Eccube\Tests\Web;

use Eccube\Tests\Web\AbstractWebTestCase;

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
        $this->saveCustomerPoint($Customer, $currentPoint);

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
            array('form' =>
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
        $this->actual = $this->calculateCurrentPoint($Customer);
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

    /**
     * 会員の保有ポイントを返す.
     *
     * @see Plugin\Point\Event\WorkPlace\FrontShoppingComplete::calculateCurrentPoint()
     */
    protected function calculateCurrentPoint($Customer)
    {
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $Customer->getId()
        );
        $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $Customer->getId(),
            $orderIds
        );
        return $calculateCurrentPoint;
    }

    /**
     * 会員の保有ポイントを設定する.
     */
    protected function saveCustomerPoint($Customer, $currentPoint)
    {
        // 手動設定ポイントを登録
        $this->app['eccube.plugin.point.history.service']->addEntity($Customer);
        $this->app['eccube.plugin.point.history.service']->saveManualpoint($currentPoint);
        $point = array();
        $point['current'] = $currentPoint;
        $point['use'] = 0;
        $point['add'] = $currentPoint;

        // 手動設定ポイントのスナップショット登録
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($Customer);
        $this->app['eccube.plugin.point.history.service']->saveSnapShot($point);
        // 保有ポイントを登録
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $currentPoint,
            $Customer
        );
    }
}
