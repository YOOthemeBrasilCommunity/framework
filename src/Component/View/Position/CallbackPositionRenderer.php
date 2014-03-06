<?php

namespace Pagekit\Component\View\Position;

use Pagekit\Component\View\Widget\WidgetProvider;

class CallbackPositionRenderer implements PositionRendererInterface
{
    /**
     * @var string
     */
    protected $callback;

    /**
     * Constructor.
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function render($position, WidgetProvider $provider, \ArrayObject $widgets, array $options = array())
    {
        return call_user_func($this->callback, $position, $provider, $widgets, $options);
    }
}
