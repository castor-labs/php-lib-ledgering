<?php

use Castor\CodeStyle\ConfigBuilder;

$finder = PhpCsFixer\Finder::create()
    ->in(["src", "tests", "examples"])
    ->notName('definition.php')
;

return ConfigBuilder::create('Ledgering', 'ledgering')->build($finder);