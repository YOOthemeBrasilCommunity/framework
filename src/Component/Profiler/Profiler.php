<?php

namespace Pagekit\Component\Profiler;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler as BaseProfiler;

class Profiler extends BaseProfiler
{
    /**
     * @var string[]
     */
    protected $viewCollectorMap = [];

    /**
    * @var Array
    */
    protected $order = [];

    /**
     * Adds a Collector.
     *
     * @param DataCollectorInterface $collector
     * @param string                 $toolbar
     * @param string                 $panel
     * @param int                    $priority
     */
    public function add(DataCollectorInterface $collector, $toolbar = null, $panel = null, $priority = 0)
    {
        $this->viewCollectorMap[$collector->getName()] = compact('toolbar', 'panel');
        $this->order[$collector->getName()] = $priority;

        parent::add($collector);
    }

    /**
     * Returns path to toolbar view.
     *
     * @param  string $name
     * @return string|null
     */
    public function getToolbarView($name)
    {
        return isset($this->viewCollectorMap[$name]) ? $this->viewCollectorMap[$name]['toolbar'] : null;
    }

    /**
     * Returns path to panel view.
     *
     * @param  string $name
     * @return string|null
     */
    public function getPanelView($name)
    {
        return isset($this->viewCollectorMap[$name]) ? $this->viewCollectorMap[$name]['panel'] : null;
    }

    /**
     * Gets the Collectors ordered by priority.
     *
     * @return array An array of collectors
     */
    public function all() {
        arsort($this->order);
        $collectors = [];

        foreach ($this->order as $name => $priority) {
            $collectors[$name] = parent::get($name);
        }

        return $collectors;
    }
}