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
use Eccube\Event\TemplateEvent;
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

        // 利用ポイントを取得
        $usePoint = $this->app['eccube.plugin.point.repository.point']->getLatestPreUsePoint($Order);

        // 加算ポイントを算出
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];
        $calculator->addEntity('Order', $Order);
        $calculator->addEntity('Customer', $Order->getCustomer());
        $calculator->setUsePoint($usePoint * -1);
        $addPoint = $calculator->getAddPointByOrder();
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

        // 仮利用ポイントの相殺を登録
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->savePreUsePoint($usePoint * -1);

        // 利用ポイントを登録し、その結果 保有ポイントがマイナス(ポイント使いすぎ)になっていないか確認を行う
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveUsePoint($usePoint);
        // 現在ポイントを履歴から計算
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $Order->getCustomer()->getId()
        );
        $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $Order->getCustomer()->getId(),
            $orderIds
        );
        // 保有ポイントマイナスのチェック
        if ($calculateCurrentPoint < 0) {
            $this->app['monolog.point']->addInfo('save current point', array(
                    'current point' => $calculateCurrentPoint,
                )
            );

            // ポイントがマイナスの時はメール送信
            $this->app['eccube.plugin.point.mail.helper']->sendPointNotifyMail($Order, $calculateCurrentPoint, $usePoint);
            // ポイントがマイナスになった受注であるということを記録
            $pointAbuse = new PointAbuse($Order->getId());
            $this->app['orm.em']->persist($pointAbuse);
            $this->app['orm.em']->flush($pointAbuse);
        }

        // 加算ポイントを登録
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveAddPoint($addPoint);
        $this->app['eccube.plugin.point.history.service']->savePointStatus();

        // 確定ステータス＝新規受注であれば、加算ポイントを確定状態に変更
        if ($add_point_flg) {
            $this->app['eccube.plugin.point.history.service']->fixPointStatus();
        }

        // 保有ポイントを再計算して、会員の保有ポイントを更新する
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $Order->getCustomer()->getId()
        );
        $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $Order->getCustomer()->getId(),
            $orderIds
        );
        // 会員ポイント更新
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $calculateCurrentPoint,
            $Order->getCustomer()
        );

        $this->app['monolog.point']->addInfo('save add point', array(
                'customer_id' => $Order->getCustomer()->getId(),
                'order_id' => $Order->getId(),
                'current point' => $calculateCurrentPoint,
                'add point' => $addPoint,
                'use point' => $usePoint,
            )
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

    /**
     * Twig拡張処理
     * @param TemplateEvent $event
     * @return void
     */
    public function createTwig(TemplateEvent $event)
    {
        // 不適切な受注記録に、今回の受注が含まれているか？
        $parameters = $event->getParameters();
        $orderId = $parameters['orderId'];
        $result = $this->app['eccube.plugin.point.repository.pointabuse']->findBy(array('order_id' => $orderId));
        if (empty($result)) {
            return;
        }

        // エラーメッセージの挿入
        $search = '{% block main %}';
        $script = <<<__EOL__
{% block javascript %}
            <script>
            $(function() {
                $("#deliveradd_input_box__message").children("h2.heading01").remove();
                $("#deliveradd_input_box__message").prepend('<div class="message"><p class="errormsg bg-danger">ご注文中に問題が発生した可能性があります。お手数ですがお問い合わせをお願いします。(受注番号：{{ orderId }})</p></div>');
            });
            </script>
{% endblock %}
__EOL__;

        $replace = $search.$script;
        $source = str_replace($search, $replace, $event->getSource());
        $event->setSource($source);
    }
}
