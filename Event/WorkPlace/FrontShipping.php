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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * フックポイント汎用処理具象クラス
 *  - 拡張元 : 商品購入確認
 *  - 拡張項目 : 合計金額・ポイント
 * Class FrontShipping
 * @package Plugin\Point\Event\WorkPlace
 */
class FrontShipping extends AbstractWorkPlace
{
    /**
     * 通常はデータの保存を行うが、本処理では、合計金額判定とエラー処理
     * @param EventArgs $event
     * @return bool
     */
    public function save(EventArgs $event)
    {
        // 必要エンティティの確認
        if (!$event->hasArgument('Order')) {
            return false;
        }
        $order = $event->getArgument('Order');

        // 会員情報を取得
        $customer = $order->getCustomer();
        if (empty($customer)) {
            return false;
        }

        // 計算用ヘルパー呼び出し
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];
        // 計算ヘルパー取得判定
        if (empty($calculator)) {
            return false;
        }

        // 計算に必要なエンティティを登録
        $calculator->addEntity('Order', $order);
        $calculator->addEntity('Customer', $customer);

        // 合計金額マイナス確認
        if ($calculator->calculateTotalDiscountOnChangeConditions()) {
            $this->app->addError('お支払い金額がマイナスになったため、ポイントをキャンセルしました。', 'front.request');
        }
    }
}
