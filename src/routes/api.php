<?php
use Illuminate\Support\Facades\Route;
Route::group(['prefix' => 'spark-meters'], function () {

    Route::group(['prefix' => 'sm-system'], function () {
        Route::get('/', 'SystemController@show');

    });
    Route::group(['prefix' => 'sm-credential'], function () {
        Route::get('/', 'SmCredentialController@show');
        Route::put('/', 'SmCredentialController@update');
    });
    Route::group(['prefix' => 'sm-meter-model'], function () {
        Route::get('/', 'SmMeterModelController@index');
        Route::get('/sync', 'SmMeterModelController@sync');
        Route::get('/sync-check', 'SmMeterModelController@checkSync');
        Route::get('/count', 'SmMeterModelController@count');

    });
    Route::group(['prefix' => 'sm-customer'], function () {
        Route::get('/', 'SmCustomerController@index');
        Route::get('/sync', 'SmCustomerController@sync');
        Route::get('/sync-check', 'SmCustomerController@checkSync');
        Route::get('/count', 'SmCustomerController@count');

    });
    Route::group(['prefix' => 'sm-tariff'], function () {
        Route::get('/', 'SmTariffController@index');
        Route::put('/', 'SmTariffController@updateInfo');
        Route::get('/information/{tariffId}', 'SmTariffController@getInfo');
        Route::get('/sync', 'SmTariffController@sync');
        Route::get('/sync-check', 'SmTariffController@checkSync');
        Route::get('/count', 'SmTariffController@count');

    });
});

