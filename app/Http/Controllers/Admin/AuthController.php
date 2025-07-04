<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
     */

    // use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('guest:admin')->except('logout');
        $this->middleware('guest:admin')->except('login');
    }


   /**
    * The function showAdminLoginForm displays the admin login form view in PHP.
    * 
    * view named 'auth.login' is being returned with the data array containing the key 'url'
    * with the value 'admin'.
    */
    public function showAdminLoginForm()
    {
        return view('auth.login', ['url' => 'admin']);
    }

   /**
    * The function handles user login authentication for an admin account in a PHP application.
    * 
    * @param Request request The `Request ` parameter in the `login` function is an instance of
    * the Illuminate\Http\Request class. It represents the HTTP request that is being made to the
    * server.
    * 
    * @return If the login attempt for the admin user is successful, the function will redirect the
    * user to the admin dashboard. If the login attempt fails, it will update the last login timestamp
    * for the authenticated user and return the user back to the login page with the email field
    * pre-filled and the remember checkbox status retained. ip_address
    */
    public function login(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required|min:6',
            ]);

            if (Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password], $request->get('remember'))) {
                $user = auth('admin')->user();
                $user->update([
                    "last_login" => now()
                ]);
                
                return redirect()->to(route('admin.dashboard'));
            }

            return redirect()->to(route('admin.login'))->withInput($request->only('email', 'remember'));
        } catch (\Throwable $th) {
            if(auth('admin')->check()){
                return redirect()->to(route('admin.dashboard'));
            }
            Session::flush();
            return redirect()->to(route('admin.login'))->withInput($request->only('email', 'remember'));
        }
    }
}
