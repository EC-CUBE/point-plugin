<?php

namespace Plugin\Point\ServiceProvider;

use Plugin\Point\Doctrine\Listener\ORMListener;
use Plugin\Point\Helper\EventRoutineWorksHelper\EventRoutineWorksHelper;
use Plugin\Point\Helper\EventRoutineWorksHelper\EventRoutineWorksHelperFactory;
use Plugin\Point\Helper\PointCalculateHelper\PointCalculateHelper;
use Plugin\Point\Helper\PointHistoryHelper\PointHistoryHelper;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

/**
 * Class PointServiceProvider
 * @package Plugin\Point\ServiceProvider
 */
class PointServiceProvider implements ServiceProviderInterface
{
    /**
     * サービス登録処理
     * @param BaseApplication $app
     */
    public function register(BaseApplication $app)
    {
        /**
         * ルーティング登録
         * 管理画面 > 設定 > 基本情報設定 > ポイント基本情報設定画面
         */
        $app->match(
            '/'.$app['config']['admin_route'].'/point/setting',
            'Plugin\Point\Controller\AdminPointController::index'
        )->bind('point_info');

        /**
         * ルーティング登録
         * フロント画面 > 商品購入確認画面
         */
        $app->match(
            '/shopping/use_point',
            'Plugin\Point\Controller\FrontPointController::usePoint'
        )->bind('point_use');

        /**
         * レポジトリ登録
         */
        $app['eccube.plugin.point.repository.point'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\Point');
            }
        );

        /** ポイントステータステーブル用リポジトリ */
        $app['eccube.plugin.point.repository.pointstatus'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\PointStatus');
            }
        );

        /** ポイント機能基本情報テーブル用リポジトリ */
        $app['eccube.plugin.point.repository.pointinfo'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\PointInfo');
            }
        );

        /** ポイント付与タイミング受注ステータス保存テーブル用リポジトリ */
        $app['eccube.plugin.point.repository.pointinfo.addstatus'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\PointInfoAddStatus');
            }
        );

        /** ポイント会員情報テーブル */
        $app['eccube.plugin.point.repository.pointcustomer'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\PointCustomer');
            }
        );

        /** ポイント機能商品付与率テーブル */
        $app['eccube.plugin.point.repository.pointproductrate'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\PointProductRate');
            }
        );

        /** ポイント機能スナップショットテーブル */
        $app['eccube.plugin.point.repository.pointsnapshot'] = $app->share(
            function () use ($app) {
                return $app['orm.em']->getRepository('Plugin\Point\Entity\PointSnapshot');
            }
        );

        /**
         * フォームタイプ登録
         */
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new \Plugin\Point\Form\Type\PointInfoType($app);

            return $types;
            })
        );

        /**
         * メニュー登録
         */
        $app['config'] = $app->share(
            $app->extend(
                'config',
                function ($config) {
                    $addNavi['id'] = "point_info";
                    $addNavi['name'] = "ポイント管理";
                    $addNavi['url'] = "point_info";
                    $nav = $config['nav'];
                    foreach ($nav as $key => $val) {
                        if ("setting" == $val["id"]) {
                            $nav[$key]['child'][0]['child'][] = $addNavi;
                        }
                    }
                    $config['nav'] = $nav;

                    return $config;
                }
            )
        );

        /**
         * フックポイントイベント定型処理ヘルパーファクトリー登録
         */
        $app['eccube.plugin.point.hookpoint.routinework'] = $app->protect(function ($class) {
            return new EventRoutineWorksHelper($class);
        });

        /**
         * ポイント計算処理サービスファクトリー登録
         */
        $app['eccube.plugin.point.calculate.helper.factory'] = $app->share(
            function () use ($app) {
                return new PointCalculateHelper($app);
            }
        );

        /**
         * ポイント履歴ヘルパー登録
         */
        $app['eccube.plugin.point.history.service'] = $app->share(
            function () {
                return new PointHistoryHelper();
            }
        );

        /**
         * メッセージ登録
         */
        $app['translator'] = $app->share(
            $app->extend(
                'translator',
                function ($translator, \Silex\Application $app) {
                    $translator->addLoader('yaml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
                    $file = __DIR__.'/../Resource/locale/message.'.$app['locale'].'.yml';
                    if (file_exists($file)) {
                        $translator->addResource('yaml', $file, $app['locale']);
                    }

                    return $translator;
                }
            )
        );
    }

    /**
     * 初期化時処理
     *  - 本クラスでは使用せず
     * @param BaseApplication $app
     */
    public function boot(BaseApplication $app)
    {
    }
}
