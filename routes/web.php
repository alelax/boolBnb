<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();


Route::middleware('isLogged')->group(function (){
    Route::get('/home', 'UserPanelController@index')->name('home');
    Route::get('/user-logged-apartment-detail/{apartment_id}', 'UserPanelController@showApartmentDetail')->name('ownerApartmentDetails');

});

Route::post('/test', 'TestController@index')->name('test');

Route::resource('apartaments', 'ApartamentController');
