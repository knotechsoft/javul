<?php

namespace App\Http\Controllers;

use App\City;
use App\Fund;
use App\Http\Requests;
use App\Library\Helpers;
use App\Objective;
use App\Paypal;
use App\SiteActivity;
use App\SiteConfigs;
use App\State;
use App\Task;
use App\Unit;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Hashids\Hashids;

class AccountController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $msg_flag = false;
        $msg_val = '';
        $msg_type = '';
        if($request->session()->has('msg_val')){
            $msg_val =  $request->session()->get('msg_val');
            $request->session()->forget('msg_val');
            $msg_flag = true;
            $msg_type = "success";
        }
        view()->share('msg_flag',$msg_flag);
        view()->share('msg_val',$msg_val);
        view()->share('msg_type',$msg_type);

        $countries = Unit::getAllCountryWithFrequent();
        $states = State::where('country_id',Auth::user()->country_id)->lists('name','id');
        $cities = City::where('state_id',Auth::user()->state_id)->lists('name','id');
        view()->share('countries',$countries);
        view()->share('states',$states);
        view()->share('cities',$cities);

        // current logged in user available balance
        $creditedBalance = Fund::getUserDonatedFund(Auth::user()->id);
        $debitedBalance = Fund::getUserAwardedFund(Auth::user()->id);
        $availableBalance = $creditedBalance - $debitedBalance;
        view()->share('availableBalance',$availableBalance);

        //expiry years of card
        $expiry_years = SiteConfigs::getCardExpiryYear();

        view()->share('expiry_years',$expiry_years);
        return view('users.my_account');
    }

    /**
     * Function will transfer money from seller account to user accout (paypal only).
     * @param Request $request
     * @return $this
     */
    public function withdraw(Request $request){
        $validator = \Validator::make($request->all(), [
            'paypal_email'=> 'required|email',
            'cc-amount'=>'required'
        ]);
        if ($validator->fails())
            return redirect()->back()->withErrors($validator)->withInput();

        $requestedAmount = $request->input('cc-amount');
        $isCurrency = Helpers::isCurrency($requestedAmount);
        if(!$isCurrency)
            return redirect()->back()->withErrors(['error'=>'Please enter amount correctly.'])->withInput();

        $checkEmailExist = Paypal::checkEmailExistINPaypal($request->input('paypal_email'));
        if(empty($checkEmailExist))
            return redirect()->back()->withErrors(['error'=>'Email does not exist.'])->withInput();
        else{
            $creditedBalance = Fund::getUserDonatedFund(Auth::user()->id);
            $debitedBalance = Fund::getUserAwardedFund(Auth::user()->id);
            $availableBalance = $creditedBalance - $debitedBalance;

            if($requestedAmount > $availableBalance)
                return redirect()->back()->withErrors(['error'=>'Insufficient balance.'])->withInput();


            //transfer requested amount to user on given email id. (paypal)
            $data = $request->all();
            $payment = Paypal::transferAmountToUser($data);

            if(!$payment['success'])
                return redirect()->back()->withErrors(['active'=>'withdraw','error'=>'Could not connect to Paypal. Please try again later'])->withInput();

            if($payment['success'] && !empty($payment['paymentResponse'])){
                Transaction::create([
                    'created_by'=>Auth::user()->id,
                    'user_id'=>Auth::user()->id,
                    'amount'=>$data['cc-amount'],
                    'trans_type'=>'debit',
                    'comments'=>'$'.$data['cc-amount'].' withdrawn by '.Auth::user()->first_name.' '.Auth::user()->last_name
                ]);

                $request->session()->flash('msg_val', 'Amount transfer successfully.');
                $request->session()->flash('msg_type', "success");
                return redirect('account');
            }
        }

    }

    /**
     * Function will check. email exist in paypal or not.
     * @param Request $request
     * @return mixed
     */
    public function paypal_email_check(Request $request){
        $email = $request->input('paypal_email');
        $validator = \Validator::make($request->all(), [
            'paypal_email'=> 'required|email'
        ]);
        if ($validator->fails())
            return \Response::json(['success'=>false,'message'=>'Email is invalid.']);

        $checkEmailExist = Paypal::checkEmailExistINPaypal($email);
        if(!$checkEmailExist['success'] && $checkEmailExist['timeout_error'])
            return \Response::json(['success'=>false,'message'=>'Could not connect to Paypal. Please try again later.' ]);
        else if(!$checkEmailExist['success'] && !$checkEmailExist['timeout_error'])
            return \Response::json(['success'=>false,'message'=>'Email address does not exist in Paypal.' ]);

        if($checkEmailExist['success'])
            return \Response::json(['success'=>true]);
    }
    public function logout(){
        Auth::user()->update(['loggedin'=>null]);
        return redirect('logout');
    }


}
