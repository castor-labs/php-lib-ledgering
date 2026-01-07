<?php

use Castor\CodeStyle\ConfigBuilder;

$finder = PhpCsFixer\Finder::create()
    ->in(["src", "tests"])
    ->notName('definition.php')
;

return ConfigBuilder::create('Ledgering', 'ledgering')->build($finder);