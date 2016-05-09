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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * フックポイント汎用処理具象クラス
 *  - 拡張元 : メール通知
 * Class AdminOrderMail
 * @package Plugin\Point\Event\WorkPlace
 */
class  AdminOrderMail extends AbstractWorkPlace
{

    /**
     * 加算ポイント表示
     *
     * @param TemplateEvent $event
     * @return bool
     */
    public function createTwig(TemplateEvent $event)
    {

        $args = $event->getParameters();

        $Order = $args['Order'];

        $Customer = $Order->getCustomer();
        if (empty($Customer)) {
            return false;
        }

        $body = $args['body'];

        // 計算ヘルパーの取得
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];

        // 計算に必要なエンティティの設定
        $calculator->addEntity('Order', $Order);
        $calculator->addEntity('Customer', $Customer);

        // 計算値取得
        $addPoint = $calculator->getAddPointByOrder();

        $body = $this->getBody($body, $addPoint);

        $args['body'] = $body;

        $event->setParameters($args);

    }


    /**
     * 加算ポイント表示
     *
     * @param EventArgs $event
     * @return bool
     */
    public function save(EventArgs $event)
    {

        $this->app['monolog.point.admin']->addInfo('save start');

        $Order = $event->getArgument('Order');
        $MailHistory = $event->getArgument('MailHistory');

        $Customer = $Order->getCustomer();
        if (empty($Customer)) {
            return false;
        }

        $body = $MailHistory->getMailBody();

        // 計算ヘルパーの取得
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];

        // 計算に必要なエンティティの設定
        $calculator->addEntity('Order', $Order);
        $calculator->addEntity('Customer', $Customer);

        // 計算値取得
        $addPoint = $calculator->getAddPointByOrder();

        $body = $this->getBody($body, $addPoint);

        // メッセージにメールボディをセット
        $MailHistory->setMailBody($body);

        $this->app['orm.em']->flush($MailHistory);


        $this->app['monolog.point.admin']->addInfo('save end');
    }


    /**
     * 本文を置換
     *
     * @param $body
     * @param $addPoint
     * @return mixed
     */
    private function getBody($body, $addPoint)
    {

        // 情報置換用のキーを取得
        $search = array();
        preg_match_all('/合　計.*\\n/u', $body, $search);

        // メール本文置換
        $snippet = PHP_EOL;
        $snippet .= PHP_EOL;
        $snippet .= '***********************************************'.PHP_EOL;
        $snippet .= '　ポイント情報                                 '.PHP_EOL;
        $snippet .= '***********************************************'.PHP_EOL;
        $snippet .= '加算ポイント：'.number_format($addPoint).PHP_EOL;
        $snippet .= PHP_EOL;
        $replace = $search[0][0].$snippet;
        return preg_replace('/'.$search[0][0].'/u', $replace, $body);

    }

}
