<?php

declare(strict_types=1);
use Tests\SearchTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(SearchTestCase::class)->in('Search');
