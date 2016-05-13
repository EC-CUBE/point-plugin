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

namespace Plugin\Point\Event\WorkPlace;

use Eccube\Event\EventArgs;
use Plugin\Point\Entity\PointAbuse;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * フックポイント汎用処理具象クラス
 *  - 拡張元 : 商品購入完了
 *  - 拡張項目 : メール内容
 * Class FrontShoppingComplete
 * @package Plugin\Point\Event\WorkPlace
 */
class FrontShoppingComplete extends AbstractWorkPlace
{
    /**
     * ポイントログの保存
     *  - 仮付与ポイント
     *  - 確定ポイント判定
     *  - スナップショット保存
     *  - メール送信
     * @param EventArgs $event
     * @return bool
     * @throws UndefinedFunctionException
     */
    public function save(EventArgs $event)
    {
        $this->app['monolog.point']->addInfo('save start');

        $Order = $event->getArgument('Order');

        // 使用ポイントをエンティティに格納
        $usePoint = $this->app['eccube.plugin.point.repository.point']->getLatestPreUsePoint($Order);

        // 計算判定取得
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];

        // 計算に必要なエンティティを登録
        $calculator->addEntity('Order', $Order);
        $calculator->addEntity('Customer', $Order->getCustomer());
        $calculator->setUsePoint($usePoint * -1);

        // 加算ポイント取得
        $addPoint = $calculator->getAddPointByOrder();

        // 加算ポイント取得可否判定
        if (is_null($addPoint)) {
            $addPoint = 0;
        }

        // ポイント付与受注ステータスが「新規」の場合、付与ポイントを確定
        $add_point_flg = false;
        $pointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        // ポイント機能基本設定の付与ポイント受注ステータスを取得
        if ($pointInfo->getPlgAddPointStatus() == $this->app['config']['order_new']) {
            $add_point_flg = true;
        }

        // 履歴情報登録
        // 利用ポイント
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->savePreUsePoint($usePoint * -1);
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveUsePoint($usePoint);

        // ポイントの付与
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveAddPoint($addPoint);

        // ポイントステータスのレコードを生成
        $this->app['eccube.plugin.point.history.service']->savePointStatus();

        // 付与ポイント受注ステータスが新規であれば、ポイントを確定状態にする
        if ($add_point_flg) {
            $this->app['eccube.plugin.point.history.service']->fixPointStatus();
        }

        // 現在ポイントを履歴から計算
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $Order->getCustomer()->getId()
        );
        $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $Order->getCustomer()->getId(),
            $orderIds
        );

        if ($calculateCurrentPoint < 0) {

            $this->app['monolog.point']->addInfo('save current point', array(
                    'current point' => $calculateCurrentPoint,
                )
            );

            // ポイントがマイナスの時はメール送信
            $this->app['eccube.plugin.point.mail.helper']->sendPointNotifyMail($Order, $calculateCurrentPoint, $usePoint);
            // テーブルに記憶
            $pointAbuse = new PointAbuse($Order->getId());
            $this->app['orm.em']->persist($pointAbuse);
            $this->app['orm.em']->flush($pointAbuse);
        }

        $this->app['monolog.point']->addInfo('save add point', array(
                'customer_id' => $Order->getCustomer()->getId(),
                'order_id' => $Order->getId(),
                'current point' => $calculateCurrentPoint,
                'add point' => $addPoint,
                'use point' => $usePoint,
            )
        );

        // 会員ポイント更新
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $calculateCurrentPoint,
            $Order->getCustomer()
        );

        // ポイント保存用変数作成
        $point = array();
        $point['current'] = $calculateCurrentPoint;
        $point['use'] = $usePoint * -1;
        $point['add'] = $addPoint;
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveSnapShot($point);

        $this->app['monolog.point']->addInfo('save end');

    }
}
