<?php

use Castor\CodeStyle\ConfigBuilder;

$finder = PhpCsFixer\Finder::create()
    ->in(["src", "tests"])
;

return ConfigBuilder::create('Ledgering', 'ledgering')->build($finder);