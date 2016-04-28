<?php


namespace Plugin\Point\Helper\EventRoutineWorksHelper;

use \Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Plugin\Point\Event\WorkPlace\AbstractWorkPlace;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * フックポイント定型処理を保持オブジェクトに移譲
 * Class EventRoutineWorksHelper
 * @package Plugin\Point\Helper\EventRoutineWorksHelper
 */
class EventRoutineWorksHelper
{
    /**
     * @var AbstractWorkPlace
     */
    protected $place;

    /**
     * EventRoutineWorksHelper constructor.
     * @param AbstractWorkPlace $place
     */
    public function __construct(AbstractWorkPlace $place)
    {
        $this->app = \Eccube\Application::getInstance();
        $this->place = $place;
    }

    /**
     * フォーム拡張
     * @param FormBuilder $builder
     * @param Request $request
     * @return mixed
     */
    public function createForm(FormBuilder $builder, Request $request)
    {
        return $this->place->createForm($builder, $request);
    }

    /**
     * 画面描画拡張
     * @param Request $request
     * @param Response $response
     */
    public function renderView(Request $request, Response $response)
    {
        $this->place->renderView($request, $response);
    }

    /**
     * Twig拡張
     * @param TemplateEvent $event
     */
    public function createTwig(TemplateEvent $event)
    {
        $this->place->createTwig($event);
    }

    /**
     * データ保存拡張
     * @param EventArgs $event
     */
    public function save(EventArgs $event)
    {
        $this->place->save($event);
    }
}
