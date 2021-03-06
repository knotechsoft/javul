<?php

namespace App\Http\Controllers\Auth;

use App\Objective;
use App\Task;
use App\Unit;
use App\User;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new authentication controller instance.
     */
    public function __construct()
    {
        view()->share('user_login',\Auth::check());
        $this->middleware($this->guestMiddleware(), ['except' => 'logout']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
            /*'country'=>'required',
            'state'=>'required',
            'city'=>'required',*/
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        $userData=User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'country_id' => 231,
            'state_id' => 3924,
            'city_id' => 43070,
            'password' => bcrypt($data['password']),
            'role'=>'user'
        ]);

        $toEmail = $data['email'];
        $toName= $data['first_name'].' '.$data['last_name'];
        $subject="Welcome To Javul.org";

        \Mail::send('emails.registration', ['userObj'=> $userData ], function($message) use ($toEmail,$toName,$subject)
        {
            $message->to($toEmail,$toName)->subject($subject);
            $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
        });

        return $userData;

    }

    /**
     * Method will called after login successfully into system
     * @param \Illuminate\Http\Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authenticated( \Illuminate\Http\Request $request, \App\User $user ) {
        return redirect()->intended($this->redirectPath());
    }
}
