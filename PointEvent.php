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
namespace Plugin\Point;

use Eccube\Application;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use HttpException\NotFoundHttpException;
use Plugin\Point\Event\WorkPlace\AdminCustomer;
use Plugin\Point\Event\WorkPlace\AdminOrder;
use Plugin\Point\Event\WorkPlace\AdminOrderProgress;
use Plugin\Point\Event\WorkPlace\AdminProduct;
use Plugin\Point\Event\WorkPlace\FrontCart;
use Plugin\Point\Event\WorkPlace\FrontDelivery;
use Plugin\Point\Event\WorkPlace\FrontHistory;
use Plugin\Point\Event\WorkPlace\FrontMyPage;
use Plugin\Point\Event\WorkPlace\FrontPayment;
use Plugin\Point\Event\WorkPlace\FrontProductDetail;
use Plugin\Point\Event\WorkPlace\FrontShipping;
use Plugin\Point\Event\WorkPlace\FrontShopping;
use Plugin\Point\Event\WorkPlace\FrontShoppingComplete;
use Plugin\Point\Event\WorkPlace\ServiceMail;


/**
 * ポイントプラグインイベント処理ルーティングクラス
 * Class PointEvent
 * @package Plugin\Point
 */
class PointEvent
{
    /**
     * ヘルパー呼び出し用
     * 管理画面
     */
    const HELPER_ADMIN_PRODUCT = 'AdminProduct';
    const HELPER_ADMIN_CUSTOMER = 'AdminCustomer';
    const HELPER_ADMIN_ORDER = 'AdminOrder';
    const HELPER_ADMIN_ORDER_PROGRESS = 'AdminOrderProgress';

    /**
     * ヘルパー呼び出し用
     * 管理画面
     */
    const HELPER_FRONT_SHOPPING = 'FrontShopping';
    const HELPER_FRONT_SHOPPING_INDEX = 'FrontShoppingIndex';
    const HELPER_FRONT_SHOPPING_COMPLETE = 'FrontShoppingComplete';
    const HELPER_FRONT_MYPAGE = 'FrontMypage';
    const HELPER_FRONT_PRODUCT_DETAIL = 'FrontProductDetail';
    const HELPER_FRONT_CART = 'FrontCart';
    const HELPER_FRONT_HISTORY = 'FrontHistory';
    const HELPER_FRONT_DELIVERY = 'FrontDelivery';
    const HELPER_FRONT_PAYMENT = 'FrontPayment';
    const HELPER_FRONT_SHIPPING = 'FrontShipping';

    /**
     * ヘルパー呼び出し用
     * サービス
     */
    const HELPER_SERVICE_MAIL = 'ServiceMail';


    /** @var  \Eccube\Application $app */
    protected $app;
    /** @var null */
    protected $factory = null;
    /** @var null */
    protected $helper = null;

    /**
     * PointEvent constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 商品毎ポイント付与率
     *  - フォーム拡張処理
     *  - 管理画面 > 商品編集
     * @param EventArgs $event
     */
    public function onAdminProductEditInitialize(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 商品登録編集画面用/初期化 )
        //$this->setHelper(self::HELPER_ADMIN_PRODUCT);

        //フォーム拡張
        //$this->createForm($event);
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminProduct());
        $helper->createForm($event->getArgument('builder'), $this->app['request']);
    }

    /**
     * 商品毎ポイント付与率
     *  - 保存処理
     *  - 管理画面 > 商品編集
     * @param EventArgs $event
     */
    public function onAdminProductEditComplete(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 商品登録編集画面用/終了時 )
        //$this->setHelper(self::HELPER_ADMIN_PRODUCT);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminProduct());
        //$helper->createForm($event->getArgument('builder'), $this->app['request']);
        // ポイント付与率保存処理
        $helper->save($event);
    }

    /**
     * 会員保有ポイント
     *  - フォーム拡張処理
     *  - 管理画面 > 会員編集
     * @param EventArgs $event
     */
    public function onAdminCustomerEditIndexInitialize(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 会員登録編集画面用/初期化 )
        //$this->setHelper(self::HELPER_ADMIN_CUSTOMER);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminCustomer());
        $helper->createForm($event->getArgument('builder'), $this->app['request']);

        //フォーム拡張
        //$this->createForm($event);
    }

    /**
     * 会員保有ポイント
     *  - 保存処理
     *  - 管理画面 > 会員編集
     * @param EventArgs $event
     */
    public function onAdminCustomerEditIndexComplete(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 会員登録編集画面用/終了時 )
        //$this->setHelper(self::HELPER_ADMIN_CUSTOMER);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminCustomer());
        $helper->save($event);
        // ポイント付与率保存処理
        //$this->save($event);
    }

    /**
     * 受注ステータス登録・編集
     *  - フォーム項目追加
     *  - 管理画面 > 受注登録 ( 編集 )
     * @param EventArgs $event
     */
    public function onAdminOrderEditIndexInitialize(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 会員登録編集画面用/初期化 )
        //$this->setHelper(self::HELPER_ADMIN_ORDER);
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminOrder());
        $helper->createForm($event->getArgument('builder'), $this->app['request']);


        // ポイント付与率保存処理
        //$this->createForm($event);
    }

    /**
     * 受注ステータス変更時ポイント付与
     *  - 判定・更新処理
     *  - 更新処理前
     *  - 管理画面 > 受注登録 ( 編集 )
     * @param EventArgs $event
     */
    // @todo need delete
    public function onAdminOrderEditIndexProgress(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 会員登録編集画面用/終了 )
        //$this->setHelper(self::HELPER_ADMIN_ORDER_PROGRESS);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminOrderProgress());
        $helper->save($event);
        // ポイント付与率保存処理
        //$this->save($event);
    }

    /**
     * 受注ステータス変更時ポイント付与
     *  - 判定・更新処理
     *  - 管理画面 > 受注登録 ( 編集 )
     * @param EventArgs $event
     */
    public function onAdminOrderEditIndexComplete(EventArgs $event)
    {
        // フックポイント汎用処理サービス取得 ( 会員登録編集画面用/終了 )
        //$this->setHelper(self::HELPER_ADMIN_ORDER);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminOrder());
        $helper->save($event);

        // ポイント付与率保存処理
        //$this->save($event);
    }

    /**
     * 受注削除
     * @param EventArgs $event
     */
    public function onAdminOrderDeleteComplete(EventArgs $event)
    {
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminOrder());
        $helper->delete($event);
    }

    /**
     * 商品購入確認完了
     *  - 利用ポイント・保有ポイント・仮付与ポイント保存
     *  - フロント画面 > 商品購入確認完了
     * @param EventArgs $event
     * @return bool
     */
    public function onFrontShoppingConfirmProcessing(EventArgs $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント汎用処理サービス取得 ( 商品購入完了画面用 )
        //$this->setHelper(self::HELPER_FRONT_SHOPPING_COMPLETE);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontShoppingComplete());
        $helper->save($event);
        // ポイント関連保存処理
        //$this->save($event);
    }

    /**
     * 商品購入確認完了
     *  - 利用ポイント・保有ポイント・仮付与ポイントメール反映
     *  - フロント画面 > 商品購入完了
     * @param EventArgs $event
     * @return bool
     */
    public function onServiceShoppingNotifyComplete(EventArgs $event){
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント汎用処理サービス取得 ( 商品購入完了画面用 )
        //$this->setHelper(self::HELPER_FRONT_SHOPPING_COMPLETE);
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontShoppingComplete());
        $helper->save($event);

        // ポイント関連保存処理
        //$this->save($event);
    }

    /**
     * 配送関連処理
     *  - 配送関連変更時の合計金額判定処理
     * @param TemplateEvent $event
     * @return bool
     */
    public function Delivery(EventArgs $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント定形処理ヘルパー取得
        //$this->setHelper(self::HELPER_FRONT_DELIVERY);
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontDelivery());
        $helper->save($event);

        // 配送関連合計金額判定
        //$this->save($event);
    }

    /**
     * 支払い方法関連
     *  - 支払い方法関連変更時の合計金額判定処理
     * @param TemplateEvent $event
     * @return bool
     */
    public function Payment(EventArgs $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント定形処理ヘルパー取得
        //$this->setHelper(self::HELPER_FRONT_PAYMENT);
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontPayment());
        $helper->save($event);

        // 支払い合計金額判定
        //$this->save($event);
    }

    /**
     * 配送方法関連
     *  - 配送方法関連変更時の合計金額判定処理
     * @param TemplateEvent $event
     * @return bool
     */
    public function Shipping(EventArgs $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント定形処理ヘルパー取得
        //$this->setHelper(self::HELPER_FRONT_SHIPPING);
        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontShipping());
        $helper->save($event);

        // 配送先合計金額判定
        //$this->save($event);
    }

    /**
     * 商品購入確認画面
     *  - ポイント使用処理
     *  - 付与ポイント計算処理・画面描画処理
     *  - フロント画面 > 商品購入確認画面
     * @param TemplateEvent $event
     * @return bool
     */
    public function onRenderShoppingIndex(TemplateEvent $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント汎用処理サービス取得 ( 商品購入画面用 )
        //$this->setHelper(self::HELPER_FRONT_SHOPPING);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontShopping());
        $helper->createTwig($event);

        // Twig拡張(ポイント計算/合計金額計算・描画)
        //$this->createTwig($event);
    }


    /**
     * 管理画面受注編集
     *  - 利用ポイント・保有ポイント・付与ポイント表示
     *  - 管理画面 > 受注情報登録・編集
     * @param TemplateEvent $event
     */
    public function onRenderAdminOrderEdit(TemplateEvent $event)
    {
        //$args = $event->getParameters();

        // フックポイント定形処理ヘルパー取得 ( 商品購入完了 )
        //$this->setHelper(self::HELPER_ADMIN_ORDER);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new AdminOrder());
        $helper->createTwig($event);

        // ポイント関連保存処理
        //$this->createTwig($event);

    }

    /**     * マイページ
     *  - 利用ポイント・保有ポイント・仮付与ポイント保存
     * @param TemplateEvent $event
     * @return bool
     */
    public function onRenderMyPageIndex(TemplateEvent $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント定形処理ヘルパー取得 ( 商品購入完了 )
        //$this->setHelper(self::HELPER_FRONT_MYPAGE);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontMyPage());
        $helper->createTwig($event);

        // ポイント関連保存処理
        //$this->createTwig($event);
    }

    /**
     * 商品購入完了メール
     *  - ポイントの表示
     * @param EventArgs $event
     * @return bool
     */
    public function onMailOrderComplete(EventArgs $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント定形処理ヘルパー取得 ( 受注完了/終了 )
        //$this->setHelper(self::HELPER_SERVICE_MAIL);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new ServiceMail());
        $helper->save($event);

        // ポイント関連保存処理
        //$this->save($event);
    }

    /**
     * 商品詳細画面
     *  - 付与ポイント表示
     * @param TemplateEvent $event
     */
    public function onRenderProductDetail(TemplateEvent $event)
    {
        // フックポイント定形処理ヘルパー取得 ( 商品詳細 )
        //$this->setHelper(self::HELPER_FRONT_PRODUCT_DETAIL);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontProductDetail());
        $helper->createTwig($event);

        // ポイント関連保存処理
        //$this->createTwig($event);
    }

    /**
     * カート画面
     *  - 利用ポイント・保有ポイント・仮付与ポイント表示
     * @param TemplateEvent $event
     */
    public function onRenderCart(TemplateEvent $event)
    {
        // フックポイント定形処理ヘルパー取得 ( カート画面 )
        //$this->setHelper(self::HELPER_FRONT_CART);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontCart());
        $helper->createTwig($event);

        // ポイント関連保存処理
        //$this->createTwig($event);
    }

    /**
     * マイページ履歴画面
     *  - 利用ポイント・保有ポイント・仮付与ポイント表示
     * @param TemplateEvent $event
     * @return bool
     */
    public function onRenderHistory(TemplateEvent $event)
    {
        // ログイン判定
        if (!$this->isAuthRouteFront()) {
            return true;
        }

        // フックポイント定形処理ヘルパー取得 ( マイページ履歴 )
        //$this->setHelper(self::HELPER_FRONT_HISTORY);

        $helper = $this->app['eccube.plugin.point.hookpoint.routinework'](new FrontHistory());
        $helper->createTwig($event);

        // ポイント関連保存処理
        //$this->createTwig($event);
    }


    /**
     * 管理画面権確認
     * @throws NotFoundHttpException
     */
    protected function isAuthRouteAdmin()
    {
        // 権限判定
        if (!$this->app->isGranted('ROLE_ADMIN') && !$this->app->isGranted('ROLE_USER')) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        return;
    }

    /**
     * フロント画面権限確認
     * @return bool
     */
    protected function isAuthRouteFront()
    {
        // 権限判定
        if (!$this->app->isGranted('IS_AUTHENTICATED_FULLY')) {
            return false;
        }

        return true;
    }
}
