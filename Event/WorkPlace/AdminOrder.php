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
            $lastUsePoint = -($this->app['eccube.plugin.point.repository.point']->getLatestUsePoint($order));
        }

        // カスタマー保有ポイント取得
        if (!empty($hasCustomer)) {
            $hasPoint = $this->app['eccube.plugin.point.repository.pointcustomer']->getLastPointById(
                $hasCustomer->getId()
            );
            // 入力の上限になるので、保有ポイント+現在の利用ポイントを設定する
            $hasPoint += $lastUsePoint;
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
                'data' => $lastUsePoint,
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
        $usePoint = -($this->app['eccube.plugin.point.repository.point']->getLatestUsePoint($order));

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
            $newAddPoint = $this->calculator->getAddPointByOrder();

            // 付与ポイント有無確認
            if (!empty($newAddPoint)) {
                // 更新前の付与ポイント取得
                $beforeAddPoint = $this->app['eccube.plugin.point.repository.point']->getLatestAddPointByOrder(
                    $this->targetOrder
                );

                // 更新前の付与ポイントと新しい付与ポイントに相違があった際はアップデート処理
                if ($beforeAddPoint != $newAddPoint) {
                    $this->updateOrderEvent($newAddPoint, $beforeAddPoint);
                }
            }
        }

        // 以下受注画面内、イベント処理
        // 受注ステータス判定→ポイント確定処理
        if ($this->targetOrder->getOrderStatus()->getId() == $this->pointInfo->getPlgAddPointStatus()) {
            $this->pointFixEvent($event);
        }

        // 利用ポイントの更新
        $this->pointUseEvent($event);
    }

    /**
     * 受注編集で購入商品の構成が変更した際に以下処理を行う
     *  - 前回付与ポイントの打ち消し
     *  - 今回付与ポイントの付与
     * @param $newAddPoint
     * @param $beforeAddPoint
     * @return bool
     */
    public function updateOrderEvent($newAddPoint, $beforeAddPoint)
    {
        // 引数判定
        if (empty($newAddPoint)) {
            return false;
        }

        // 以前の加算ポイントをマイナスで相殺
        if (!empty($beforeAddPoint)) {
            $this->history->addEntity($this->targetOrder);
            $this->history->addEntity($this->customer);
            $this->history->saveAddPointByOrderEdit($beforeAddPoint * -1);
        }

        // 新しい加算ポイントの保存
        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveAddPointByOrderEdit($newAddPoint);

        // 会員の保有ポイント保存
        $currentPoint = $this->calculateCurrentPoint();
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $currentPoint,
            $this->customer
        );

        // スナップショット保存
        $point = array();
        $point['current'] = $currentPoint;
        $point['use'] = 0;
        $point['add'] = $newAddPoint;
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
        // ポイントが確定ステータスなら何もしない
        if ($this->app['eccube.plugin.point.repository.pointstatus']->isFixedStatus($this->targetOrder)) {
            return false;
        }

        // 必要エンティティ判定
        if (empty($this->targetOrder)) {
            return false;
        }

        if (empty($this->customer)) {
            return false;
        }

        // ポイントを確定ステータスにする
        $this->fixPointStatus();

        // 会員の保有ポイント更新
        $currentPoint = $this->calculateCurrentPoint();
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $currentPoint,
            $this->customer
        );

        // SnapShot保存
        $fixedAddPoint = $this->app['eccube.plugin.point.repository.point']->getLatestAddPointByOrder(
            $this->targetOrder
        );
        $point = array();
        $point['current'] = $currentPoint;
        $point['use'] = 0;
        $point['add'] = $fixedAddPoint;
        $this->saveFixOrderSnapShot($point);
    }

    /**
     * 受注の利用ポイントを新しい利用ポイントに更新する
     *  - 相違あり : 利用ポイント打ち消し、更新
     *  - 相違なし : なにもしない
     * @param $event
     * @return bool
     */
    protected function pointUseEvent($event)
    {
        // 更新前の利用ポイントの取得
        $beforeUsePoint = -($this->app['eccube.plugin.point.repository.point']->getLatestUsePoint($this->targetOrder));
        // 更新前の利用ポイントと新しい利用ポイントが同じであれば処理をキャンセル
        if ($this->usePoint == $beforeUsePoint) {
            return;
        }

        // 計算に必要なエンティティをセット
        $this->calculator->addEntity('Order', $this->targetOrder);
        $this->calculator->addEntity('Customer', $this->customer);
        // 計算使用値は絶対値
        $this->calculator->setUsePoint($this->usePoint);

        // 履歴保存
        // 更新前の利用ポイントを加算して相殺
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveUsePointByOrderEdit($beforeUsePoint);
        // 新しい利用ポイントをマイナス
        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $this->history->saveUsePointByOrderEdit($this->usePoint * -1);

        // 会員ポイントの更新
        $currentPoint = $this->calculateCurrentPoint();
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $currentPoint,
            $this->customer
        );

        // SnapShot保存
        $point = array();
        $point['current'] = $currentPoint;
        $point['use'] = ($beforeUsePoint - $this->usePoint) * -1;
        $point['add'] = $this->calculator->getAddPointByOrder();
        $this->saveAdjustUseOrderSnapShot($point);
    }

    /**
     * 付与ポイントを「確定」に変更する
     */
    protected function fixPointStatus()
    {
        // 必要エンティティ判定
        if (empty($this->targetOrder)) {
            return;
        }

        // ポイントを確定状態にする
        $this->history->addEntity($this->targetOrder);
        $this->history->fixPointStatus();
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
     * @return int 保有ポイント
     */
    protected function calculateCurrentPoint()
    {
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $this->customer->getId()
        );
        $currentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $this->customer->getId(),
            $orderIds
        );

        if ($currentPoint < 0) {
            // TODO: ポイントがマイナス！
        }

        return $currentPoint;
    }
}
