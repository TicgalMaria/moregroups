<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreVCSIgnored(true);

$config = new Config();

$rules = [
    '@PER-CS2.0'                  => true,
    // Required by the CI: a missing trailing comma on the last argument of a
    // multiline call is the most frequent style failure in review.
    'trailing_comma_in_multiline' => ['elements' => ['arguments', 'array_destructuring', 'arrays']],
];

return $config
    ->setRules($rules)
    ->setFinder($finder)
    ->setUsingCache(false);