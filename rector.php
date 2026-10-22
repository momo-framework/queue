<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
    ])
    // Inlining `/** @var T $x */ $x = ...; return $x;` leaves the @var
    // behind and drops the type PHPStan needed from it.
    ->withSkip([
        SimplifyUselessVariableRector::class,
    ]);
