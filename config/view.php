<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Where the Blade templates live. This matches the framework default; it is
    | repeated here only because this file has to stand on its own.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Deliberately storage_path() and not realpath(storage_path(...)).
    |
    | The framework default wraps this in realpath(), which returns false when
    | the directory does not exist yet. On a fresh checkout — CI, or anyone
    | cloning this repository — storage/framework/views is absent, because it
    | holds compiled templates and is correctly left out of version control.
    | The empty path then reaches the Blade compiler and every view-rendering
    | test fails with "Please provide a valid cache path".
    |
    | storage_path() always yields a usable path, and the compiler creates the
    | directory itself on first write, so a fresh clone renders views fine.
    |
    */

    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];
