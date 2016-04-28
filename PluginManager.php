<?php

namespace Plugin\Point;

use Eccube\Entity\PageLayout;
use Eccube\Plugin\AbstractPluginManager;
use Monolog\Logger;
use Plugin\Point\Entity\PointInfo;

/**
 * インストールハンドラー
 * Class PluginManager
 * @package Plugin\Point
 */
class PluginManager extends AbstractPluginManager
{
    /** @var \Eccube\Application */
    protected $app;

    /**
     * PluginManager constructor.
     */
    public function __construct()
    {
        $this->app = \Eccube\Application::getInstance();
    }

    /**
     * インストール時に実行
     * @param $config
     * @param $app
     */
    public function install($config, $app)
    {
        $this->migrationSchema($app, __DIR__.'/Resource/doctrine/migration', $config['code']);
    }

    /**
     * アンインストール時に実行
     * @param $config
     * @param $app
     */
    public function uninstall($config, $app)
    {
        $this->migrationSchema($app, __DIR__.'/Resource/doctrine/migration', $config['code'], 0);
    }

    /**
     * プラグイン有効化時に実行
     * @param $config
     * @param $app
     */
    public function enable($config, $app)
    {

        $PointInfo = new PointInfo();
        $PointInfo
            ->setPlgAddPointStatus(1)
            ->setPlgBasicPointRate(1)
            ->setPlgPointConversionRate(1)
            ->setPlgRoundType(1)
            ->setPlgCalculationType(1);

        $this->app['orm.em']->persist($PointInfo);
        $this->app['orm.em']->flush($PointInfo);


        // ページレイアウトにプラグイン使用時の値を代入
        $deviceType = $this->app['eccube.repository.master.device_type']->findOneById(10);
        $pageLayout = new PageLayout();
        $pageLayout->setId(null);
        $pageLayout->setDeviceType($deviceType);
        $pageLayout->setFileName('../../Plugin/Point/Resource/template/default/point_use');
        $pageLayout->setEditFlg(2);
        $pageLayout->setMetaRobots('noindex');
        $pageLayout->setUrl('point_use');
        $pageLayout->setName('商品購入確認/利用ポイント');
        try {
            $this->app['orm.em']->persist($pageLayout);
            $this->app['orm.em']->flush($pageLayout);
        } catch (\Exception $e) {
            $log = $e;
            $this->app->log($log, array(), Logger::WARNING);
            $app->addError('プラグインの有効化に失敗いたしました', 'admin');
        }
    }

    /**
     * プラグイン無効化時実行
     * @param $config
     * @param $app
     */
    public function disable($config, $app)
    {
        $qb = $this->app['db']->createQueryBuilder();
        $qb->delete('plg_point_info');
        $qb->execute();
        // ログテーブルからポイントを計算
        $pageLayout = $this->app['eccube.repository.page_layout']->findByUrl('point_use');

        // 重複登録対応
        foreach ($pageLayout as $deleteNode) {
            $this->app['orm.em']->persist($deleteNode);
            $this->app['orm.em']->remove($deleteNode);
        }
        try {
            $this->app['orm.em']->flush();
        } catch (\Exception $e) {
            $log = $e;
            $this->app->log($log, array(), Logger::WARNING);
            $app->addError('プラグインの無効化に失敗いたしました', 'admin');
        }
    }

    /**
     * アップデート時に行う処理
     * @param $config
     * @param $app
     */
    public function update($config, $app)
    {
    }
}
