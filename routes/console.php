<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Orders;
use App\Http\Controllers\Api\DriverController;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('SelectDriver {order_id}', function ($order_id) {
    $this->info("Sending email to {$order_id}!");

    $order = Orders::findorFail($order_id);

    $driver = new DriverController;

    $selected_driver = $driver->select_driver($order->amount, $order->latitude, $order->longitude);

//    print_r($selected_driver->id);

    $order->driver_id = $selected_driver->id;
    $order->save();

});




