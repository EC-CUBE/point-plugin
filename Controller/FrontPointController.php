<?php

namespace Plugin\Point\Controller;

use Eccube\Application;
use Plugin\Point\Form\Type;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ポイント設定画面用コントローラー
 * Class FrontPointController
 * @package Plugin\Point\Controller
 */
class FrontPointController
{
    /** @var Application */
    protected $app;

    /**
     * FrontPointController constructor.
     */
    public function __construct()
    {
        $this->app = \Eccube\Application::getInstance();
    }

    /**
     * 利用ポイント入力画面
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function usePoint(Application $app, Request $request)
    {
        // 権限判定
        if (!$this->app->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException;
        }

        // カートサービス取得
        $cartService = $this->app['eccube.service.cart'];

        // カートチェック
        if (!$cartService->isLocked()) {
            // カートが存在しない、カートがロックされていない時はエラー
            return $this->app->redirect($this->app->url('cart'));
        }

        // 受注情報が取得される
        $Order = $this->app['eccube.service.shopping']->getOrder($this->app['config']['order_processing']);

        // 受注情報がない場合はエラー表示
        if (!$Order) {
            $this->app->addError('front.shopping.order.error');

            return $this->app->redirect($this->app->url('shopping_error'));
        }

        // 最終仮利用ポイントがあるかどうかの判定
        $lastPreUsePoint = -($this->app['eccube.plugin.point.repository.point']->getLatestPreUsePoint($Order));

        // 計算用ヘルパー呼び出し
        $calculator = $this->app['eccube.plugin.point.calculate.helper.factory'];
        // 計算ヘルパー取得判定
        if (empty($calculator)) {
            return false;
        }

        // 必要エンティティ取得
        // カスタマーエンティティ
        $customer = $this->app['security']->getToken()->getUser();

        // 計算に必要なエンティティを格納
        $calculator->addEntity('Customer', $customer);
        $calculator->addEntity('Order', $Order);

        // 保有ポイント
        $point = $calculator->getPoint();
        if (empty($point)) {
            $point = 0;
        }

        // 加算ポイント
        $addPoint = $calculator->getAddPointByOrder();

        // ポイント換算レート
        $pointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        if (empty($pointInfo)) {
            return false;
        }
        $pointRate = $pointInfo->getPlgPointConversionRate();
        if (empty($pointInfo)) {
            return false;
        }


        //フォーム生成
        $form = $this->app['form.factory']
            ->createBuilder()->add(
                'plg_use_point',
                'text',
                array(
                    'label' => '利用ポイント',
                    'required' => false,
                    'mapped' => false,
                    'empty_data' => '',
                    'data' => $lastPreUsePoint,
                    'attr' => array(
                        'placeholder' => '使用するポイントを入力 例. 1',
                    ),
                    'constraints' => array(
                        new Assert\LessThanOrEqual(
                            array(
                                'value' => $point,
                                'message' => '利用ポイントは保有ポイント以内で入力してください。',
                            )
                        ),
                        new Assert\Regex(
                            array(
                                'pattern' => "/^\d+$/u",
                                'message' => 'form.type.numeric.invalid',
                            )
                        ),
                    ),
                )
            )->getForm();

        $form->handleRequest($request);

        // POST値取得
        $usePoint = $form->get('plg_use_point')->getData();
        // 利用ポイントをセット
        $calculator->setUsePoint($usePoint);


        // 合計金額がマイナスかどうかを判定
        $errorFlg = false;

        if (!$this->app['eccube.service.shopping']->isDiscount($Order, $calculator->getconversionpoint())) {
            $errorFlg = true;
        }

        // 保存処理
        if ($form->isSubmitted() && $form->isValid() && !$errorFlg) {
            // 最終保存ポイントと現在ポイントに相違があれば利用ポイント保存
            if ($lastPreUsePoint != $usePoint) {
                if ($calculator->setDiscount($lastPreUsePoint)) {
                    $newOrder = $calculator->getEntity('Order');
                    // 値引き計算後のオーダーが返却
                    $newOrder = $this->app['eccube.service.shopping']->calculatePrice($newOrder);

                    // 履歴情報登録
                    // 利用ポイント
                    // 再入力時は、以前利用ポイントを打ち消し
                    if (!empty($lastPreUsePoint)) {
                        $this->app['eccube.plugin.point.history.service']->addEntity($Order);
                        $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
                        $this->app['eccube.plugin.point.history.service']->savePreUsePoint($lastPreUsePoint);
                    }
                    // ユーザー入力値保存
                    $this->app['eccube.plugin.point.history.service']->refreshEntity();
                    $this->app['eccube.plugin.point.history.service']->addEntity($Order);
                    $this->app['eccube.plugin.point.history.service']->addEntity($Order->getCustomer());
                    $this->app['eccube.plugin.point.history.service']->savePreUsePoint($usePoint * -1);

                    // 現在ポイントを履歴から計算
                    $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
                        $Order->getCustomer()->getId()
                    );
                    $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
                        $Order->getCustomer()->getId(),
                        $orderIds
                    );

                    if ($calculateCurrentPoint < 0) {
                        // TODO: ポイントがマイナス！
                    }

                    // 会員ポイント更新
                    $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
                        $calculateCurrentPoint,
                        $Order->getCustomer()
                    );

                    $this->app['orm.em']->persist($newOrder);
                    $this->app['orm.em']->flush();
                }
            }

            return $this->app->redirect($this->app->url('shopping'));
        }

        // 合計金額を取得
        $total = $Order->getTotal();

        // 合計金額エラー
        $errors = array();
        if ($errorFlg) {
            $form['plg_use_point']->addError(new FormError('計算でマイナス値が発生します。入力を確認してください。'));
        }

        return $app->render(
            'Point/Resource/template/default/point_use.twig',
            array(
                'form' => $form->createView(),  // フォーム
                'usePoint' => $usePoint,        // 利用ポイント
                'pointRate' => $pointRate,      // 換算レート
                'point' => $point - $usePoint,  // 保有ポイント
                'total' => $total,              // 合計金額
            )
        );
    }
}
