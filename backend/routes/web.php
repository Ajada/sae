<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

Route::get('/cript', function () {
    // return view('welcome');
    dd(
        Crypt::encryptString('D#9PxLMGJIwt;7?GCoMExuP>z3>R0!tnL21mV')
    );
});
