<?php
/**
 * Generic widget renderer for AJAX refreshes.
 *
 * This view takes the widget name, parses it for a plugin,
 * and then renders the correct widget element.
 *
 * @var \App\View\AppView $this
 * @var mixed $data
 * @var mixed $widgetName
 */
$this->loadHelper('Crustum/Rhythm.Rhythm');
$this->loadHelper('Crustum/Rhythm.Chart');
echo $this->Rhythm->widget($widgetName, $data);
