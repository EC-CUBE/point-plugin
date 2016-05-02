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
 *  - 拡張元 : 商品登録( 編集 )
 *  - 拡張項目 : 商品毎ポイント付与率( 編集 )
 * Class AdminProduct
 * @package Plugin\Point\Event\WorkPlace
 */
class  AdminProduct extends AbstractWorkPlace
{
    /**
     * 商品フォームポイント付与率項目追加
     * @param FormBuilder $builder
     * @param Request $request
     */
    public function createForm(FormBuilder $builder, Request $request)
    {
        $productId = $builder->getForm()->getData()->getId();

        // 登録済み情報取得処理
        $lastPointProduct = null;
        if (!is_null($productId)) {
            $lastPointProduct = $this->app['eccube.plugin.point.repository.pointproductrate']->getLastPointProductRateById(
                $productId
            );
        }

        // ポイント付与率項目拡張
        $builder
            ->add(
                'plg_point_product_rate',
                'integer',
                array(
                    'label' => 'ポイント付与率',
                    'required' => false,
                    'mapped' => false,
                    'data' => $lastPointProduct,
                    'attr' => array(
                        'placeholder' => '設定されていると本商品のみ設定値をもとにポイントを計算します。',
                    ),
                    'constraints' => array(
                        new Assert\Regex(
                            array(
                                'pattern' => "/^\d+$/u",
                                'message' => 'form.type.numeric.invalid',
                            )
                        ),
                        new Assert\Range(
                            array(
                                'min' => 0,
                                'max' => 100,
                            )
                        ),
                    ),
                )
            );
    }

    /**
     * 商品毎ポイント付与率保存
     * @param EventArgs $event
     * @return bool
     */
    public function save(EventArgs $event)
    {
        // フォーム情報取得処理
        $form = $event->getArgument('form');

        if (empty($form)) {
            return false;
        }

        // ポイント付与率取得
        $pointRate = $form->get('plg_point_product_rate')->getData();

        // 商品ID取得
        $productId = $form->getData()->getId();
        if(empty($productId)){
            $productId = 0;
        }

        // 前回入力値と比較
        $status = $this->app['eccube.plugin.point.repository.pointproductrate']
            ->isSamePoint($pointRate, $productId);

        // 前回入力値と同じ値であれば登録をキャンセル
        if ($status) {
            return true;
        }

        // プロダクトエンティティを取得
        $product = $event->getArgument('Product');

        if (empty($product)) {
            return false;
        }

        // ポイント付与保存処理
        $this->app['eccube.plugin.point.repository.pointproductrate']->savePointProductRate($pointRate, $product);
    }
}
