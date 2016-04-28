<?php


namespace Plugin\Point\Event\WorkPlace;

use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Plugin\Point\Resource\lib\PointCalculateHelper\Bridge\CalculateType\FrontCart\NonSubtraction;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * フックポイント汎用処理具象クラス
 *  - 拡張元 : カート
 *  - 拡張項目 : 画面表示
 * Class FrontCart
 * @package Plugin\Point\Event\WorkPlace
 */
class FrontCart extends AbstractWorkPlace
{
    /**
     * カートページにポイント情報を表示
     * @param TemplateEvent $event
     * @return bool
     */
    public function createTwig(TemplateEvent $event)
    {
        // 権限判定
        $isAuth = $this->app->isGranted('IS_AUTHENTICATED_FULLY');

        // ポイント情報基本設定を取得
        $pointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        $pointRate = 0;
        if (!empty($pointInfo)) {
            $pointRate = (integer)$pointInfo->getPlgPointConversionRate();
        }

        // ポイント表示用変数作成
        $point = array();

        // ポイント換算率取得
        if ($isAuth) {
            // ポイント計算ヘルパーを取得
            $calculator = null;
            $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];

            // ヘルパーの取得判定
            if (empty($calculator)) {
                return false;
            }

            // カスタマー情報を取得
            $customer = $this->app['security']->getToken()->getUser();

            if (empty($customer)) {
                return false;
            }

            // 計算に必要なエンティティを登録
            // カートアイテム(プロダクトクラス)を取得設定
            $parameters = $event->getParameters();

            if (empty($parameters)) {
                return false;
            }

            // カートオブジェクトの確認
            if (!isset($parameters['Cart']) || empty($parameters['Cart'])) {
                return false;
            }

            // 計算に必要なエンティティを格納
            $calculator->addEntity('Customer', $customer);
            $calculator->addEntity('Cart', $parameters['Cart']);


            // 会員保有ポイントを取得
            $currentPoint = $calculator->getPoint();

            // 会員保有ポイント取得判定
            if (empty($currentPoint)) {
                $currentPoint = 0;
            }

            // 購入商品付与ポイント取得
            $addPoint = $calculator->getAddPointByCart();

            // 購入商品付与ポイント判定
            if (empty($addPoint)) {
                $addPoint = 0;
            }
            $point['current'] = $currentPoint;
            $point['add'] = $addPoint;
        }

        $point['rate'] = $pointRate;

        // 使用ポイントボタン付与
        // 権限判定
        if ($isAuth) {
            $snippet = $this->app->render(
                'Point/Resource/template/default/Event/Cart/point_box.twig',
                array(
                    'point' => $point,
                )
            )->getContent();
        } else {
            $snippet = $this->app->render(
                'Point/Resource/template/default/Event/Cart/point_box_no_customer.twig',
                array(
                    'point' => $point,
                )
            )->getContent();
        }
        $search = '<div id="cart_item_list"';
        $this->replaceView($event, $snippet, $search);
    }
}
