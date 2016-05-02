<?php


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
 *  - 拡張元 : 受注登録( 編集 )
 *  - 拡張項目 : ポイント付与判定・登録・ポイント調整
 *  - 商品明細の変更によるポイントの調整
 * Class AdminOrder
 * @package Plugin\Point\Event\WorkPlace
 */
class  AdminOrderProgress extends AbstractWorkPlace
{
    /** @var */
    protected $pointInfo;
    /** @var */
    protected $pointType;
    /** @var */
    protected $targetOrder;
    /** @var */
    protected $calculateCurrentPoint;
    /** @var */
    protected $customer;
    /** @var */
    protected $calculator;
    /** @var */
    protected $history;
    /** @var */
    protected $usePoint;

    /**
     * AdminOrder constructor.
     */
    public function __construct()
    {
        parent::__construct();
        // 履歴管理ヘルパーセット
        $this->history = $this->app['eccube.plugin.point.history.service'];

        // ポイント情報基本設定をセット
        $this->pointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        if (empty($this->pointInfo)) {
            return false;
        }

        // 計算方法を取得
        $this->pointType = $this->pointInfo->getPlgCalculationType();

        // 計算ヘルパー取得
        $this->calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];
    }

    /**
     * 受注ステータス判定・ポイント更新
     * @param EventArgs $event
     * @return bool
     */
    public function save(EventArgs $event)
    {
        /*
        // 必要情報をセット
        $this->targetOrder = $event->getArgument('TargetOrder');

        if (empty($this->targetOrder)) {
            return false;
        }

        if (!$event->hasArgument('form')) {
            return false;
        }

        if ($event->getArgument('form')->has('plg_use_point')) {
            $this->usePoint = $event->getArgument('form')->get('plg_use_point')->getData();
        }

        // 利用ポイント確認
        if (empty($this->usePoint)) {
            $this->usePoint = 0;
        }
        */

        /**
         * プロセスイベントのみ以下実行
         * 値引き計算表示を本体側で行うために、値引き金額をセット
         */
        /*
        if (!$event->hasArgument('Customer')) {
            // 最後に利用したポイントを取得
            $lastUsePoint = $this->app['eccube.plugin.point.repository.point']->getLatestUsePoint(
                $this->targetOrder
            );

            // ここでDiscoutを設定
            $this->calculator->addEntity('Order', $this->targetOrder);
            $this->calculator->setUsePoint($this->usePoint);
            //$this->calculator->setDiscount($lastUsePoint);

            return false;
        }
        */
    }
}
