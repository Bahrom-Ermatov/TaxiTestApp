<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Create User
     * @param Request $request
     * @return User
     */

    /**
    * @OA\Post(
    * path="/api/auth/register",
    * summary="Регистрация пользователя",
    * description="Регистрация пользователя",
    * operationId="registerUser",
    * tags={"registerUser"},
    * @OA\RequestBody(
    *    required=true,
    *    description="Параметры",
    *    @OA\JsonContent(
    *       required={"name","email", "password"},

    *       @OA\Property(property="name", type="string", example="bahrom"),
    *       @OA\Property(property="email", type="string", format="email", example="bahrom.ermatov@gmail.com"),
    *       @OA\Property(property="password", type="string", format="password", example="Observer1"),
    *    ),
    * ),
    * @OA\Response(
    *    response=200,
    *    description="Success",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Пользователь успешно зарегистрирован")
    *     )
    * ),
    * @OA\Response(
    *    response=400,
    *    description="Ошибка при регистрации водителя",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Возникла ошибка при регистрации пользоватея")
    *        )
    *     )
    * )
    */

    public function createUser (Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(),
            [
                'name' =>'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required'
            ]);

            if($validateUser->fails()) {
                return response()->json([
                    'status' =>false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 400);
            }

            $user = User::create([
                'name' =>$request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User created Successfully',
                'token' => $user->createToken('API TOKEN')->plainTextToken
            ], 200);

        } catch (\Throwable $th){
            return response()->json([
                'status' =>false,
                'message' =>$th->getMessage()
            ], 500);
        }

    }

    /**
     * Login the User
     * @param Request $request
     * @return User
     */

        /**
     * Create User
     * @param Request $request
     * @return User
     */

    /**
    * @OA\Post(
    * path="/api/auth/login",
    * summary="Авторизация пользователя",
    * description="Авторизация пользователя",
    * operationId="loginUser",
    * tags={"loginUser"},
    * @OA\RequestBody(
    *    required=true,
    *    description="Параметры",
    *    @OA\JsonContent(
    *       required={"email", "password"},

    *       @OA\Property(property="email", type="string", format="email", example="bahrom.ermatov@gmail.com"),
    *       @OA\Property(property="password", type="string", format="password", example="Observer1"),
    *    ),
    * ),
    * @OA\Response(
    *    response=200,
    *    description="Success",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Пользователь успешно авторизовался")
    *     )
    * ),
    * @OA\Response(
    *    response=400,
    *    description="Ошибка при регистрации водителя",
    *    @OA\JsonContent(
    *       @OA\Property(property="message", type="string", example="Возникла ошибка при авторизации пользоватея")
    *        )
    *     )
    * )
    */
    public function loginUser (Request $request){
        try {
            $validateUser = Validator::make($request->all(),
            [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if($validateUser->fails()) {
                return response()->json([
                    'status' =>false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 400);
            }

            if (!Auth::attempt($request->only(['email', 'password']))){
                return response()->json([
                    'status' =>false,
                    'message' => 'Email or password does not match'
                ], 400);
            };

            $user = User::where('email', $request->email)->first();

            return response()->json([
                'status' => true,
                'message' => 'User Logged in Successfully',
                'token' => $user->createToken('API TOKEN')->plainTextToken
            ], 200);

        } catch (Throwable $th){
            return response()->json([
                'status' =>false,
                'message' =>$th->getMessage()
            ], 500);
        }
    }
}
