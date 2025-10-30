<?php

require 'vendor/autoload.php';

use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;

$display = DisplayBuilder::default()->build();
$display->clear();
$display->draw(ParagraphWidget::fromString('Hello World'));
