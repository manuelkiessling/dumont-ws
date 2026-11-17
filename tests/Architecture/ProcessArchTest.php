<?php

declare(strict_types=1);

// A run ends by returning, never by ending the process it runs in.
arch('no code of the application ends the process it runs in')
    ->expect('App')
    ->not->toUse(['exit', 'die']);
