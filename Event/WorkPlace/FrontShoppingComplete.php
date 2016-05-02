<?php


namespace Plugin\Point\Event\WorkPlace;

use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Plugin\Point\Entity\PointUse;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
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
        // オーダー判定
        $order = $event->getArgument('Order');
        if (empty($order)) {
            return false;
        }

        // 使用ポイントをエンティティに格納
        $usePoint = $this->app['eccube.plugin.point.repository.point']->getLatestPreUsePoint($order);

        // 計算判定取得
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];

        // 計算ヘルパー取得判定
        if (is_null($calculator)) {
            // 画面がないためエラーをスロー
            throw new UndefinedFunctionException();
        }

        // 計算に必要なエンティティを登録
        $calculator->addEntity('Order', $order);
        $calculator->addEntity('Customer', $order->getCustomer());
        $calculator->setUsePoint($usePoint);

        // 付与ポイント取得
        $addPoint = $calculator->getAddPointByOrder();

        //付与ポイント取得可否判定
        if (is_null($addPoint)) {
            // 画面がないためエラーをスロー
            throw new \UnexpectedValueException();
        }

        // 現在保有ポイント取得
        $currentPoint = $calculator->getPoint();

        //保有ポイント取得可否判定
        if (is_null($currentPoint)) {
            $currentPoint = 0;
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
        $this->app['eccube.plugin.point.history.service']->addEntity($order);
        $this->app['eccube.plugin.point.history.service']->addEntity($order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->savePreUsePoint($usePoint * -1);
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($order);
        $this->app['eccube.plugin.point.history.service']->addEntity($order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveUsePoint($usePoint);

        // ポイントの付与
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($order);
        $this->app['eccube.plugin.point.history.service']->addEntity($order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveAddPoint($addPoint);

		// ポイントステータスのレコードを生成
        $this->app['eccube.plugin.point.history.service']->savePointStatus();

        // 付与ポイント受注ステータスが新規であれば、ポイントを確定状態にする
        if ($add_point_flg) {
            $this->app['eccube.plugin.point.history.service']->fixPointStatus();
        }

        // 現在ポイントを履歴から計算
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $order->getCustomer()->getId()
        );
        $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $order->getCustomer()->getId(),
            $orderIds
        );

        if ($calculateCurrentPoint < 0) {
            // TODO: ポイントがマイナス！
        }

        // 会員ポイント更新
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $calculateCurrentPoint,
            $order->getCustomer()
        );

        // ポイント保存用変数作成
        $point = array();
        $point['current'] = $calculateCurrentPoint;
        $point['use'] = $usePoint * -1;
        $point['add'] = $addPoint;
        $this->app['eccube.plugin.point.history.service']->refreshEntity();
        $this->app['eccube.plugin.point.history.service']->addEntity($order);
        $this->app['eccube.plugin.point.history.service']->addEntity($order->getCustomer());
        $this->app['eccube.plugin.point.history.service']->saveSnapShot($point);
    }
}
