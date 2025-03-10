<?php

namespace App\Http\Controllers;

use App\Mail\PostCreatedMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Resend\Laravel\Facades\Resend;

class AuthController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('America/Guayaquil');
    }
    public function register(Request $request)
    {
        try {
            if (!$request->ci || !$request->nombres || !$request->apellidos || !$request->email || !$request->password || !$request->confirm_password) {
                return response()->json(['status' => "error", 'message' => "Todos los campos son requeridos"], 422);
            }
            $verify_email = User::where('email', $request->email)->first();
            if ($verify_email) {
                return response()->json(['status' => 'error', 'message' => "El email ya existe"], 422);
            }
            $verify_ci = User::where('ci', $request->ci)->first();
            if ($verify_ci) {
                return response()->json(['status' => 'error', 'message' => "El ci ya existe"], 422);
            }
            $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
            if (!preg_match($pattern, $request->password)) {
                return response()->json(['status' => "error",  'message' => "Password invalido, recuerde la contrasena tiene que ser de 8 caracteres en adelante, tiene que tener una minuscula, una mayuscula, un numero ,un caracter especial (@$!%*?&)  "], 422);
            }

            if ($request->password != $request->confirm_password) {
                return response()->json(['status' => "error", 'message' => "Las contraseñas no coinciden"], 422);
            }

            $user = User::create([
                'ci' => $request->ci,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);
            return response()->json(["status" => "success", 'message' => "Usuario creado correctamente"], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => "Ocurrio un error", "error" => $e->getMessage()], 422);
        }
    }

    public function login()
    {
        try {
            $credentials = request(['email', 'password']);

            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => "Credenciales inválidas"], 401);
            }

            if ($user->is_blocked) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Usuario bloqueado por múltiples intentos fallidos. Por favor contacte al administrador."
                ], 401);
            }

            $token = JWTAuth::attempt($credentials);

            if (!$token) {
                $user->login_attempts += 1;
                if ($user->login_attempts >= 5) {
                    $user->is_blocked = true;
                    $user->blocked_at = now();
                    $message = "Usuario bloqueado por múltiples intentos fallidos. Por favor contacte al administrador.";
                } else {
                    $intentos = 5 - $user->login_attempts;
                    $message = "Credenciales inválidas. Intentos restantes: " . $intentos;
                }

                $user->save();
                return response()->json(['status' => 'error', 'message' => $message], 401);
            }

            $user->login_attempts = 0;
            $user->save();

            return response()->json(['token' => $token], 200);
        } catch (JWTException $e) {
            return response()->json(['status' => 'error', 'message' => "Ocurrió un error"], 422);
        }
    }

    public function getUser()
    {
        $user = Auth::user();
        return response()->json($user, 200);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['status' => 'success', 'message' => "Sesion cerrada"], 200);
        } catch (JWTException $e) {
            return response()->json(['status' => 'error', 'message' => "Ocurrio un error"], 422);
        }
    }

    public function searchUsers(Request $request)
    {
        try {
            $query = User::query();

            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('ci', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('nombres', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('apellidos', 'LIKE', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(nombres, ' ', apellidos) LIKE ?", ["%{$searchTerm}%"]);
                });
            }

            $users = $query->get();
            return response()->json($users, 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'No encontramos un usuario con ese correo electrónico'], 404);
            }

            $lastToken = DB::table('password_reset_tokens')->where('email', $request->email)->latest('id')->first();
            if ($lastToken) {
                $minutesDiff = Carbon::parse($lastToken->created_at)->diffInMinutes(now());
                if ($minutesDiff < 60) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Ya se ha enviado un correo electrónico recientemente. Por favor, espere antes de volver a intentarlo.'
                    ], 400);
                }
            }
            $number1 = rand(0, 9);
            $number2 = rand(0, 9);
            $number3 = rand(0, 9);
            $number4 = rand(0, 9);
            $token = $number1 . $number2 . $number3 . $number4;

            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'token' => $token,
            ]);

            Mail::to($request->email)->send(new PostCreatedMail($token));
            return response()->json([
                'status' => 'success',
                'message' => 'Hemos enviado un enlace de recuperación a su correo electrónico'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
    public function verifyToken(Request $request)
    {
        try {
            $lastToken = DB::table('password_reset_tokens')->where('email', $request->email)->where('token', $request->token)->latest('id')->first();
            if (!$lastToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token invalido'
                ], 400);
            }

            $minutesDiff = Carbon::parse($lastToken->created_at)->diffInMinutes(now());
            if ($minutesDiff < 60) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Token valido'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token expirado'
                ], 400);
            }
        } catch (\Exception $e) {
            return  response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }


    public function resetAccountPass(Request $request)
    {
        try {
            $lastToken = DB::table('password_reset_tokens')->where('email', $request->email)->where('token', $request->token)->latest('id')->first();
            if (!$lastToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token invalido'
                ], 400);
            }
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'No encontramos un usuario con ese correo electrónico'], 404);
            }
            $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
            if (!preg_match($pattern, $request->password)) {
                return response()->json(['status' => "error", 'message' => "Password invalido, recuerde la contrasena tiene que ser de 8 caracteres en adelante, tiene que tener una minuscula, una mayuscula, un numero,un caracter especial (@$!%*?&)"], 422);
            }
            if ($request->password != $request->confirm_password) {
                return response()->json(['status' => "error", 'message' => "Las contraseñas no coinciden"], 422);
            }
            $user->is_blocked = 0;
            $user->password = bcrypt($request->password);
            $user->save();
            return response()->json(['status' => 'success', 'message' => 'Contraseña actualizada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
