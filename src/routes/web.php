<?php

use Illuminate\Support\Facades\Route;

Route::get('spark', static function (){

    $aa=[
        'name'=>"kamuran"
    ];
    return json_encode($aa);
});
