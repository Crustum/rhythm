<?php
declare(strict_types=1);

namespace Rhythm\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Exception;
use Rhythm\Rhythm;
use Rhythm\Widget\WidgetRegistry;

/**
 * Dashboard Controller
 */
class DashboardController extends Controller
{
    /**
     * Rhythm instance
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Widget registry
     *
     * @var \Rhythm\Widget\WidgetRegistry
     */
    protected WidgetRegistry $widgetRegistry;

    /**
     * Index action - Main new system dashboard
     *
     * @return void
     */
    public function index(Rhythm $rhythm): void
    {
        $this->initializeWidgets($rhythm);

        $layout = $this->request->getQuery('layout', 'default');
        $layouts = Configure::read('Rhythm.layouts', []);

        if (!isset($layouts[$layout])) {
            $layout = 'default';
        }

        $this->set('widgetRegistry', $this->widgetRegistry);
        $this->set('currentLayout', $layout);
        $this->set('availableLayouts', $layouts);
        $this->set('layoutConfig', $layouts[$layout] ?? []);
        $this->viewBuilder()->setLayout('Rhythm.dashboard');
    }

    /**
     * Widget refresh action - Compatible with existing dashboard.js
     *
     * @return void
     */
    public function widget(Rhythm $rhythm): void
    {
        $this->initializeWidgets($rhythm);
        $this->set('widgetRegistry', $this->widgetRegistry);

        $widgetName = $this->request->getParam('pass.0');
        $period = (int)$this->request->getQuery('period', 60);
        $sort = $this->request->getQuery('sort');

        if (!$widgetName) {
            throw new BadRequestException('Widget name is required');
        }

        if (!$this->widgetRegistry->has($widgetName)) {
            throw new BadRequestException('Widget not found: ' . $widgetName);
        }

        $widget = $this->widgetRegistry->get($widgetName);

        $options = ['period' => $period];
        if ($sort !== null) {
            $options['sort'] = $sort;
        }
        $data = $widget->getData($options);
        $this->set(compact('data'));
        $this->set('widget', $widget);
        $this->set('widgetName', $widgetName);

        $this->viewBuilder()->setLayout('ajax');
    }

    /**
     * Refresh all widgets action
     *
     * @return void
     */
    public function refresh(Rhythm $rhythm): void
    {
        $this->initializeWidgets($rhythm);
        $period = (int)$this->request->getQuery('period', 60);
        $sort = $this->request->getQuery('sort');

        $options = ['period' => $period];
        if ($sort !== null) {
            $options['sort'] = $sort;
        }

        $layout = $this->request->getQuery('layout', 'default');
        $layouts = Configure::read('Rhythm.layouts', []);

        if (!isset($layouts[$layout])) {
            $layout = 'default';
        }

        $this->set('widgetRegistry', $this->widgetRegistry);
        $this->set('currentLayout', $layout);
        $this->set('availableLayouts', $layouts);
        $this->set('layoutConfig', $layouts[$layout] ?? []);
        $this->viewBuilder()->setLayout('ajax');
        $this->viewBuilder()->setTemplate('Dashboard/index');
    }

    /**
     * Initialize widgets with Rhythm instance
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @return void
     */
    protected function initializeWidgets(Rhythm $rhythm): void
    {
        $this->rhythm = $rhythm;
        $this->widgetRegistry = new WidgetRegistry($this->rhythm);

        $widgetConfigs = Configure::read('Rhythm.widgets');

        if (empty($widgetConfigs)) {
            return;
        }

        foreach ($widgetConfigs as $name => $config) {
            if (isset($config['className'])) {
                $this->widgetRegistry->register($name, $config['className'], $config);
            }
        }

        $this->set('widgetConfigs', $widgetConfigs);
    }

    /**
     * Test method that throws NotFoundException
     *
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function fail(): void
    {
        throw new NotFoundException('Test exception');
    }

    /**
     * Test method that throws Exception
     *
     * @return void
     * @throws \Exception
     */
    public function fail2(): void
    {
        throw new Exception('Test 2 exception');
    }
}
