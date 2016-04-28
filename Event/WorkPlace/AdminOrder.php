<?php


namespace Plugin\Point\Event\WorkPlace;

use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Plugin\Point\Entity\PointUse;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
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
class  AdminOrder extends AbstractWorkPlace
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
     * フォームを拡張
     *  -   利用ポイント項目を追加
     *  -   追加項目の位置はTwig拡張で配備
     * @param FormBuilder $builder
     * @param Request $request
     * @return bool
     */
    public function createForm(FormBuilder $builder, Request $request)
    {
        // オーダーエンティティを取得
        $order = $builder->getData();

        if (empty($order) || !preg_match('/Order/', get_class($order))) {
            return false;
        }

        $hasCustomer = $order->getCustomer();

        // 初期値・取得値設定処理
        // 初回のダミーエンティティにはカスタマー情報を含まない
        $lastUsePoint = 0;
        if (!empty($order) && !empty($hasCustomer)) {
            $lastUsePoint = $this->app['eccube.plugin.point.repository.point']->getLastAdjustUsePoint($order);

            // 初期値設定
            if (empty($lastUsePoint)) {
                $lastUsePoint = 0;
            }
        }

        // カスタマー保有ポイント取得
        if (!empty($hasCustomer)) {
            $hasPoint = $this->app['eccube.plugin.point.repository.pointcustomer']->getLastPointById(
                $hasCustomer->getId()
            );
        }

        // 0値設定
        if (!isset($hasPoint) && empty($hasPoint)) {
            $hasPoint = 0;
        }

        // ポイント付与率項目拡張
        $builder->add(
            'plg_use_point',
            'text',
            array(
                'label' => '利用ポイント',
                'required' => false,
                'mapped' => false,
                'data' => abs($lastUsePoint),
                'empty_data' => null,
                'attr' => array(
                    'placeholder' => '手動調整可能なカスタマーの利用ポイント',
                    'class' => 'form-control',
                ),
                'constraints' => array(
                    new Assert\Regex(
                        array(
                            'pattern' => "/^\d+?$/u",
                            'message' => '数字で入力してください。',
                        )
                    ),
                    new Assert\LessThanOrEqual(
                        array(
                            'value' => $hasPoint,
                            'message' => '利用ポイントは保有ポイント以内で入力してください。',
                        )
                    ),
                    new Assert\Range(
                        array(
                            'min' => 0,
                            'max' => 100000,
                        )
                    ),
                ),
            )
        );
    }

    /**
     * Twigの拡張
     *  - フォーム追加項目を挿入
     * @param TemplateEvent $event
     * @return bool
     */
    public function createTwig(TemplateEvent $event)
    {
        // ポイント情報基本設定確認
        if (empty($this->pointInfo)) {
            return false;
        }

        // オーダーエンティティを取得
        $args = $event->getParameters();
        // オーダーが取得判定
        if (!isset($args['Order'])) {
            return false;
        }

        // フォームの有無を判定
        if (!isset($args['form'])) {
            return false;
        }

        $order = $args['Order'];

        // 商品が一点もない際は、ポイント利用欄を表示しない
        if (count($order->getOrderDetails()) < 1) {
            $args['form']->children['plg_use_point']->vars['block_prefixes'][1] = 'hidden';
        }

        $hasCustomer = $order->getCustomer();

        // 初回アクセスのダミーエンティティではカスタマー情報は含まない
        if (empty($hasCustomer)) {
            return false;
        }

        // 利用ポイントをエンティティにセット
        $pointUse = new PointUse();

        // 手動調整ポイントを取得
        $usePoint = $this->app['eccube.plugin.point.repository.point']->getLastAdjustUsePoint($order);

        if (empty($usePoint)) {
            $usePoint = 0;
        }

        // 計算ヘルパー取得判定
        if (is_null($this->calculator)) {
            return true;
        }

        // 計算に必要なエンティティを登録
        $this->calculator->addEntity('Order', $order);
        $this->calculator->addEntity('Customer', $order->getCustomer());
        $this->calculator->setUsePoint($usePoint);

        // 合計金額がマイナスかどうかを判定
        $errorFlg = false;
        if (!$this->app['eccube.service.shopping']->isDiscount($order, $this->calculator->getConversionPoint())) {
            $errorFlg = true;
        }

        // 利用ポイントを格納
        $pointUse->setPlgUsePoint($usePoint);

        // ポイント基本設定の確認
        if (empty($this->pointInfo)) {
            return false;
        }

        // 付与ポイント取得
        $addPoint = $this->calculator->getAddPointByOrder();

        // 付与ポイント取得可否判定
        if (is_null($addPoint)) {
            return true;
        }

        // 現在保有ポイント取得
        $currentPoint = $this->calculator->getPoint();

        //保有ポイント取得可否判定
        if (is_null($currentPoint)) {
            $currentPoint = 0;
        }

        // ポイント表示用変数作成
        $point = array();
        $point['current'] = $currentPoint;
        $point['use'] = $usePoint;
        $point['add'] = $addPoint;

        $form = $args['form'];


        // 合計金額エラー判定
        $errors = array();
        if ($errorFlg) {
            $form['plg_use_point']->vars['errors'] = new FormError('計算でマイナス値が発生します。入力を確認してください。');
        }

        // twigパラメータにポイント情報を追加
        // 受注商品情報に受注ポイント情報を表示
        $snippet = $this->app->render(
            'Point/Resource/template/admin/Event/AdminOrder/order_point.twig',
            array(
                'form' => $args['form'],
                'point' => $point,
            )
        )->getContent();
        $search = '<dl id="product_info_result_box__body_summary"';
        $this->replaceView($event, $snippet, $search);
    }

    /**
     * 受注ステータス判定・ポイント更新
     * @param EventArgs $event
     * @return bool
     */
    public function save(EventArgs $event)
    {
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

        // 会員情報取得
        $this->customer = $event->getArgument('Customer');
        if (empty($this->customer)) {
            return false;
        }

        // アップデート処理判定( 受注画面で購入商品構成に変更があった場合 )
        if (!empty($this->targetOrder) && !empty($this->customer)) {
            $this->calculator->addEntity('Order', $this->targetOrder);
            $this->calculator->addEntity('Customer', $this->customer);
            $addPoint = $this->calculator->getAddPointByOrder();

            // 付与ポイント有無確認
            if (!empty($addPoint)) {
                // 現在仮付与ポイント取得
                $provisionalPoint = $this->app['eccube.plugin.point.repository.point']->getProvisionalAddPointByOrder(
                    $this->targetOrder
                );

                // 現在仮付与ポイントと保存済み付与ポイントに相違があった際はアップデート処理
                if ($provisionalPoint != $addPoint) {
                    $this->updateOrderEvent($addPoint, $provisionalPoint);
                }
            }
        }

        // 以下受注画面内、イベント処理
        // 受注ステータス判定→ポイント確定処理
        if ($this->targetOrder->getOrderStatus()->getId() == $this->pointInfo->getPlgAddPointStatus()) {
            $this->pointFixEvent($event);
        }

        // 利用ポイント調整イベント
        $this->pointUseEvent($event);
    }

    /**
     * 受注編集で購入商品の構成が変更した際に以下処理を行う
     *  - 前回付与ポイントの打ち消し
     *  - 今回付与ポイントの仮付与
     * @param $addPoint
     * @param $provisionalPoint
     * @return bool
     */
    public function updateOrderEvent($addPoint, $provisionalPoint)
    {
        // 引数判定
        if (empty($addPoint)) {
            return false;
        }

        // 仮付与ポイントが空でなければ登録
        if (!empty($provisionalPoint)) {
            $this->history->addEntity($this->targetOrder);
            $this->history->addEntity($this->customer);
            $this->history->saveProvisionalAddPoint(abs($provisionalPoint) * -1);
        }

        // 本受注に対して最後に確定付与されたポイントを取得
        $lastAddPoint = $this->app['eccube.plugin.point.repository.point']->getLastAddPointByOrder($this->targetOrder);

        // 最後に確定付与されたポイントと今回付与ポイントが
        // 同値であれば、リロードと判定
        if ($lastAddPoint == $addPoint) {
            return false;
        }

        // 最終確定付与ポイント打ち消し
        if (!empty($lastAddPoint)) {
            $this->history->refreshEntity();
            $this->history->addEntity($this->targetOrder);
            $this->history->addEntity($this->customer);
            $this->history->cancelAddPoint(abs($lastAddPoint) * -1);
        }

        // 履歴保存
        // 仮ポイントの保存
        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveProvisionalAddPoint(abs($addPoint));

        $point = array();
        // 現在保有ポイント再計算
        $this->refreshCurrentPoint();
        $point['current'] = $this->calculateCurrentPoint;
        $point['use'] = 0;
        $point['add'] = $addPoint;

        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $this->calculateCurrentPoint,
            $this->customer
        );

        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveSnapShot($point);
    }

    /**
     * ポイント確定時処理
     *  -   受注ステータス判定でポイントの付与が確定した際の処理
     * @param $event
     * @return bool
     */
    protected function pointFixEvent($event)
    {
        // 本受注最終受注ポイントの種類を判定する
        $fixFlg = $this->app['eccube.plugin.point.repository.point']->isLastProvisionalFix($this->targetOrder);

        // 最終ポイントの種別が確定ポイントの場合は処理キャンセル
        if ($fixFlg) {
            return false;
        }

        if (empty($event)) {
            return false;
        }

        if (empty($this->targetOrder)) {
            return false;
        }

        if (empty($this->customer)) {
            return false;
        }

        // 仮付与ポイントがあるか確認
        $provisionalPoint = $this->app['eccube.plugin.point.repository.point']->getProvisionalAddPointByOrder(
            $this->targetOrder
        );

        if (empty($provisionalPoint)) {
            return false;
        }

        // AdminOrder計算ヘルパーを使用
        $this->calculator->addEntity('Order', $this->targetOrder);
        $this->calculator->addEntity('Customer', $this->customer);

        // 履歴保存
        // 仮ポイントの保存
        $this->saveFixOrderHistory($provisionalPoint);

        $point = array();
        // 現在保有ポイント再計算
        $this->refreshCurrentPoint();
        $point['current'] = $this->calculateCurrentPoint;
        $point['use'] = 0;
        $point['add'] = $provisionalPoint;

        // SnapShot保存
        $this->saveFixOrderSnapShot($point);
    }

    /**
     * 本受注ログ最終利用ポイントと今回利用ポイントの相違確認
     *  - 相違あり : 利用ポイント打ち消し更新
     *  - 相違なし : 処理中止
     * @param $event
     * @return bool
     */
    protected function pointUseEvent($event)
    {
        // 最終利用ポイントの取得
        $lastUsePoint = $this->app['eccube.plugin.point.repository.point']->getLastAdjustUsePoint($this->targetOrder);

        // 最終利用ポイント確認
        if (empty($lastUsePoint)) {
            $lastUsePoint = 0;
        }

        // 最終利用ポイントと現在利用ポイントが同じであれば処理をキャンセル
        if ($this->isSameUsePoint($lastUsePoint)) {
            return false;
        }


        // 現在利用ポイントを設定
        $calculateUsePoint = $lastUsePoint - $this->usePoint;
        $calculateUsePoint = $calculateUsePoint * -1;

        // 計算に必要なエンティティをセット
        $this->calculator->addEntity('Order', $this->targetOrder);
        $this->calculator->addEntity('Customer', $this->customer);
        // 計算使用値は絶対値
        $this->calculator->setUsePoint($this->usePoint);

        // 付与ポイント取得
        $addPoint = $this->calculator->getAddPointByOrder();

        // 履歴保存
        // 戻し
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        // 戻しは以前のポイント
        $this->history->saveUsePointAdjustOrderHistory(abs($lastUsePoint));
        // 入力
        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveUsePointAdjustOrderHistory(abs($this->usePoint) * -1);

        // 現在保有ポイント再計算
        $this->refreshCurrentPoint();
        // 現在保有ポイント取得
        $currentPoint = $this->calculateCurrentPoint;
        if (empty($currentPoint)) {
            $currentPoint = 0;
        }

        $point = array();
        // 現在ポイントをログから再計算
        $point['current'] = $currentPoint;
        $point['use'] = $calculateUsePoint;
        // 計算付与ポイント
        $point['add'] = $addPoint;

        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $currentPoint,
            $this->customer
        );

        // SnapShot保存
        $this->saveAdjustUseOrderSnapShot($point);
    }

    /**
     * 本受注ログ最終利用ポイントと今回利用ポイントを判定
     *  - true 同一
     *  - false 相違
     * @param $lastUse
     * @return bool
     */
    protected function isSameUsePoint($lastUse)
    {
        // 必要値判定
        if ($lastUse == $this->usePoint) {
            return true;
        }

        return false;
    }

    /**
     * ポイント確定情報の保存
     * @param $provisionalPoint
     * @return bool
     */
    protected function saveFixOrderHistory($provisionalPoint)
    {
        // 必要エンティティ判定
        if (empty($this->targetOrder)) {
            return false;
        }

        if (empty($this->customer)) {
            return false;
        }

        if (empty($provisionalPoint)) {
            return false;
        }

        // 履歴情報登録
        // 仮付与ポイント打ち消し
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->fixProvisionalAddPoint($provisionalPoint);
        // ポイント付与
        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveFixProvisionalAddPoint($provisionalPoint);

        // 会員ポイント更新
        // 現在ポイントを履歴から計算
        $this->refreshCurrentPoint();
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $this->calculateCurrentPoint,
            $this->customer
        );
    }

    /**
     * スナップショットテーブルへの保存
     *  - 利用ポイント調整時のスナップショット
     * @param $point
     * @return bool
     */
    protected function saveAdjustUseOrderSnapShot($point)
    {
        // 必要エンティティ判定
        if (empty($this->targetOrder)) {
            return false;
        }

        if (empty($this->customer)) {
            return false;
        }

        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveSnapShot($point);
    }

    /**
     * スナップショットテーブルへの保存
     *  - 付与ポイント確定時のスナップショット
     * @param $point
     * @return bool
     */
    protected function saveFixOrderSnapShot($point)
    {
        // 必要エンティティ判定
        if (empty($this->targetOrder)) {
            return false;
        }

        if (empty($this->customer)) {
            return false;
        }

        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveSnapShot($point);
    }

    /**
     * 現在保有ポイントをログから再計算
     */
    protected function refreshCurrentPoint()
    {
        $this->calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->getCalculateCurrentPointByCustomerId(
            $this->customer->getId()
        );
    }
}
