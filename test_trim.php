<?php

$output = "Hello World!";
echo "Original: '$output'\n";
echo "Trimmed: '" . trim($output) . "'\n";
echo "Empty?: " . (empty(trim($output)) ? "yes" : "no") . "\n";
echo "Length: " . strlen(trim($output)) . "\n";
