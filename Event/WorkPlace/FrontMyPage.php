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
 *  - 拡張元 : マイページ
 *  - 拡張項目 : 画面表示
 * Class FrontMyPage
 * @package Plugin\Point\Event\WorkPlace
 */
class FrontMyPage extends AbstractWorkPlace
{
    /**
     * マイページにポイント情報を差し込む
     * @param TemplateEvent $event
     * @return bool
     */
    public function createTwig(TemplateEvent $event)
    {
        $pointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        $point_rate = 0;
        if (!empty($pointInfo)) {
            $point_rate = (integer)$pointInfo->getPlgPointConversionRate();
        }

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

        if (empty($pointInfo)) {
            return false;
        }

        // 計算に必要なエンティティを登録
        $calculator->addEntity('Customer', $customer);

        // 会員保有ポイントを取得
        $currentPoint = $calculator->getPoint();

        // 会員保有ポイント取得判定
        if (empty($currentPoint)) {
            $currentPoint = 0;
        }

        // 仮ポイント取得
        $previsionAddPoint = $calculator->getProvisionalAddPoint();


        // 仮ポイント取得判定
        if (empty($previsionAddPoint)) {
            $previsionAddPoint = 0;
        }

        // ポイント表示用変数作成
        $point = array();
        $point['current'] = $currentPoint;
        $point['pre'] = $previsionAddPoint;
        $point['rate'] = $point_rate;

        // 使用ポイントボタン付与
        // twigコードにポイント表示欄を追加
        $snippet = $this->app->render(
            'Point/Resource/template/default/Event/MypageTop/point_box.twig',
            array(
                'point' => $point,
            )
        )->getContent();
        $search = '<div id="history_list"';
        $this->replaceView($event, $snippet, $search);
    }
}
