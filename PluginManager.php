<?php

namespace Plugin\Point;

use Eccube\Entity\PageLayout;
use Eccube\Plugin\AbstractPluginManager;
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
        $this->migrationSchema($app, __DIR__.'/Resource/doctrine/migration', $config['code']);

        // ポイント基本設定のデフォルト値を登録
        $PointInfo = $this->app['orm.em']
            ->getRepository('Plugin\Point\Entity\PointInfo')
            ->getLastInsertData();
        if (is_null($PointInfo)) {
            $PointInfo = new PointInfo();
            $PointInfo
                ->setPlgAddPointStatus(1)
                ->setPlgBasicPointRate(1)
                ->setPlgPointConversionRate(1)
                ->setPlgRoundType(1)
                ->setPlgCalculationType(1);

            $this->app['orm.em']->persist($PointInfo);
            $this->app['orm.em']->flush($PointInfo);
        }

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
        $this->app['orm.em']->persist($pageLayout);
        $this->app['orm.em']->flush($pageLayout);
    }

    /**
     * プラグイン無効化時実行
     * @param $config
     * @param $app
     */
    public function disable($config, $app)
    {
        // ページ情報の削除
        $pageLayout = $this->app['eccube.repository.page_layout']->findByUrl('point_use');
        foreach ($pageLayout as $deleteNode) {
            $this->app['orm.em']->remove($deleteNode);
            $this->app['orm.em']->flush($deleteNode);
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
