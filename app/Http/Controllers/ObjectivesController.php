<?php

namespace App\Http\Controllers;

use App\ActivityPoint;
use App\Fund;
use App\ImportanceLevel;
use App\Objective;
use App\SiteActivity;
use App\Task;
use App\Unit;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use Hashids\Hashids;

class ObjectivesController extends Controller
{
    public function __construct(){
        $this->middleware('auth',['except'=>['index','view','get_objectives_paginate']]);
        view()->share('site_activity_text','Unit Activity Log');
    }

    /**
     * Objectives listing
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
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

        // get all objectives for listing
       /* $object = Unit::with(['objectives','tasks'])->get();
        dd($object );*/
        $objectives = Objective::join('units','objectives.unit_id','=','units.id')
                                ->join('users','objectives.user_id','=','users.id')
                                ->select(['objectives.*','units.name as unit_name','users.first_name','users.last_name',
                'users.id as user_id'])->orderBy('objectives.id','desc')->paginate(\Config::get('app.page_limit'));
        view()->share('objectives',$objectives );

        $site_activity = SiteActivity::orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
        view()->share('site_activity',$site_activity);
        view()->share('site_activity_text','Global Activity Log');
        return view('objectives.objectives');
    }

    /**
     * create objectives under unit.
     * @param Request $request
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function create(Request $request){

        $segments =$request->segments();

        $unit_id=null;
        $unitInfo = [];
        $availableUnitFunds = '';
        $awardedUnitFunds  = '';
        if(count($segments) == 3){
            $unit_id=$request->segment(2);
            if(empty($unit_id) && count($segments) == 3)
                return view('errors.404');
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);

            if(empty($unit_id))
                return view('errors.404');

            $unit_id = $unit_id[0];
            $unitInfo = Unit::find($unit_id);
            $availableUnitFunds =Fund::getUnitDonatedFund($unit_id);
            $awardedUnitFunds =Fund::getUnitAwardedFund($unit_id);

        }

        view()->share('availableUnitFunds',$availableUnitFunds);
        view()->share('awardedUnitFunds',$awardedUnitFunds);

        $unitsObj = Unit::where('status','active')->lists('name','id');
        $parentObjectivesObj = Objective::lists('name','id');

        view()->share('parentObjectivesObj',$parentObjectivesObj);
        view()->share('unitsObj',$unitsObj);
        view()->share('objectiveObj',[]);
        view()->share('objectives_unit_id',$unit_id );
        view()->share('unitInfo',$unitInfo);

        if($request->isMethod('post'))
        {
            $validator = \Validator::make($request->all(), [
                'unit' => 'required',
                'objective_name' => 'required'
            ]);

            if ($validator->fails())
                return redirect()->back()->withErrors($validator)->withInput();

            $status = "in-progress";

            $unitID = $request->input('unit');
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unitID = $unitIDHashID->decode($unitID);
            if(empty($unitID))
                return redirect()->back()->withErrors(['unit'=>'Unit doesn\'t exist in database.'])->withInput();
            $unitID = $unitID[0];

            if(Unit::find($unitID)->count() == 0)
                return redirect()->back()->withErrors(['unit'=>'Unit doesn\'t exist in database.'])->withInput();

            // create objective
            $parent_id = $request->input('parent_objective');
            if(!empty($parent_id)){
                $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
                $parent_id = $objectiveIDHashID->decode($parent_id);
                if(!empty($parent_id))
                    $parent_id=$parent_id[0];
                else
                    $parent_id =null;
            }
            else
                $parent_id =null;
            $slug=substr(str_replace(" ","_",strtolower($request->input('objective_name'))),0,20);
            $objectiveId = Objective::create([
                'user_id'=>Auth::user()->id,
                'unit_id'=>$unitID,
                'name'=>$request->input('objective_name'),
                'slug'=>$slug,
                'description'=>$request->input('description'),
                'status'=>$status,
                'parent_id'=>$parent_id
            ])->id;

            ImportanceLevel::create([
                'user_id'=>Auth::user()->id,
                'objective_id'=>$objectiveId,
                'importance_level'=>'+1',
                'type'=>'Objective'
            ]);

            // add activity point for created unit and user.
            ActivityPoint::create([
                'user_id'=>Auth::user()->id,
                'objective_id'=>$objectiveId,
                'points'=>2,
                'comments'=>'Objective Created',
                'type'=>'objective'
            ]);

            // add site activity record for global statistics.
            $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
            $user_id = $userIDHashID->encode(Auth::user()->id);

            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $objectiveId = $objectiveIDHashID->encode($objectiveId);

            SiteActivity::create([
                'user_id'=>Auth::user()->id,
                'unit_id'=>$unitID,
                'objective_id'=>$objectiveId,
                'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                    .Auth::user()->first_name.' '.Auth::user()->last_name.'</a>
                        created objective <a href="'.url('objectives/'.$objectiveId.'/'.$slug).'">'.$request->input('objective_name').'</a>'
            ]);

            // After Created Unit send mail to site admin
            $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
            $unitCreator = User::find(Auth::user()->id);

            $toEmail = $unitCreator->email;
            $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
            $subject="Objective Created";

            \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
            {
                $message->to($toEmail,$toName)->subject($subject);
                if(!empty($siteAdminemails))
                    $message->bcc($siteAdminemails,"Admin")->subject($subject);

                $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
            });

            $request->session()->flash('msg_val', "Objective created successfully!!!");
            return redirect('objectives');

        }
        return view('objectives.create');
    }

    /**
     * Update objectives
     * @param $objective_id
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($objective_id,Request $request){
        if(!empty($objective_id)){
            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $objective_id = $objectiveIDHashID->decode($objective_id);
            if(!empty($objective_id)){
                $objective_id = $objective_id[0];
                $objectiveObj = Objective::find($objective_id);

                //update data of objective
                if(!empty($objectiveObj) && $request->isMethod('post')){
                    $validator = \Validator::make($request->all(), [
                        'unit' => 'required',
                        'objective_name' => 'required'
                    ]);

                    if ($validator->fails())
                        return redirect()->back()->withErrors($validator)->withInput();

                    $unitID = $request->input('unit');
                    $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
                    $unitID = $unitIDHashID->decode($unitID);
                    if(empty($unitID))
                        return redirect()->back()->withErrors(['unit'=>'Unit doesn\'t exist in database.'])->withInput();
                    $unitID = $unitID[0];

                    if(Unit::find($unitID)->count() == 0)
                        return redirect()->back()->withErrors(['unit'=>'Unit doesn\'t exist in database.'])->withInput();

                    // create objective
                    $slug=substr(str_replace(" ","_",strtolower($request->input('objective_name'))),0,20);
                    $parent_id = $request->input('parent_objective');
                    if(!empty($parent_id)){
                        $parent_id = $objectiveIDHashID->decode($parent_id);
                        if(!empty($parent_id))
                            $parent_id=$parent_id[0];
                    }
                    if(Objective::where('id',$objective_id)->count() > 0){

                        Objective::where('id',$objective_id)->update([
                            'user_id'=>Auth::user()->id,
                            'unit_id'=>$unitID,
                            'name'=>$request->input('objective_name'),
                            'slug'=>$slug,
                            'description'=>$request->input('description'),
                            'parent_id'=>$parent_id
                        ]);
                    }

                    // add activity point for created unit and user.
                    ActivityPoint::create([
                        'user_id'=>Auth::user()->id,
                        'objective_id'=>$objective_id,
                        'points'=>1,
                        'comments'=>'Objective updated',
                        'type'=>'objective'
                    ]);

                    // add site activity record for global statistics.
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
                    $objectiveId = $objectiveIDHashID->encode($objective_id);

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$unitID,
                        'objective_id'=>$objective_id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a>
                        updated objective <a href="'.url('objectives/'.$objectiveId.'/'.$slug).'">'.$request->input('objective_name').'</a>'
                    ]);

                    // After Created Unit send mail to site admin
                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Objective Updated";

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });

                    $request->session()->flash('msg_val', "Objective updated successfully!!!");
                    return redirect('objectives');

                }
                elseif(!empty($objectiveObj)){
                    //display update page to user
                    view()->share('objectiveObj',$objectiveObj);
                    $unitsObj = Unit::where('status','active')->lists('name','id');
                    $parentObjectivesObj = Objective::where('id','!=',$objective_id)->lists('name','id');
                    view()->share('parentObjectivesObj',$parentObjectivesObj);
                    view()->share('unitsObj',$unitsObj);
                    return view('objectives.create');
                }
            }
        }
        return view('errors.404');
    }

    public function view($objective_id){
        if(!empty($objective_id)){

            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $objective_id = $objectiveIDHashID->decode($objective_id);
            if(!empty($objective_id)){
                $objective_id = $objective_id[0];
                $obj = Objective::checkObjectiveExist($objective_id,false);
                if($obj){
                    $objectiveObj = Objective::where('id',$objective_id)->first();
                    $objectiveObj->tasks = Task::where('objective_id',$objective_id)->orderBy('id','desc')->paginate(\Config::get('app.page_limit'));
                    $objectiveObj->unit = Unit::getUnitWithCategories($objectiveObj->unit_id);
                    $upvotedCnt = ImportanceLevel::where('objective_id',$objective_id)->where('importance_level','+1')->count();
                    $downvotedCnt = ImportanceLevel::where('objective_id',$objective_id)->where('importance_level','-1')->count();

                    if($upvotedCnt == 0 && $downvotedCnt == 0)
                        $importancePercentage = 0;
                    else{

                        $importancePercentage =  ($upvotedCnt * 100) / ($upvotedCnt + $downvotedCnt);
                    }

                    if(is_float($importancePercentage))
                        $importancePercentage = ceil($importancePercentage);
                    view()->share('upvotedCnt',$upvotedCnt);
                    view()->share('downvotedCnt',$downvotedCnt);
                    view()->share('importancePercentage',$importancePercentage);
                    if(!empty($objectiveObj)){
                        view()->share('objectiveObj',$objectiveObj);
                        $availableFunds =Fund::getObjectiveDonatedFund($objective_id);
                        $awardedFunds =Fund::getObjectiveAwardedFund($objective_id);

                        view()->share('availableObjFunds',$availableFunds );
                        view()->share('awardedObjFunds',$awardedFunds );

                        $availableUnitFunds =Fund::getUnitDonatedFund($objectiveObj->unit_id);
                        $awardedUnitFunds =Fund::getUnitAwardedFund($objectiveObj->unit_id);

                        view()->share('availableUnitFunds',$availableUnitFunds );
                        view()->share('awardedUnitFunds',$awardedUnitFunds );

                        /*$site_activity = SiteActivity::where(function($query) use($objectiveObj){
                            return $query->where('unit_id',$objectiveObj->unit_id)
                                ->orWhere('objective_id',$objectiveObj->id)
                                ->orWhere('task_id',$taskObj->id);
                        })->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));*/

                        $site_activity = SiteActivity::where('unit_id',$objectiveObj->unit->id)->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
                        view()->share('site_activity',$site_activity);
                        view()->share('unit_activity_id',$objectiveObj->unit->id);

                        return view('objectives.view');
                    }
                }
            }
        }
        return view('errors.404');

    }

    public function add_importance(Request $request){
        $objectiveID = $request->input('id');
        $objectiveIDEndcoded = $objectiveID;
        $type = $request->input('type');
        if(!empty($objectiveID)){
            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $objectiveID = $objectiveIDHashID->decode($objectiveID);
            if(!empty($objectiveID)){
                $objectiveID = $objectiveID[0];
                $objectiveObj = Objective::find($objectiveID);
                if(!empty($objectiveObj)){
                    $importanceLevelObj = ImportanceLevel::where('objective_id',$objectiveID)->where('user_id',Auth::user()->id)->first();
                    $site_activity_text = '';
                    if($type == "up"){
                        $levelValue = "+1";
                        $site_activity_text =" upvote objective ";
                    }
                    else{
                        $levelValue = "-1";
                        $site_activity_text =" downvote objective ";
                    }
                    if(count($importanceLevelObj) > 0)
                        $importanceLevelObj->update(['importance_level'=>$levelValue]);
                    else{
                        ImportanceLevel::create([
                            'user_id'=>Auth::user()->id,
                            'objective_id'=>$objectiveID,
                            'importance_level'=>$levelValue,
                            'type'=>'Objective'
                        ]);
                    }

                    $upvotedCnt = ImportanceLevel::where('objective_id',$objectiveID)->where('importance_level','+1')->count();
                    $downvotedCnt = ImportanceLevel::where('objective_id',$objectiveID)->where('importance_level','-1')->count();

                    $importancePercentage =  ($upvotedCnt * 100) / ($upvotedCnt + $downvotedCnt);

                    if(is_float($importancePercentage))
                        $importancePercentage = ceil($importancePercentage);
                    view()->share('upvotedCnt',$upvotedCnt);
                    view()->share('downvotedCnt',$downvotedCnt);
                    view()->share('importancePercentage',$importancePercentage);

                    $importance_level_html = view('objectives.partials.importance_level',['objective_id'=>$objectiveID])->render();

                    $user_id = Auth::user()->id;
                    $userIDHashID = new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id_encoded = $userIDHashID->encode($user_id);


                    // add comment : issue : skype text sir (26.07.2016)
                    /*SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id_encoded.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'.Auth::user()->first_name.' '.Auth::user()
                                ->last_name
                            .'</a>'.$site_activity_text .' <a href="'.url('objectives/'.$objectiveIDEndcoded.'/'.$objectiveObj->slug) .'">'
                            .$objectiveObj->name
                            .'</a>'
                    ]);*/

                    return \Response::json(['success'=>true,'html'=>$importance_level_html]);
                }
            }
        }
        return \Response::json(['success'=>false]);

    }

    public function delete_objective(Request $request){
        $objectiveID = $request->input('id');
        if(!empty($objectiveID)){
            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $objectiveID = $objectiveIDHashID->decode($objectiveID);
            if(!empty($objectiveID)){
                $objectiveID = $objectiveID[0];
                $objectiveObj = Objective::find($objectiveID);
                $objectiveTemp = $objectiveObj;
                if(!empty($objectiveObj)){
                    $tasksObj = Task::where('objective_id',$objectiveID)->get();
                    if(count($tasksObj) > 0){
                        foreach($tasksObj  as $task)
                            Task::deleteTask($task->id);
                    }
                    $objectiveObj->delete();

                    // add activity point for created unit and user.
                    ActivityPoint::create([
                        'user_id'=>Auth::user()->id,
                        'objective_id'=>$objectiveID,
                        'points'=>1,
                        'comments'=>'Objective deleted',
                        'type'=>'objective'
                    ]);

                    // add site activity record for global statistics.
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    /*$objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
                    $objectiveId = $objectiveIDHashID->encode($objectiveID);*/

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$objectiveTemp->unit_id,
                        'objective_id'=>$objectiveID,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name))
                            .'">'.Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a>
                        deleted objective '.$objectiveTemp->name
                    ]);

                    // After Created Unit send mail to site admin
                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Objective Deleted";

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

    public function lists(Request $request)
    {
        $unit_id = $request->segment(2);
        if(!empty($unit_id)){
            $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)){
                $unit_id= $unit_id[0];
                $objectiveObj = Objective::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.page_limit'));
                view()->share('objectiveObj',$objectiveObj);
                $objectiveObj->unit = Unit::getUnitWithCategories($unit_id);


                $site_activity = SiteActivity::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
                //$taskObj = Task::where('unit_id',$unit_id)->get();
                view()->share('site_activity',$site_activity);
                view()->share('unit_activity_id',$unit_id);
                return view('objectives.partials.list');
            }
        }
        return view('errors.404');
    }

    public function get_objectives_paginate(Request $request){
        $from_page = $request->input('from_page');
        $page_limit = \Config::get('app.page_limit');
        $unit_id = $request->input('unit_id');
        $objectives = Objective::orderBy('id','desc');
        if(!empty($unit_id )) {
            $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)) {
                $unit_id = $unit_id[0];
                $objectives =$objectives->where('unit_id',$unit_id);
            }
        }
        $objectives = $objectives->paginate($page_limit);

        view()->share('objectives',$objectives);
        view()->share('from_page',$from_page);
        view()->share('unit_id',$unit_id);
        $html = view('objectives.partials.more_objectives')->render();
        return \Response::json(['success'=>true,'html'=>$html]);
    }
}
