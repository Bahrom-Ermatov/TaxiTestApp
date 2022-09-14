<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Drivers;
use App\Models\Companies;
use App\Models\ChangeBalanceCompany;
use App\Http\Resources\DriverResource;
use Illuminate\Support\Facades\DB;

class DriverController extends Controller
{
    public function select_driver($amount, $latitude, $longitude)
    {
        $drivers = Drivers::where('stat_id', 1)
                        ->where('balance', '>=', $amount/10)
                        ->get();

        $header=['Content-Type:application/json']; //Определяем значение хидера
        $data = "";
        $method = "GET";
        $min_distance = -1;

        //Перебираем водителей
        //Для каждого из них вычисляаем расстояние до точки заказа
        //А также вычисляем минимальное расстояние
        foreach($drivers as $key => $value) {

            $link = "http://router.project-osrm.org/route/v1/driving/".$longitude.",".$latitude.";".$value['longitude'].",".$value['latitude']."?overview=false";
            $results = $this->send_request($link, $header, $data, $method);

            //Проверяем код ошибки
            if ($results[0]!=200) {
                exit ("Ошибка при получении дистанции");
            }

            //Получаем дистанцию от водителя до точки заказа
            $distance = json_decode($results[1], true)['routes'][0]['distance'];

            //Зафиксируем минимальную дистанцию
            if ($min_distance==-1 || $min_distance > $distance) {
                $min_distance = $distance;
            }
            
    
            $drivers[$key]['distance'] = $distance;
            error_log($drivers[$key]);
        }

        $rating = -1;

        //Выбираем самых близких водителей и из них выбираем того у кого самый высокий рейтинг
        foreach ($drivers as $key => $value) {
            if ($min_distance/$value['distance']>0.9) {
                if ($value['rating']>$rating) {
                    $selected_driver = $drivers[$key];
                }
            }
        }

        return $selected_driver;
    }

    public function send_request($link, $header, $data, $method)
    {
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array( $code, $out ); 
    }  
    public function charge_commission($driver_id, $amount, $commission)
    {
        $company_id = 1;
        $driver = Drivers::findorFail($driver_id);
        $company = Companies::findorFail($company_id);

        $commission_sum = $amount * $commission / 100;

        $driver_balance = $driver->balance;

        if ($driver->balance < $commission_sum) {
            return "Недостаточно средств на счету у водителя";    
        }

        DB::beginTransaction();
        $driver->balance = $driver_balance - $commission_sum;
        $driver->save();

        DB::rollback();

        DB::beginTransaction();
        $change_balance = new ChangeBalanceCompany;
        $change_balance->company_id = $company_id;
        $change_balance->change_sum = $commission_sum;
        $change_balance->created_at = date("Y-m-d H:i:s");
        $change_balance->updated_at = date("Y-m-d H:i:s");
        
        $change_balance->save();

        DB::commit();

        return $company;
    }
}
