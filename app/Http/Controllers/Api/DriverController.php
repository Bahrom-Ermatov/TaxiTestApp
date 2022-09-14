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
    /**
    * @OA\Get(
    * path="/api/driver/select/{amount}/{latitude}/{longitude}",
    * summary="Выбор водителя",
    * description="Выбор водителя",
    * operationId="selectDriver",
    * tags={"selectDriver"},
    * @OA\Parameter(
    *    description="Сумма заказа",
    *    in="path",
    *    name="amount",
    *    required=true,
    *    example="100",
    *    @OA\Schema(
    *       type="integer",
    *       format="int64"
    *    )
    * ),
    * @OA\Parameter(
    *    description="Latitude",
    *    in="path",
    *    name="latitude",
    *    required=true,
    *    example="40.304213",
    *    @OA\Schema(
    *       type="string"
    *    )
    * ),
    * @OA\Parameter(
    *    description="Longitude",
    *    in="path",
    *    name="longitude",
    *    required=true,
    *    example="69.632049",
    *    @OA\Schema(
    *       type="string"
    *    )
    * ),
    * @OA\Response(
    *    response=200,
    *    description="Success",
    *    @OA\JsonContent(
    *       @OA\Property(property="data", type="array",
    *           @OA\Items(ref="#/")
    *        )
    *     )
    * ),
    * @OA\Response(
    *    response=400,
    *    description="Ошибка при выборе водителя",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Возникла ошибка при выборе водителя")
    *        )
    *     )
    * )
    */

    public function select_driver($amount, $latitude, $longitude)
    {
        try {
            $drivers = Drivers::where('stat_id', 1)
            ->where('balance', '>=', $amount/10)
            ->get();

            if ($drivers->count()==0) {
                return response()->json([
                    'status' =>false,
                    'message' => 'Водители не найдены',
                ], 400);
            }

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
                    return response()->json([
                        'status' =>false,
                        'message' => 'Ошибка при получении дистанции',
                    ], $results[0]);
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

        }catch(\Exception $e) {
            return response()->json([
                'status' =>false,
                'message' =>$e->getMessage()
            ], 500);
        }
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


    /**
    * @OA\Post(
    * path="/api/driver/charge-commission",
    * summary="Биллинг - Начисление коммиссии",
    * description="Биллинг - Начисление коммиссии",
    * operationId="chargeCommission",
    * tags={"chargeCommission"},
    * @OA\RequestBody(
    *    required=true,
    *    description="Параметры",
    *    @OA\JsonContent(
    *       required={"driver_id","amount", "commission"},
    *       @OA\Property(property="driver_id", type="integer", format="int64", example="1"),
    *       @OA\Property(property="amount", type="integer", format="int64", example="100"),
    *       @OA\Property(property="commission", type="integer", format="int64", example="1"),
    *    ),
    * ),
    * @OA\Response(
    *    response=200,
    *    description="Success",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Коммиссия успешно начислена")
    *     )
    * ),
    * @OA\Response(
    *    response=400,
    *    description="Ошибка при начислении коммиссии",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Возникла ошибка при начислении коммиссии")
    *        )
    *     )
    * )
    */

    public function charge_commission(Request $request)
    {
        try {        
            $company_id = 1;
            $driver = Drivers::findorFail($request->driver_id);
            $company = Companies::findorFail($company_id);
    
            $commission_sum = $request->amount * $request->commission / 100;
    
            $driver_balance = $driver->balance;
    
            if ($driver_balance < $commission_sum) {
                return response()->json([
                    'status' =>false,
                    'message' => 'Недостаточно средств на счету у водителя',
                ], 400);
            }
    
            //Снимаем коммиссию со счета водителя и переводим на счет компании
            try{
                DB::beginTransaction();
                $driver->balance = $driver_balance - $commission_sum;
    
                $change_balance = new ChangeBalanceCompany;
                $change_balance->company_id = $company_id;
                $change_balance->change_sum = $commission_sum;
                $change_balance->created_at = date("Y-m-d H:i:s");
                $change_balance->updated_at = date("Y-m-d H:i:s");
                
                $driver->save();
                $change_balance->save();
                
                DB::commit();
                return response()->json([
                    'status' =>true,
                    'message' => 'Коммиссия успешно начислена',
                ], 200);
    
            }catch(\Exception $e){
                DB::rollback();
                return response()->json([
                    'status' =>false,
                    'message' => 'Ошибка при начислении коммиссии',
                    'errors' => $e->getMessage()
                ], 400);
    
            }    
        }catch(\Exception $e) {
            return response()->json([
                'status' =>false,
                'message' =>$e->getMessage()
            ], 500);
        }
    }

    /**
    * @OA\Post(
    * path="/api/driver/register",
    * summary="Регистрация водителя",
    * description="Регистрация водителя",
    * operationId="registerDriver",
    * tags={"registerDriver"},
    * security={ {"sanctum": {} }},
    * @OA\RequestBody(
    *    required=true,
    *    description="Параметры",
    *    @OA\JsonContent(
    *       required={"first_name","last_name", "login", "balance", "latitude", "longitude", "rating"},

    *       @OA\Property(property="first_name", type="string", example="Bahrom"),
    *       @OA\Property(property="last_name", type="string", example="Ermatov"),
    *       @OA\Property(property="login", type="string", example="bahrom"),
    *       @OA\Property(property="balance", type="float",  example="0"),
    *       @OA\Property(property="latitude", type="string",  example="40.289541"),
    *       @OA\Property(property="longitude", type="string",  example="69.632049"),
    *       @OA\Property(property="rating", type="float", example="0.5"),
    *    ),
    * ),
    * @OA\Response(
    *    response=200,
    *    description="Success",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Водитель успешно зарегистрирован")
    *     )
    * ),
    * @OA\Response(
    *    response=400,
    *    description="Ошибка при регистрации водителя",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Возникла ошибка при регистрации водителя")
    *        )
    *     )
    * )
    */

    public function create_driver (Request $request) {
        try {

            $driver = new Drivers;
            $driver->first_name = $request->first_name;
            $driver->last_name = $request->last_name;
            $driver->login = $request->login;
            $driver->stat_id = 1;
            $driver->balance = $request->balance;
            $driver->latitude = $request->latitude;
            $driver->longitude = $request->longitude;
            $driver->rating = $request->rating;
            $driver->save();

            return response()->json([
                'status' => true,
                'message' => 'Driver created Successfully',
                'driver' => $driver
            ], 200);

        }catch(\Exception $e) {
            return response()->json([
                'status' =>false,
                'message' =>$e->getMessage()
            ], 500);
        }
    }
}
