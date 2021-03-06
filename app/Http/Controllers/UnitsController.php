<?php

namespace App\Http\Controllers;

use App\ActivityPoint;
use App\City;
use App\Country;
use App\Fund;
use App\Issue;
use App\Objective;
use App\RelatedUnit;
use App\SiteActivity;
use App\SiteConfigs;
use App\State;
use App\Task;
use App\TaskBidder;
use App\Unit;
use App\UnitCategory;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use Hashids\Hashids;


class UnitsController extends Controller
{
    public function __construct(){
        $this->middleware('auth',['except'=>['index','view','get_units_paginate']]);
        view()->share('site_activity_text','Unit Activity Log');
    }

    public function index(Request $request){
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

        // get all units for listing
        $units = Unit::orderBy('id','desc')->paginate(\Config::get('app.page_limit'));
        view()->share('units',$units );
        $site_activity = SiteActivity::orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
        view()->share('site_activity',$site_activity);
        view()->share('site_activity_text','Global Activity Log');
        return view('units.units');
    }

    /**
     * Function is used to retrieve states from country id
     * @param Request $request
     * @return mixed
     */
    public function get_state(Request $request){
        $country_id = $request->input('country_id');

        $states = State::where('country_id',$country_id)->lists('name','id');
        return \Response::json(['success'=>true,'states'=>$states]);
    }

    /**
     * function is used to retrieve cities from state id
     * @param Request $request
     * @return mixed
     */
    public function get_city(Request $request){
        $state_id = $request->input('state_id');
        $cities = City::where('state_id',$state_id)->lists('name','id');
        return \Response::json(['success'=>true,'cities'=>$cities]);
    }

    /**
     * To create new unit
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(Request $request){
        $unit_category_arr = UnitCategory::where('status','approved')->lists('name','id');
        $unit_credibility_arr= SiteConfigs::getUnitCredibilityTypes();
        $countries = Unit::getAllCountryWithFrequent();
        $unitsObj = Unit::lists('name','id');

        view()->share('totalUnits',count($unitsObj));
        view()->share('relatedUnitsObj',$unitsObj);
        view()->share('parentUnitsObj',$unitsObj);
        view()->share('countries',$countries);
        view()->share('unit_category_arr',$unit_category_arr);
        view()->share('unit_credibility_arr',$unit_credibility_arr);
        view()->share('unitObj',[] );
        view()->share('states',[]);
        view()->share('cities',[]);
        view()->share('relatedUnitsofUnitObj',[]);
        //if page is submitted
        if($request->isMethod('post')){
            $validator = \Validator::make($request->all(), [
                'unit_name' => 'required',
                'unit_category' => 'required',
                'credibility' => 'required',
                'country' => 'required'
            ]);

            if ($validator->fails())
                return redirect()->back()->withErrors($validator)->withInput();


            // insert record into units table.
            $status = $request->input('status');
            if(empty($status))
                $status="disabled";
            else
                $status="active";

            $slug=substr(str_replace(" ","_",strtolower($request->input('unit_name'))),0,20);

            $unitID = Unit::create([
                'user_id'=>Auth::user()->id,
                'name'=>$request->input('unit_name'),
                'slug'=>$slug,
                'category_id'=>implode(",",$request->input('unit_category')),
                'description'=>trim($request->input('description')),
                'credibility'=>$request->input('credibility'),
                'country_id'=>$request->input('country'),
                'state_id'=>$request->input('state'),
                'city_id'=>$request->input('city'),
                'status'=>'active',
                'parent_id'=>$request->input('parent_unit')
            ])->id;


            // After Created Unit send mail to site admin
            $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
            $unitCreator = User::find(Auth::user()->id);

            $toEmail = $unitCreator->email;
            $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
            $subject="Unit Created";

            \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
            {
                $message->to($toEmail,$toName)->subject($subject);
                if(!empty($siteAdminemails))
                    $message->bcc($siteAdminemails,"Admin")->subject($subject);

                $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
            });

            //if user selected related to unit then insert record to related_units table
            $related_unit = $request->input('related_to');
            if(!empty($related_unit)){
                RelatedUnit::create([
                    'unit_id'=>$unitID,
                    'related_to'=>implode(",",$related_unit)
                ]);
            }
            // add activity point for created unit and user.
            ActivityPoint::create([
                'user_id'=>Auth::user()->id,
                'unit_id'=>$unitID,
                'points'=>2,
                'comments'=>'Unit Created',
                'type'=>'unit'
            ]);
            // add site activity record for global statistics.
            $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
            $user_id = $userIDHashID->encode(Auth::user()->id);

            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->encode($unitID);
            SiteActivity::create([
                'user_id'=>Auth::user()->id,
                'unit_id'=>$unitID,
                'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                    .Auth::user()->first_name.' '.Auth::user()->last_name.'</a>
                created
                 unit <a href="'.url('units/'.$unit_id.'/'.$slug).'">'.$request->input('unit_name').'</a>'
            ]);

            $request->session()->flash('msg_val', "Unit created successfully!!!");
            return redirect('units');
        }


        return view('units.create');
    }

    /**
     * Update Unit information
     * @param $unit_id
     * @param Request $request
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function edit($unit_id,Request $request){
        if(!empty($unit_id))
        {
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)){
                $unit_id = $unit_id[0];
                $units = Unit::getUnitWithCategories($unit_id);
                $totalUnits = Unit::count();
                view()->share('totalUnits',$totalUnits );
                //dd($request->all());
                if(!empty($units) && $request->isMethod('post')){
                    //update unit and redirect to units page
                    $validator = \Validator::make($request->all(), [
                        'unit_name' => 'required',
                        'unit_category' => 'required',
                        'credibility' => 'required',
                        'country' => 'required'
                    ]);

                    if ($validator->fails())
                        return redirect()->back()->withErrors($validator)->withInput();

                    // insert record into units table.
                    $status = $request->input('status');
                    if(empty($status))
                        $status="disabled";
                    else
                        $status="active";

                    if(Auth::user()->role != "superadmin")
                        $status="active";

                    $slug=substr(str_replace(" ","_",strtolower($request->input('unit_name'))),0,20);

                    // update unit data.
                    Unit::where('id',$unit_id)->update([
                        'name'=>$request->input('unit_name'),
                        'slug'=>$slug,
                        'category_id'=>implode(",",$request->input('unit_category')),
                        'description'=>trim($request->input('description')),
                        'credibility'=>$request->input('credibility'),
                        'country_id'=>$request->input('country'),
                        'state_id'=>$request->input('state'),
                        'city_id'=>$request->input('city'),
                        'status'=>$status,
                        'parent_id'=>$request->input('parent_unit'),
                        'modified_by'=>Auth::user()->id
                    ]);

                    //if user selected related to unit then insert record to related_units table
                    $related_unit = $request->input('related_to');
                    if(!empty($related_unit)){
                        $relatedUnitExist = RelatedUnit::where('unit_id',$unit_id)->count();
                        if($relatedUnitExist > 0){
                            RelatedUnit::where('unit_id',$unit_id)->update([
                                'related_to'=>implode(",",$related_unit)
                            ]);
                        }
                        else{
                            RelatedUnit::create([
                                'unit_id'=>$unit_id,
                                'related_to'=>implode(",",$related_unit)
                            ]);
                        }
                    }
                    else
                    {
                        $cnt = RelatedUnit::where('unit_id',$unit_id)->count();
                        if($cnt > 0)
                            RelatedUnit::where('unit_id',$unit_id)->forceDelete();
                    }
                    // add activity point for created unit and user.
                    ActivityPoint::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$unit_id,
                        'points'=>1,
                        'comments'=>'Unit Edited',
                        'type'=>'unit'
                    ]);
                    // add site activity record for global statistics.
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
                    $tempUnitID= $unit_id;
                    $unit_id = $unitIDHashID->encode($unit_id);
                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$tempUnitID,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name))
                            .'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a>
                        updated unit <a href="'.url('units/'.$unit_id.'/'.$slug).'">'.$request->input('unit_name').'</a>'
                    ]);

                    // After Created Unit send mail to site admin
                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Unit Updated";

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });

                    $request->session()->flash('msg_val', "Unit updated successfully!!!");
                    return redirect('units');

                }
                elseif(!empty($units)){

                    //redirect to edit page
                    //$units = array_shift($units);
                    $unit_category_arr = UnitCategory::where('status','approved')->lists('name','id');
                    $unit_credibility_arr= SiteConfigs::getUnitCredibilityTypes();
                    $countries = Country::lists('name','id');
                    $countries = Unit::getAllCountryWithFrequent();
                    $states = State::where('country_id',$units->country_id)->lists('name','id');
                    $cities = City::where('state_id',$units->state_id)->lists('name','id');
                    $unitsObj = Unit::where('id','!=',$unit_id)->lists('name','id');

                    $relatedUnitsofUnitObj = RelatedUnit::where('unit_id',$unit_id)->first();
                    if(!empty($relatedUnitsofUnitObj))
                        $relatedUnitsofUnitObj = explode(",",$relatedUnitsofUnitObj->related_to);
                    else
                        $relatedUnitsofUnitObj  = [];

                    view()->share('relatedUnitsObj',$unitsObj);
                    view()->share('relatedUnitsofUnitObj',$relatedUnitsofUnitObj);

                    view()->share('parentUnitsObj',$unitsObj);
                    view()->share('countries',$countries);
                    view()->share('states',$states);
                    view()->share('cities',$cities);

                    view()->share('unit_category_arr',$unit_category_arr);
                    view()->share('unit_credibility_arr',$unit_credibility_arr);


                    view()->share('unitObj',$units );
                    return view('units.create');
                }
            }

        }
        return view('errors.404');
    }

    /**
     * Display Unit information only.
     * @param $unit_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view($unit_id){
        if(!empty($unit_id))
        {
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)){
                $unit_id = $unit_id[0];
                $unit = Unit::getUnitWithCategories($unit_id);
                if(!empty($unit)){
                    $objectives = Objective::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.page_limit'));
                    $related_units = RelatedUnit::getRelatedUnitName($unit_id);
                    $taskForBidding = Task::where('unit_id', '=', $unit_id)->where('status', '=', "approval")->count();
                    $userAuth = Auth::user();
                    $taskBidders = [];
                    if(!empty($userAuth))
                    $taskBidders = Task::join('task_bidders','tasks.id','=','task_bidders.task_id')->where('task_bidders.user_id',
                        Auth::user()->id)->where('unit_id',$unit_id)->where('tasks.status', '=', "approval")->count();

                    if($taskForBidding > 0)
                        $taskForBidding = $taskForBidding - $taskBidders;

                    if($unit->country_id == 247)
                        $cityName = "Global";
                    else
                        $cityName = City::find($unit->city_id)->name;

                    view()->share('taskForBidding',$taskForBidding);
                    view()->share('cityName',$cityName);
                    view()->share('related_units',$related_units);
                    view()->share('unitObj',$unit );
                    view()->share('objectivesObj',$objectives );

                    $availableFunds =Fund::getUnitDonatedFund($unit_id);
                    $awardedFunds =Fund::getUnitAwardedFund($unit_id);

                    view()->share('availableFunds',$availableFunds );
                    view()->share('awardedFunds',$awardedFunds );

                    $site_activity = SiteActivity::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
                    $taskObj = Task::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.page_limit'));
                    view()->share('taskObj',$taskObj);
                    view()->share('site_activity',$site_activity);
                    view()->share('unit_activity_id',$unit_id);

                    $issuesObj = Issue::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.page_limit'));
                    view()->share('issuesObj',$issuesObj);

                    return view('units.view');
                }
            }
        }
        return view('errors.404');
    }

    public function delete_unit(Request $request){
        $unitID = $request->input('id');
        if(!empty($unitID)){
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unitID = $unitIDHashID->decode($unitID);
            if(!empty($unitID)){
                $unitID = $unitID[0];
                $unitObj = Unit::find($unitID);
                $unitTemp = $unitObj;
                if(count($unitObj) > 0){
                    $objectiveObj = Objective::where('unit_id',$unitID)->get();
                    if(!empty($objectiveObj)){
                        foreach($objectiveObj as $objective){
                            $tasksObj = Task::where('objective_id',$objective->id)->get();
                            if(count($tasksObj) > 0){
                                foreach($tasksObj  as $task)
                                    Task::deleteTask($task->id);
                            }
                            Objective::find($objective->id)->delete();
                        }
                    }
                    $unitObj->delete();
                    // add activity point for created unit and user.
                    ActivityPoint::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$unitID,
                        'points'=>1,
                        'comments'=>'Unit deleted',
                        'type'=>'unit'
                    ]);

                    // add site activity record for global statistics.
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    /*$objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
                    $objectiveId = $objectiveIDHashID->encode($objectiveID);*/

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$unitID,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'.Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a>
                        deleted unit '.$unitTemp->name
                    ]);

                    // After Created Unit send mail to site admin
                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Unit Deleted";

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });

                    return \Response::json(['success'=>true]);
                }
            }
        }
        return \Response::json(['success'=>false]);
    }


    public function available_bids($unit_id){
        if(!empty($unit_id)){
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)){
                $unit_id = $unit_id[0];
                $unitObj = Unit::find($unit_id);
                if(!empty($unitObj)){
                    $taskObj = Task::where('unit_id', '=', $unit_id)->where('status', '=', "approval")->get();
                    view()->share('taskObj',$taskObj);
                    return view('tasks.available_for_bid');
                }
            }
        }
        return view('errors.404');
    }

    public function get_units_paginate(Request $request){
        $page_limit = \Config::get('app.page_limit');
        $units = Unit::orderBy('id','desc')->paginate($page_limit);
        view()->share('units',$units);
        $html = view('units.partials.more_units')->render();
        return \Response::json(['success'=>true,'html'=>$html]);

    }
    public function show()
    {

    }
}
