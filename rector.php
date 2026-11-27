<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\Class_\DescriptionPropertyToDescriptionAttributeRector;
use RectorLaravel\Rector\Class_\EmptyGuardedPropertyToUnguardedAttributeRector;
use RectorLaravel\Rector\Class_\SignaturePropertyToSignatureAttributeRector;
use RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        EmptyGuardedPropertyToUnguardedAttributeRector::class,
        SignaturePropertyToSignatureAttributeRector::class,
        DescriptionPropertyToDescriptionAttributeRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
    )
    ->withPhpSets(php83: true)
    ->withComposerBased(laravel: true)
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_IF_HELPERS,
    ])
    ->withConfiguredRule(RemoveDumpDataDeadCodeRector::class, ['dd', 'dump']);
