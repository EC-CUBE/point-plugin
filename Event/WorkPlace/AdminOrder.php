<?php


namespace Plugin\Point\Event\WorkPlace;

use Eccube\Entity\Customer;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
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

        // 計算ヘルパー取得
        $this->calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];
    }

    /**
     * 受注登録・編集
     *
     * @param FormBuilder $builder
     * @param Request $request
     * @param EventArgs|null $event
     */
    public function createForm(FormBuilder $builder, Request $request, EventArgs $event = null)
    {
        $builder = $event->getArgument('builder');
        $Order = $event->getArgument('TargetOrder');
        $Customer = $Order->getCustomer();

        $currentPoint = null;
        $usePoint = null;
        $addPoint = null;

        $builder = $this->buildForm($builder);

        // 非会員受注の場合は制御を行わない.
        if (!$Customer instanceof Customer) {
            return;
        }

        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $Customer->getId()
        );
        $currentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $Customer->getId(),
            $orderIds
        );
        $usePoint = $this->app['eccube.plugin.point.repository.point']->getLatestUsePoint($Order);
        $usePoint = - ($usePoint);

        // 受注編集時
        if ($Order->getId()) {
            $addPoint = $this->app['eccube.plugin.point.repository.point']->getLatestAddPointByOrder($Order);

            // 確定ステータスの場合
            if ($this->app['eccube.plugin.point.repository.pointstatus']->isFixedStatus($Order)) {
                $builder->addEventListener(
                    FormEvents::POST_SUBMIT,
                    function (FormEvent $event) use ($currentPoint, $usePoint, $addPoint) {
                        $form = $event->getForm();
                        $recalcCurrentPoint = $currentPoint + $usePoint - $addPoint;
                        $inputUsePoint = $form['plg_use_point']->getData();
                        $inputAddPoint = $form['plg_add_point']->getData();
                        if ($inputUsePoint > $recalcCurrentPoint + $inputAddPoint) {
                            $error = new FormError('保有ポイント以内になるよう調整してください');
                            $form['plg_use_point']->addError($error);
                            $form['plg_add_point']->addError($error);
                        }
                    }
                );
            // 非確定ステータスの場合
            } else {
                $builder->addEventListener(
                    FormEvents::POST_SUBMIT,
                    function (FormEvent $event) use ($currentPoint) {
                        $form = $event->getForm();
                        $inputUsePoint = $form['plg_use_point']->getData();
                        if ($inputUsePoint > $currentPoint) {
                            $error = new FormError('保有ポイント以内で入力してください');
                            $form['plg_use_point']->addError($error);
                        }
                    }
                );
            }
        // 新規受注登録
        } else {
            $builder->addEventListener(
                FormEvents::POST_SUBMIT,
                function (FormEvent $event) use ($currentPoint) {
                    $form = $event->getForm();
                    $inputUsePoint = $form['plg_use_point']->getData();
                    if ($inputUsePoint > $currentPoint) {
                        $error = new FormError('保有ポイント以内で入力してください');
                        $form['plg_use_point']->addError($error);
                    }
                }
            );
        }

        $builder->get('plg_use_point')->setData($usePoint);
        $builder->get('plg_add_point')->setData($addPoint);
    }

    protected function buildForm($builder)
    {
        $builder->add(
            'plg_use_point',
            'integer',
            array(
                'label' => '利用ポイント',
                'required' => false,
                'mapped' => false,
                'attr' => array(
                    'class' => 'form-control',
                ),
                'constraints' => array(
                    new Assert\GreaterThanOrEqual(array('value' => 0)),
                ),
            )
        )->add(
            'plg_add_point',
            'integer',
            array(
                'label' => '加算ポイント',
                'required' => false,
                'mapped' => false,
                'attr' => array(
                    'class' => 'form-control',
                ),
                'constraints' => array(
                    new Assert\GreaterThanOrEqual(array('value' => 0)),
                ),
            )
        );

        return $builder;
    }

    /**
     * Twigの拡張
     *  - フォーム追加項目を挿入
     * @param TemplateEvent $event
     */
    public function createTwig(TemplateEvent $event)
    {
        $parameters = $event->getParameters();

        $Order = $parameters['Order'];
        $Customer = $Order->getCustomer();

        // 新規受注登録時、会員情報が設定されていない場合はポイント関連の情報は表示しない.
        if (!$Customer instanceof Customer) {
            return;
        }

        $snippet = $this->app->renderView(
            'Point/Resource/template/admin/Event/AdminOrder/order_point.twig',
            array(
                'form' => $parameters['form'],
            )
        );
        $search = '<dl id="product_info_result_box__body_summary"';
        $this->replaceView($event, $snippet, $search);

        // TODO 保有ポイントの表示処理
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $Customer->getId()
        );
        $currentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $Customer->getId(),
            $orderIds
        );
    }

    /**
     * 受注ステータス判定・ポイント更新
     * @param EventArgs $event
     * @return bool
     */
    public function save(EventArgs $event)
    {
        $this->targetOrder = $event->getArgument('TargetOrder');

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
     * 受注削除
     * @param EventArgs $event
     */
    public function delete(EventArgs $event)
    {
        // 必要情報をセット
        $this->targetOrder = $event->getArgument('Order');
        if (empty($this->targetOrder)) {
            return;
        }
        $this->customer = $event->getArgument('Customer');
        if (empty($this->customer)) {
            return;
        }

        // ポイントステータスを削除にする
        $this->history->deletePointStatus($this->targetOrder);

        // 会員ポイントの再計算
        $this->history->refreshEntity();
        $this->history->addEntity($this->targetOrder);
        $this->history->addEntity($this->customer);
        $currentPoint = $this->calculateCurrentPoint();
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $currentPoint,
            $this->customer
        );

        // SnapShot保存
        $point = array();
        $point['current'] = $currentPoint;
        $point['use'] = 0;
        $point['add'] = 0;
        $this->saveAdjustUseOrderSnapShot($point);
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
