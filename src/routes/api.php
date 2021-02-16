<?php
use Illuminate\Support\Facades\Route;
Route::group(['prefix' => 'spark-meters'], function () {

    Route::group(['prefix' => 'sm-site'], function () {
        Route::get('/', 'SmSiteController@index');
        Route::get('/sync', 'SmSiteController@sync');
        Route::get('/sync-check', 'SmSiteController@checkSync');
        Route::get('/count', 'SmSiteController@count');
        Route::put('/{site}', 'SmSiteController@update');
        Route::get('/location', 'SmSiteController@location');
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
        Route::get('/connection', 'SmCustomerController@connection');
        Route::put('/{customer}', 'SmCustomerController@update');
        Route::get('/search', 'SmCustomerController@search');

    });
    Route::group(['prefix' => 'sm-tariff'], function () {
        Route::get('/', 'SmTariffController@index');
        Route::put('/', 'SmTariffController@updateInfo');
        Route::get('/information/{tariffId}', 'SmTariffController@getInfo');
        Route::get('/sync', 'SmTariffController@sync');
        Route::get('/sync-check', 'SmTariffController@checkSync');
        Route::get('/count', 'SmTariffController@count');

    });
    Route::group(['prefix' => 'sm-setting'], function () {
        Route::get('/', 'SmSettingController@index');
        Route::group(['prefix' => 'sms-setting'], function () {
            Route::put('/', 'SmSmsSettingController@update');
        });
        Route::group(['prefix' => 'sync-setting'], function () {
            Route::put('/', 'SmSyncSettingController@update');
        });
    });
});

