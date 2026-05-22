<?php

declare(strict_types=1);

use Castor\Docs\Documentation;

return static function (Documentation $docs): void {
    $docs->addComposerJsonPath(__DIR__ . '/../../composer.json');
    $docs->section('Introduction', [
        'index.md',
        'getting-started.md',
    ]);
    $docs->section('Concepts', [
        'domain-model.md',
        'working-with-the-library.md',
    ]);
    $docs->section('Guides', [
        'guides/preventing-overdrafts-and-overpayments.md',
        'guides/automatic-balance-calculations.md',
        'guides/two-phase-payments.md',
        'guides/currency-exchange.md',
        'guides/loan-management.md',
    ]);
};
