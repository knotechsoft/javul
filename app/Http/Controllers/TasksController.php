<?php

namespace App\Http\Controllers;

use App\ActivityPoint;
use App\Fund;
use App\JobSkill;
use App\Library\Helpers;
use App\Objective;
use App\RewardAssignment;
use App\SiteActivity;
use App\Task;
use App\TaskAction;
use App\TaskBidder;
use App\TaskCancel;
use App\TaskComplete;
use App\TaskDocuments;
use App\TaskEditor;
use App\TaskHistory;
use App\Unit;
use Hashids\Hashids;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use Illuminate\Support\Facades\Input;

class TasksController extends Controller
{
    public function __construct(){
        $this->middleware('auth',['except'=>['index','view','get_tasks_paginate']]);
        \Stripe\Stripe::setApiKey(env('STRIPE_KEY'));
        view()->share('site_activity_text','Unit Activity Log');
    }

    /**
     * Task Listing
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View]
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
            if($request->session()->has('msg_type')){
                $msg_type = $request->session()->get('msg_type');
                $request->session()->forget('msg_type');
            }
        }
        view()->share('msg_flag',$msg_flag);
        view()->share('msg_val',$msg_val);
        view()->share('msg_type',$msg_type);
        view()->share('site_activity_text','Global Activity Log');

        //\DB::enableQueryLog();
        $tasks = \DB::table('tasks')
            ->join('objectives','tasks.objective_id','=','objectives.id')
            ->join('units','tasks.unit_id','=','units.id')
            ->join('users','tasks.user_id','=','users.id')
            ->select(['tasks.*','units.name as unit_name','users.first_name','users.last_name',
                'users.id as user_id','objectives.name as objective_name'])
            ->whereNull('tasks.deleted_at')
            ->orderBy('tasks.id','desc')
            ->paginate(\Config::get('app.page_limit'));
        //dd(\DB::getQueryLog());


        $site_activity = SiteActivity::orderBy('id',
            'desc')->paginate(\Config::get('app.site_activity_page_limit'));


        view()->share('site_activity',$site_activity);
        view()->share('tasks',$tasks);
        return view('tasks.tasks');
    }

    /**
     * create task.
     * @param Request $request
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function create(Request $request){

        $segments =$request->segments();

        $taskObjectiveObj = [];
        $task_unit_id = null;
        $task_objective_id = null;

        $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
        $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
        $taskUnitObj= [];
        $availableUnitFunds='';
        $awardedUnitFunds='';

        if(count($segments) == 4){

            $task_unit_id = $request->segment(2);
            $task_objective_id = $request->segment(3);

            if(empty($task_unit_id) || empty($task_objective_id))
                return view('errors.404');


            $task_unit_id = $unitIDHashID->decode($task_unit_id);


            $task_objective_id = $objectiveIDHashID->decode($task_objective_id);

            if(empty($task_unit_id) || empty($task_objective_id))
                return view('errors.404');

            $task_unit_id = $task_unit_id[0];
            $task_objective_id = $task_objective_id[0];

            $taskUnitObj = Unit::find($task_unit_id);
            $taskObjectiveObj = Objective::find($task_objective_id);

            if(empty($taskUnitObj) || empty($taskObjectiveObj))
                return view('errors.404');

            $availableUnitFunds =Fund::getUnitDonatedFund($task_unit_id);
            $awardedUnitFunds =Fund::getUnitAwardedFund($task_unit_id);

            $taskObjectiveObj = Objective::where('unit_id',$task_unit_id)->get();
        }
        else
        {
            //if add taks from unit view page then show unit info table as we implement in add objective on unit view page.
            $unit_id = $request->get('unit');
            if(!empty($unit_id)){
                $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
                $unit_id = $unitIDHashID->decode($unit_id);
                if(!empty($unit_id))
                {
                    $unit_id = $unit_id[0];
                    $taskUnitObj = Unit::find($unit_id);
                    if(!empty($taskUnitObj))
                    {
                        $availableUnitFunds =Fund::getUnitDonatedFund($unit_id);
                        $awardedUnitFunds =Fund::getUnitAwardedFund($unit_id);

                        $taskObjectiveObj = Objective::where('unit_id',$unit_id)->get();
                    }
                }
            }
        }
        // ********************* make selected unitid and objectiveid from url in "add" mode **************************
        view()->share('unitInfo',$taskUnitObj);
        view()->share('availableUnitFunds',$availableUnitFunds);
        view()->share('awardedUnitFunds',$awardedUnitFunds);
        view()->share('task_unit_id',$task_unit_id);
        view()->share('task_objective_id',$task_objective_id);

        // ********************* end **************************

        $unitsObj = Unit::where('status','active')->lists('name','id');
        $task_skills = JobSkill::lists('skill_name','id');
        $assigned_toUsers = User::where('id','!=',Auth::user()->id)->where('role','!=','superadmin')->get();
        $assigned_toUsers= $assigned_toUsers->lists('full_name','id');
        view()->share('assigned_toUsers',$assigned_toUsers);
        view()->share('task_skills',$task_skills );
        view()->share('unitsObj',$unitsObj);
        view()->share('objectiveObj',$taskObjectiveObj );
        view()->share('taskObj',[]);
        view()->share('taskDocumentsObj',[]);
        //view()->share('taskActionsObj',[]);
        view()->share('exploded_task_list',[]);
        view()->share('editFlag',false);
        view()->share('actionListFlag',false);
        if($request->isMethod('post')){

            $validator = \Validator::make($request->all(), [
                'unit' => 'required',
                'objective' => 'required',
                'task_name' => 'required',
                'task_skills' => 'required',
                'estimated_completion_time_start' => 'required',
                'estimated_completion_time_end' => 'required',
                'description'=>'required'
            ]);

            if ($validator->fails())
                return redirect()->back()->withErrors($validator)->withInput();

            // check unit id exist in db
            $unit_id = $request->input('unit');
            $flag =Unit::checkUnitExist($unit_id ,true);
            if(!$flag)
                return redirect()->back()->withErrors(['unit'=>'Unit doesn\'t exist in database.'])->withInput();


            // check objective id exist in db
            $objective_id = $request->input('objective');
            $flag =Objective::checkObjectiveExist($objective_id ,true); // pass objective_id and true for decode the string
            if(!$flag)
                return redirect()->back()->withErrors(['objective'=>'Objective doesn\'t exist in database.'])->withInput();


            $unit_id = $unitIDHashID->decode($unit_id);
            $objective_id = $objectiveIDHashID->decode($objective_id);

            $start_date = '';
            $end_date = '';
            try {
                $start_date  = new \DateTime($request->input('estimated_completion_time_start'));
                $end_date     = new \DateTime($request->input('estimated_completion_time_end'));
            } catch (Exception $e) {
                echo $e->getMessage();
                exit(1);
            }
            $start_date = $start_date->getTimestamp();
            $end_date  = $end_date->getTimestamp();

            // create task
            $slug=substr(str_replace(" ","_",strtolower($request->input('task_name'))),0,20);
            $task_id = Task::create([
                'user_id'=>Auth::user()->id,
                'unit_id'=>$unit_id[0],
                'objective_id'=>$objective_id[0],
                'name'=>$request->input('task_name'),
                'slug'=>$slug,
                'description'=>$request->input('description'),
                'summary'=>$request->input('summary'),
                'skills'=>implode(",",$request->input('task_skills')),
                'estimated_completion_time_start'=>date('Y-m-d h:i',$start_date),
                'estimated_completion_time_end'=>date('Y-m-d h:i',$end_date),
                'task_action'=>$request->input('action_items'),
                'compensation'=>$request->input('compensation'),
                'status'=>'editable'
            ])->id;

            $task_id_decoded= $task_id;
            $taskIDHashID= new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->encode($task_id);

            // insert action items of task.
            /*$action_items_ar = $request->input('action_items_array');
            if(!empty($action_items_ar)){
                foreach($action_items_ar as $item){
                    $item = strip_tags($item);
                    if(!empty($item)){
                        TaskAction::create([
                            'user_id'=>Auth::user()->id,
                            'task_id'=>$task_id_decoded,
                            'name'=>$item,
                            'description'=>'',
                            'status'=>'active'
                        ]);
                    }
                }
            }*/

            // upload documents of task.

            if($request->hasFile('documents')) {
                $files = $request->file('documents');
                if(count($files) > 0){
                    $totalAvailableDocs = TaskDocuments::where('task_id',$task_id_decoded)->get();
                    $totalAvailableDocs= count($totalAvailableDocs) + 1;
                    foreach($files as $index=>$file){
                        if(!empty($file)){
                            $rules = ['document' => 'required', 'extension' => 'required|in:doc,docx,pdf,txt,jpg,png,ppt,pptx,jpeg,doc,xls,xlsx'];
                            $fileData = ['document' => $file, 'extension' => strtolower($file->getClientOriginalExtension())];

                            // doing the validation, passing post data, rules and the messages
                            $validator = \Validator::make($fileData, $rules);
                            if (!$validator->fails()) {
                               if ($file->isValid()) {
                                    $destinationPath = base_path().'/uploads/tasks/'.$task_id; // upload path
                                    if(!\File::exists($destinationPath)){
                                        $oldumask = umask(0);
                                        @mkdir($destinationPath, 0775); // or even 01777 so you get the sticky bit set
                                        umask($oldumask);
                                    }
                                    $file_name =$file->getClientOriginalName();
                                    $extension = $file->getClientOriginalExtension(); // getting image extension
                                    //$fileName = $task_id.'_'.$index . '.' . $extension; // renaming image
                                    $fileName = $task_id.'_'.$totalAvailableDocs . '.' . $extension; // renaming image
                                    $file->move($destinationPath, $fileName); // uploading file to given path

                                    // insert record into task_documents table
                                    $path = $destinationPath.'/'.$fileName;
                                    TaskDocuments::create([
                                        'task_id'=>$task_id_decoded,
                                        'file_name'=>$file_name,
                                        'file_path'=>'uploads/tasks/'.$task_id.'/'.$fileName
                                    ]);
                                   $totalAvailableDocs++;
                                }
                            }
                        }
                    }
                }
            }
            // add activity point for created task.

            ActivityPoint::create([
                'user_id'=>Auth::user()->id,
                'task_id'=>$task_id,
                'points'=>5,
                'comments'=>'Task Created',
                'type'=>'task'
            ]);

            // add site activity record for global statistics.


            $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
            $user_id = $userIDHashID->encode(Auth::user()->id);

            SiteActivity::create([
                'user_id'=>Auth::user()->id,
                'unit_id'=>$unit_id[0],
                'objective_id'=>$objective_id[0],
                'task_id'=>$task_id,
                'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                    .Auth::user()->first_name.' '.Auth::user()->last_name.'</a>
                        created task <a href="'.url('tasks/'.$task_id.'/'.$slug).'">'.$request->input('task_name').'</a>'
            ]);

            // TODO: create forum entry when task is created : in PDF page no - 10

            // After Created Unit send mail to site admin
            $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
            $unitCreator = User::find(Auth::user()->id);

            $toEmail = $unitCreator->email;
            $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
            $subject="Task Created";

            \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
            {
                $message->to($toEmail,$toName)->subject($subject);
                if(!empty($siteAdminemails))
                    $message->bcc($siteAdminemails,"Admin")->subject($subject);

                $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
            });

            $request->session()->flash('msg_val', "Task created successfully!!!");
            return redirect('tasks');
        }

        return view('tasks.create');
    }

    /**
     * edit task function
     * @param Request $request
     * @param $task_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(Request $request,$task_id){
        if(!empty($task_id))
        {
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $task = Task::find($task_id);

                // if user submit the form then update the data.
                if($request->isMethod('post') && !empty($task)){
                    if($task->status == "awaiting_approval" || $task->status == "approval"){
                        return redirect()->back()->withErrors(['unit'=>'You can\'t edit task.'])->withInput();
                    }
                    $validator = \Validator::make($request->all(), [
                        'unit' => 'required',
                        'objective' => 'required',
                        'task_name' => 'required',
                        'task_skills' => 'required',
                        'estimated_completion_time_start' => 'required',
                        'estimated_completion_time_end' => 'required',
                        'description'=>'required'
                    ]);

                    if ($validator->fails())
                       return redirect()->back()->withErrors($validator)->withInput();

                    // if user didn't change anything then just redirect to task listing page.
                    $updatedFields= $request->input('changed_items');

                    // check unit id exist in db
                    $unit_id = $request->input('unit');
                    $flag =Unit::checkUnitExist($unit_id ,true);
                    if(!$flag)
                        return redirect()->back()->withErrors(['unit'=>'Unit doesn\'t exist in database.'])->withInput();


                    // check objective id exist in db
                    $objective_id = $request->input('objective');
                    $flag =Objective::checkObjectiveExist($objective_id ,true); // pass objective_id and true for decode the string
                    if(!$flag)
                        return redirect()->back()->withErrors(['objective'=>'Objective doesn\'t exist in database.'])->withInput();

                    $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
                    $unit_id = $unitIDHashID->decode($unit_id);

                    $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
                    $objective_id = $objectiveIDHashID->decode($objective_id);

                    $start_date = '';
                    $end_date = '';
                    try {
                        $start_date  = new \DateTime($request->input('estimated_completion_time_start'));
                        $end_date     = new \DateTime($request->input('estimated_completion_time_end'));
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        exit(1);
                    }
                    $start_date = $start_date->getTimestamp();
                    $end_date  = $end_date->getTimestamp();

                    // update task
                    $slug=substr(str_replace(" ","_",strtolower($request->input('task_name'))),0,20);
                    Task::where('id',$task_id)->update([
                        //'user_id'=>Auth::user()->id,
                        'unit_id'=>$unit_id[0],
                        'objective_id'=>$objective_id[0],
                        'name'=>$request->input('task_name'),
                        'slug'=>$slug,
                        'description'=>$request->input('description'),
                        'summary'=>$request->input('summary'),
                        'skills'=>implode(",",$request->input('task_skills')),
                        'estimated_completion_time_start'=>date('Y-m-d h:i',$start_date),
                        'estimated_completion_time_end'=>date('Y-m-d h:i',$end_date),
                        'task_action'=>trim($request->input('action_items')),
                        'compensation'=>$request->input('compensation'),
                        'status'=>'editable'
                    ]);

                    $task_id_decoded= $task_id;
                    $taskIDHashID= new Hashids('task id hash',10,\Config::get('app.encode_chars'));
                    $task_id = $taskIDHashID->encode($task_id);

                    // upload documents of task.
                    $task_documents=[];
                    if($request->hasFile('documents')) {
                        $files = $request->file('documents');
                        if(count($files) > 0){
                           \DB::enableQueryLog();
                            $totalAvailableDocs = TaskDocuments::where('task_id',$task_id_decoded)->get();
                            $totalAvailableDocs= count($totalAvailableDocs) + 1;
                            foreach($files as $index=>$file){
                                if(!empty($file)){

                                    $rules = ['document' => 'required', 'extension' => 'required|in:doc,docx,pdf,txt,jpg,png,ppt,pptx,jpeg,doc,xls,xlsx'];
                                    $fileData = ['document' => $file, 'extension' => strtolower($file->getClientOriginalExtension())];

                                    // doing the validation, passing post data, rules and the messages
                                    $validator = \Validator::make($fileData, $rules);
                                    if (!$validator->fails()) {
                                        if ($file->isValid()) {
                                            $destinationPath = base_path().'/uploads/tasks/'.$task_id; // upload path
                                            if(!\File::exists($destinationPath)){
                                                $oldumask = umask(0);
                                                @mkdir($destinationPath, 0775); // or even 01777 so you get the sticky bit set
                                                umask($oldumask);
                                            }

                                            $file_name =$file->getClientOriginalName();
                                            $extension = $file->getClientOriginalExtension(); // getting image extension
                                            $fileName = $task_id.'_'.$totalAvailableDocs . '.' . $extension; // renaming image
                                            $file->move($destinationPath, $fileName); // uploading file to given path

                                            // insert record into task_documents table
                                            $task_documents[]=['task_id'=>$task_id_decoded,'file_name'=>$file_name,
                                                'file_path'=>'uploads/tasks/'.$task_id.'/'.$fileName];

                                            TaskDocuments::create([
                                                'task_id'=>$task_id_decoded,
                                                'file_name'=>$file_name,
                                                'file_path'=>'uploads/tasks/'.$task_id.'/'.$fileName
                                            ]);
                                            $totalAvailableDocs++;
                                        }
                                    }
                                }

                            }
                        }
                    }

                    // if user edited record second time or more than get updatedFields of last edited record and merge with new
                    // updatefields and store into new history. because we will display only updated value to unit admin.

                    $taskHistoryObj = TaskEditor::join('task_history','task_editors.task_history_id','=','task_history.id')
                        ->where('task_editors.user_id',Auth::user()->id)
                        ->where('task_id',$task_id_decoded)
                        ->orderBy('task_history.id','desc')
                        ->first();
                    if(!empty($taskHistoryObj)){
                        $oldUpdatedFields= json_decode($taskHistoryObj->updatedFields);
                        if(!empty($oldUpdatedFields) && !empty($updatedFields))
                            $updatedFields = array_merge($updatedFields,$oldUpdatedFields );

                    }

                    // add record into task_history for task history.
                    $task_history_id =TaskHistory::create([
                        'unit_id'=>$unit_id[0],
                        'objective_id'=>$objective_id[0],
                        'name'=>$request->input('task_name'),
                        'description'=>$request->input('description'),
                        'summary'=>$request->input('summary'),
                        'skills'=>implode(",",$request->input('task_skills')),
                        'estimated_completion_time_start'=>date('Y-m-d h:i',$start_date),
                        'estimated_completion_time_end'=>date('Y-m-d h:i',$end_date),
                        'task_action'=>trim($request->input('action_items')),
                        'task_documents'=>json_encode($task_documents),
                        'compensation'=>$request->input('compensation'),
                        'updatedFields'=>json_encode($updatedFields)
                    ])->id;

                    $taskEditorObj = TaskEditor::where('task_id',$task_id_decoded)->where('user_id',Auth::user()->id)->count();
                    if($taskEditorObj > 0){
                        TaskEditor::where('task_id',$task_id_decoded)->where('user_id',Auth::user()->id)->update([
                            'task_history_id'=>$task_history_id
                        ]);
                    }
                    else{
                        TaskEditor::create([
                            'task_id'=>$task_id_decoded,
                            'task_history_id'=>$task_history_id,
                            'user_id'=>Auth::user()->id,
                            'submit_for_approval'=>'not_submitted'
                        ]);
                    }

                    // add activity point for created task.

                    ActivityPoint::create([
                        'user_id'=>Auth::user()->id,
                        'task_id'=>$task_id_decoded,
                        'points'=>2,
                        'comments'=>'Task Updated',
                        'type'=>'task'
                    ]);

                    // add site activity record for global statistics.


                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$unit_id[0],
                        'objective_id'=>$objective_id[0],
                        'task_id'=>$task_id_decoded,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a>
                        updated task <a href="'.url('tasks/'.$task_id.'/'.$slug).'">'.$request->input('task_name').'</a>'
                    ]);

                    // After Created Unit send mail to site admin
                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Task Updated";

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });

                    $request->session()->flash('msg_val', "Task updated successfully!!!");
                    return redirect('tasks');
                }

                $unitsObj = Unit::where('status','active')->lists('name','id');
                $task_skills = JobSkill::lists('skill_name','id');
                $assigned_toUsers = User::where('id','!=',Auth::user()->id)->where('role','!=','superadmin')->get();
                $assigned_toUsers= $assigned_toUsers->lists('full_name','id');
                $taskObj = $task;
                $taskObj->task_action = str_replace(array("\r", "\n"), '', $taskObj->task_action);
                $objectiveObj = Objective::where('unit_id',$taskObj->unit_id)->get();
                $taskDocumentsObj = TaskDocuments::where('task_id',$task_id)->get();
                //$taskActionsObj = TaskAction::where('task_id',$task_id)->get();
                $taskEditor = TaskEditor::where('task_id',$task_id)->where('user_id',Auth::user()->id)->first();
                $otherRemainEditors = TaskEditor::where('task_id',$task_id)
                                    ->where('user_id','!=',Auth::user()->id)->where('submit_for_approval','not_submitted')->get();
                $otherEditorsDone = TaskEditor::where('task_id',$task_id)
                    ->where('user_id','!=',Auth::user()->id)->where('submit_for_approval','submitted')->get();

                $firstUserSubmitted = TaskEditor::where('task_id',$task_id)
                    ->where('user_id','!=',Auth::user()->id)->where('submit_for_approval','submitted')->where('first_user_to_submit','yes')
                    ->first();

                $availableDays ='';
                if(count($firstUserSubmitted) > 0){
                    $submittedDate = strtotime($firstUserSubmitted->updated_at);
                    $availableDays = time() - $submittedDate;
                    $availableDays = 8 - (int)date('d',$availableDays );

                }

                view()->share('objectiveObj',$objectiveObj);
                view()->share('assigned_toUsers',$assigned_toUsers);
                view()->share('task_skills',$task_skills );
                view()->share('unitsObj',$unitsObj);
                view()->share('taskObj',$taskObj);
                view()->share('taskDocumentsObj',$taskDocumentsObj);
                view()->share('taskEditor',$taskEditor);
                view()->share('otherRemainEditors',$otherRemainEditors );
                view()->share('otherEditorsDone',$otherEditorsDone);
                view()->share('availableDays',$availableDays);

                //view()->share('taskActionsObj',$taskActionsObj);
                $taskObj->estimated_completion_time_start = date('Y/m/d h:i',strtotime($taskObj->estimated_completion_time_start));
                $taskObj->estimated_completion_time_end = date('Y/m/d h:i',strtotime($taskObj->estimated_completion_time_end));
                $exploded_task_list = explode(",",$taskObj->skills);
                view()->share('exploded_task_list',$exploded_task_list );
                view()->share('editFlag',true);
                view()->share('actionListFlag',$taskObj->task_action);
                /*if(count($taskDocumentsObj) > 0)
                    view()->share('actionListFlag',true);
                else
                    view()->share('actionListFlag',false);*/
                return view('tasks.create');
            }
        }
        return view('errors.404');
    }

    /**
     * soft deleting the document of given task_id
     * @param Request $request
     * @return mixed
     */
    public function remove_task_documents(Request $request){
        $task_id = $request->input('task_id');
        $id = $request->input('id');
        $fromEdit = $request->input('fromEdit');

        $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
        $taskDocumentIDHashID = new Hashids('task document id hash',10,\Config::get('app.encode_chars'));

        $task_id = $taskIDHashID->decode($task_id);

        if(empty($task_id)){
            return \Response::json(['success'=>false]);
        }
        $task_id = $task_id[0];

        if($fromEdit  == "yes"){
            $taskHistoryObj = TaskEditor::join('task_history','task_editors.task_history_id','=','task_history.id')
                            ->where('task_id',$task_id)->where('user_id',Auth::user()->id)->orderBy('task_history.id','desc')->first();
            if(!empty($taskHistoryObj)){
                $taskDocuments = json_decode($taskHistoryObj->task_documents);
                if(!empty($taskDocuments)){
                    foreach($taskDocuments as $index=>$document)
                    {
                        if($id == $index){
                            if(file_exists($document->file_path))
                                unlink($document->file_path);
                            unset($taskDocuments[$index]);
                        }
                    }
                    TaskHistory::find($taskHistoryObj->id)->update(['task_documents'=>json_encode($taskDocuments)]);
                    return \Response::json(['success'=>true]);
                }
            }
        }
        else{
            $id = $taskDocumentIDHashID->decode($id);
            if(empty($id)){
                return \Response::json(['success'=>false]);
            }
            $id= $id[0];
            $taskDocumentObj = TaskDocuments::where('task_id',$task_id)->where('id',$id)->get();

            if(count($taskDocumentObj) > 0){
                TaskDocuments::where('task_id',$task_id)->where('id',$id)->delete();
                return \Response::json(['success'=>true]);
            }
        }
        return \Response::json(['success'=>false]);
    }

    /**
     * retrieve objective of selected unit_id
     * @param Request $request
     * @return mixed
     */
    public function get_objective(Request $request){
        $unit_id = $request->input('unit_id');
        if(!empty($unit_id)){
            $unitIDHashID = new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            if(!empty($unit_id)){
                $unit_id = $unit_id[0];
                $unitObj = Unit::where('id',$unit_id)->get();
                if(count($unitObj) > 0){
                    $objectivesObj = Objective::where('unit_id',$unit_id)->lists('name','id');
                    $return_arr = [];
                    if(count($objectivesObj) > 0){
                        foreach($objectivesObj as $id=>$val)
                            $return_arr[$objectiveIDHashID->encode($id)] = $val;
                    }
                    return \Response::json(['success'=>true,'objectives'=>$return_arr]);
                }
            }
        }
        return \Response::json(['success'=>false]);
    }

    public function get_tasks(Request $request){
        $obj_id = $request->input('obj_id');
        if(!empty($obj_id)){
            $objectiveIDHashID = new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $obj_id = $objectiveIDHashID->decode($obj_id);
            if(!empty($obj_id)){
                $obj_id = $obj_id[0];
                $objectiveObj = Objective::where('id',$obj_id)->get();
                if(count($objectiveObj) > 0){
                    $taskObj = Task::where('objective_id',$obj_id)->lists('name','id');
                    $return_arr = [];
                    $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
                    if(count($taskObj) > 0){
                        foreach($taskObj as $id=>$val)
                            $return_arr[$taskIDHashID->encode($id)] = $val;
                    }
                    return \Response::json(['success'=>true,'tasks'=>$return_arr]);
                }
            }
        }
        return \Response::json(['success'=>false]);
    }

    /**
     * function is used to display task details.
     * @param $task_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view($task_id){
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::with(['objective','task_documents'])->find($task_id);
                if(empty($taskObj))
                    return ('errors.404');

                $taskObj->unit=[];
                if(!empty($taskObj->objective))
                    $taskObj->unit = Unit::getUnitWithCategories($taskObj->unit_id);
                if(!empty($taskObj)){
                    view()->share('taskObj',$taskObj );

                    // to display listing of bidders to task creator or unit admin of this task.
                    /*$flag = Task::isTaskCreator($task_id);
                    if(!$flag ){
                        $flag =Task::isUnitAdminOfTask($task_id);
                    }*/

                    $flag =Task::isUnitAdminOfTask($task_id); // right now considered only unit admin can assigned task to bidder if they
                    // want task creator can also assign task to bidder then remove this and uncomment above lines
                    $taskBidders = [];
                    if($flag){
                        $taskBidders = TaskBidder::join('users','task_bidders.user_id','=','users.id')
                                        ->select(['users.first_name','users.last_name','users.id as user_id','task_bidders.*'])
                                        ->where('task_id',$task_id)->get();
                    }
                    view()->share('taskBidders',$taskBidders);
                    // end display listing of bidders

                    $availableFunds =Fund::getTaskDonatedFund($task_id);
                    $awardedFunds =Fund::getTaskAwardedFund($task_id);

                    view()->share('availableFunds',$availableFunds );
                    view()->share('awardedFunds',$awardedFunds );

                    $availableUnitFunds =Fund::getUnitDonatedFund($taskObj->unit_id);
                    $awardedUnitFunds =Fund::getUnitAwardedFund($taskObj->unit_id);

                    view()->share('availableUnitFunds',$availableUnitFunds );
                    view()->share('awardedUnitFunds',$awardedUnitFunds );

                    /*$site_activity = SiteActivity::where(function($query) use($taskObj){
                            return $query->where('unit_id',$taskObj->unit_id)
                                    ->orWhere('objective_id',$taskObj->objective_id)
                                    ->orWhere('task_id',$taskObj->id);
                    })->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));*/

                    $site_activity = SiteActivity::where('unit_id',$taskObj->unit_id)
                                    ->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));

                    view()->share('site_activity',$site_activity);
                    view()->share('unit_activity_id',$taskObj->unit_id);

                    return view('tasks.view');
                }
            }
        }
        return view('errors.404');
    }

    /**
     * function will delete the task.
     * @param Request $request
     * @return mixed
     */
    public function delete_task(Request $request)
    {
        // remove task related data from all table. like task_documents and task_actions etc with task deletion
        $task_id = $request->input('id');
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                if(count($taskObj) > 0){
                    $tasktempObj = $taskObj;

                    // delete task documents, task action and task
                    Task::deleteTask($task_id);

                    // add activity points for task deletion
                    ActivityPoint::create([
                        'user_id'=>Auth::user()->id,
                        'objective_id'=>$task_id,
                        'points'=>1,
                        'comments'=>'Task Deleted',
                        'type'=>'task'
                    ]);

                    // add site activity record for global statistics.
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$tasktempObj->unit_id,
                        'objective_id'=>$tasktempObj->objective_id,
                        'task_id'=>$task_id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a>
                        deleted task '.$tasktempObj->name
                    ]);

                    // After Created Unit send mail to site admin
                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Task Deleted";

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });
                }
                return \Response::json(['success'=>true]);
            }
        }
        return \Response::json(['success'=>false]);
    }

    /**
     * when user click on Submit for Approval button after edit task.
     * @param Request $request
     * @return mixed
     */
    public function submit_for_approval(Request $request){
        $task_id = $request->input('task_id');
        $task_id_encoded=$task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                $taskEditor = TaskEditor::where('task_id',$task_id)->where('user_id',Auth::user()->id)->get();
                if(!empty($taskObj) && count($taskEditor) > 0){
                    $otherEditor = TaskEditor::where('task_id',$task_id)->where('user_id','!=',Auth::user()->id)
                                    ->where('submit_for_approval','submitted')->where('first_user_to_submit','yes')->get();

                    $first_user_to_submit ="yes";
                    if(count($otherEditor) > 0)
                        $first_user_to_submit ="no";

                    TaskEditor::where('task_id',$task_id)->where('user_id',Auth::user()->id)->update(['submit_for_approval'=>'submitted',
                        'first_user_to_submit'=>$first_user_to_submit]);

                    $taskEditorObj =  TaskEditor::where('task_id',$task_id)->where('submit_for_approval','not_submitted')->count();
                    if($taskEditorObj == 0){
                        //$taskObj = Task::find($task_id);
                        if(!empty($taskObj)){
                            //$taskObj->update(['status'=>'awaiting_approval']);
                            $taskObj->update(['status'=>'approval']);



                            // add activity point for submit for approval task.
                            ActivityPoint::create([
                                'user_id'=>Auth::user()->id,
                                'task_id'=>$task_id,
                                'points'=>2,
                                'comments'=>'Task Approved',
                                'type'=>'task'
                            ]);

                            $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                            $user_id = $userIDHashID->encode(Auth::user()->id);

                            SiteActivity::create([
                                'user_id'=>Auth::user()->id,
                                'unit_id'=>$taskObj->unit_id,
                                'objective_id'=>$taskObj->objective_id,
                                'task_id'=>$task_id,
                                'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                                    .Auth::user()->first_name.' '.Auth::user()->last_name
                                    .'</a> submitted task approval <a href="'.url('tasks/'.$task_id_encoded.'/'.$taskObj->slug).'">'
                                    .$taskObj->name.'</a>'
                            ]);

                            // After Created Unit send mail to site admin
                            $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                            $unitCreator = User::find(Auth::user()->id);

                            $toEmail = $unitCreator->email;
                            $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                            $subject="Task approval submitted by".Auth::user()->first_name.' '.Auth::user()->last_name;

                            \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                            {
                                $message->to($toEmail,$toName)->subject($subject);
                                if(!empty($siteAdminemails))
                                    $message->bcc($siteAdminemails,"Admin")->subject($subject);

                                $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                            });

                            return \Response::json(['success'=>true,'status'=>'awaiting_approval']);
                        }
                    }


                    return \Response::json(['success'=>true,'status'=>'']);
                }
            }
        }
        return \Response::json(['success'=>false]);
    }

    public function bid_now(Request $request,$task_id){
        if(!empty($task_id)){
            $task_id_encoded = $task_id;
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);

            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                if($taskObj->status != "approval")
                    return \Redirect::to('/tasks');
                if(!empty($taskObj)){
                    if($request->isMethod('post')){

                        \Validator::extend('isCurrency', function($field,$value,$parameters){
                            //return true if field value is foo
                            return Helpers::isCurrency($value);
                        });

                        $validator = \Validator::make($request->all(), [
                            'amount' => 'required|isCurrency',
                            'comment' => 'required'
                        ],[
                            'amount.required' => 'This field is required.',
                            'amount.is_currency'=>'Please enter digits only'
                        ]);

                        if ($validator->fails())
                            return redirect()->back()->withErrors($validator)->withInput();

                        $taskBidders = TaskBidder::where('task_id',$task_id)->where('first_to_bid','yes')->count();
                        $first_to_bid="no";
                        if($taskBidders == 0)
                           $first_to_bid ="yes";

                        $chargeType = $request->input('charge_type');
                        $chargeAmountType ="points";
                        if($chargeType == "on")
                            $chargeAmountType ="amount";

                        TaskBidder::create([
                           'task_id'=>$task_id,
                            'user_id'=>Auth::user()->id,
                            'amount'=>$request->input('amount'),
                            'comment'=>$request->input('comment'),
                            'first_to_bid'=>$first_to_bid,
                            'charge_type'=>$chargeAmountType
                        ]);

                        $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                        $user_id = $userIDHashID->encode(Auth::user()->id);

                        SiteActivity::create([
                            'user_id'=>Auth::user()->id,
                            'unit_id'=>$taskObj->unit_id,
                            'objective_id'=>$taskObj->objective_id,
                            'task_id'=>$task_id,
                            'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                                .Auth::user()->first_name.' '.Auth::user()->last_name
                                .'</a> bid <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                                .$taskObj->name.'</a>'
                        ]);

                        $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                        $unitCreator = User::find(Auth::user()->id);

                        $toEmail = $unitCreator->email;
                        $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                        $subject="Task bid by".Auth::user()->first_name.' '.Auth::user()->last_name;

                        \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                        {
                            $message->to($toEmail,$toName)->subject($subject);
                            if(!empty($siteAdminemails))
                                $message->bcc($siteAdminemails,"Admin")->subject($subject);

                            $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                        });

                        $request->session()->flash('msg_val', "Task bid successfully!!!");
                        return redirect('tasks');
                    }
                    else{
                        $taskBidder = TaskBidder::where('task_id',$task_id)->where('user_id',Auth::user()->id)->first();
                        $daysRemainingTobid = TaskBidder::getCountDown($task_id);

                        view()->share('daysRemainingTobid',$daysRemainingTobid);
                        view()->share('taskBidder',$taskBidder );
                        view()->share('taskObj',$taskObj);
                        return view('tasks.bid_now');
                    }
                }
            }
        }
        return view('errors.404');
    }

    public function assign_task(Request $request){
        $user_id = $request->input('uid');
        $task_id = $request->input('tid');
        $task_id_encoded =$task_id;
        if(!empty($task_id) && !empty($user_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);

            $userIDHashID = new Hashids('user id hash',10,\Config::get('app.encode_chars'));
            $user_id = $userIDHashID->decode($user_id);

            if(!empty($task_id) && !empty($user_id)){
                $task_id = $task_id[0];
                $user_id = $user_id[0];
                $taskObj = Task::find($task_id);
                $userObj = User::find($user_id);
                if(!empty($taskObj) && !empty($userObj)){
                    $taskBidderObj = TaskBidder::where('task_id',$task_id)->where('user_id',$user_id)->first();
                    if(!empty($taskBidderObj)){
                        $taskBidderObj->update(['status'=>'offer_sent']);
                        Task::where('id','=',$task_id)->update(['status'=>'assigned','assign_to'=>$user_id]);

                        // add activity point for submit for approval task.
                        ActivityPoint::create([
                            'user_id'=>Auth::user()->id,
                            'task_id'=>$task_id,
                            'points'=>3,
                            'comments'=>'Bid Selection',
                            'type'=>'task'
                        ]);

                        $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                        $unitCreator = User::find($user_id);

                        $toEmail = $unitCreator->email;
                        $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                        $subject="Task assigned to ".$unitCreator->first_name.' '.$unitCreator->last_name;

                        \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                        {
                            $message->to($toEmail,$toName)->subject($subject);
                            if(!empty($siteAdminemails))
                                $message->bcc($siteAdminemails,"Admin")->subject($subject);

                            $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                        });

                        $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                        $loggedin_user_id = $userIDHashID->encode(Auth::user()->id);
                        $user_id = $userIDHashID->encode($user_id);

                        SiteActivity::create([
                            'user_id'=>Auth::user()->id,
                            'unit_id'=>$taskObj->unit_id,
                            'objective_id'=>$taskObj->objective_id,
                            'task_id'=>$task_id,
                            'comment'=>'<a href="'.url('userprofiles/'.$loggedin_user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                                .Auth::user()->first_name.' '.Auth::user()->last_name
                                .'</a> assigned <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                                .$taskObj->name.'</a> to <a href="'.url('userprofiles/'.$user_id .'/'.strtolower($userObj->first_name.'_'.$userObj->last_name)).'">'
                                .$userObj->first_name.' '.$userObj->last_name
                                .'</a>'
                        ]);


                    }
                    return \Response::json(['success'=>true]);
                }
            }
        }
        return \Response::json(['success'=>false]);
    }

    public function check_assigned_task(){
        $taskBidderObj = TaskBidder::join('tasks','task_bidders.task_id','=','tasks.id')
                        ->whereIn('task_bidders.status',['offer_sent','re_assigned'])
                        ->where('task_bidders.user_id',Auth::user()->id)
                        ->select(['tasks.name','tasks.slug','task_bidders.*'])
                        ->first();

        if(!empty($taskBidderObj)){

            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->encode($taskBidderObj->task_id);

           /* $html = "Your bid has been selected and task (<a href='".url('tasks/'.$task_id.'/'.$taskBidderObj->slug)."'>".$taskBidderObj->name."</a>) " .
                "has been assigned to you.";*/

            if($taskBidderObj->status == "offer_sent"){
                $html = '<div class="alert alert-warning" style="padding:15px;margin-bottom:0px;margin-top:10px;margin-bottom:10px">'.
                          '<a href="#" class="close" data-dismiss="alert" aria-label="close" style="display:none;">&times;</a>'.
                          '<strong>Task Assigned!</strong> Your bid has been selected and task(<b>'.$taskBidderObj->name.'</b>) ' .
                            'has been assigned to you.'.
                        '<div class="pull-right">' .
                            '<a class="btn btn-success btn-xs offer" data-task_id="'.$task_id.'" style="margin-right:5px;">Accept</a>' .
                            '<a class="btn btn-danger btn-xs offer" data-task_id="'.$task_id.'">Reject</a></div>'.
                        '</div>';
            }
            else{
                $html = '<div class="alert alert-warning" style="padding:15px;margin-bottom:0px;margin-top:10px;">'.
                    '<strong>Task Re-Assigned!</strong> The task (<b>'.$taskBidderObj->name.'</b>) has been re-assigned to you.' .
                    '<a href="#" class="close" data-dismiss="alert" aria-label="close" style="display:none;">&times;</a>'.
                    '<div class="pull-right">' .
                        '<a class="btn btn-success btn-xs re_assigned offer" data-task_id="'.$task_id.'" style="margin-right:5px;">Ok</a>' .
                        '<a class="btn btn-danger btn-xs re_assigned offer" data-task_id="'.$task_id.'">Cancel</a></div>'.
                    '</div>';
            }

            return \Response::json(['success'=>true,'html'=>$html,'task_id'=>$task_id]);

        }
        return \Response::json(['success'=>false]);
    }

    /**
     * function is used to accept the offer sent by unit admin
     * @param Request $request
     * @return mixed
     */
    public function accept_offer(Request $request){
        $task_id = $request->input('task_id');
        $task_id_encoded=$task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                if(!empty($taskObj)){
                    $taskObj->update(['status'=>'in_progress']);
                    $taskBidder = TaskBidder::where('task_id',$task_id)->where('user_id',Auth::user()->id)->first();
                    if(!empty($taskBidder)){
                        $taskBidder->update(['status'=>'offer_accepted']);
                    }
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$taskObj->unit_id,
                        'objective_id'=>$taskObj->objective_id,
                        'task_id'=>$task_id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a> accept offer of task <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                            .$taskObj->name.'</a>'
                    ]);


                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Task accepted by ".$unitCreator->first_name.' '.$unitCreator->last_name;

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

    /**
     * Function is used to reject the offer sent by unit admin.
     * @param Request $request
     * @return mixed
     */
    public function reject_offer(Request $request){
        $task_id = $request->input('task_id');
        $task_id_encoded =$task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                if(!empty($taskObj)){
                    $taskObj->update(['assign_to'=>null,'status'=>'awaiting_assignment']);
                    $taskBidder = TaskBidder::where('task_id',$task_id)->where('user_id',Auth::user()->id)->first();
                    if(!empty($taskBidder)){
                        $taskBidder->update(['status'=>'offer_rejected']);
                    }
                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id = $userIDHashID->encode(Auth::user()->id);

                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$taskObj->unit_id,
                        'objective_id'=>$taskObj->objective_id,
                        'task_id'=>$task_id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a> reject offer of task <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                            .$taskObj->name.'</a>'
                    ]);

                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find(Auth::user()->id);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Task rejected by ".$unitCreator->first_name.' '.$unitCreator->last_name;

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

    /**
     * function is used to get bidding information.
     * @param Request $request
     * @return mixed
     */
    public function get_biding_details(Request $request){
        $id = $request->input('id');
        if(!empty($id)){
            $taskBidder = TaskBidder::find($id);
            if(!empty($taskBidder)){
                $html = '<div class="row "><div class="col-sm-12 form-group">' .
                        '<label class="control-label" style="width:100%;font-weight:bold">Amount</label><span>'.$taskBidder->amount.
                        '</div><div class="col-sm-12 form-group">' .
                        '<label class="control-label" style="width:100%;font-weight:bold">Charge Type</label><span>'.$taskBidder->charge_type.
                        '</div><div class="col-sm-12 form-group">' .
                        '<label class="control-label" style="width:100%;font-weight:bold">Comment</label><span>'.$taskBidder->comment.
                        '</div></div>';
            return \Response::json(['success'=>true,'html'=>$html]);
            }

        }
        return \Response::json(['success'=>false]);
    }

    public function complete_task(Request $request,$task_id){
        $task_id_encoded=$task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskCompleteObj  = TaskComplete::join('users','task_complete.user_id','=','users.id')
                    ->where('task_id',$task_id)
                    ->select(['task_complete.*','users.first_name','users.last_name'])
                    ->orderBy('id','asc')
                    ->get();

                if(Auth::user()->role == "superadmin")
                    $taskObj = Task::where('id','=',$task_id)->first();
                else
                    $taskObj = Task::where('id','=',$task_id)->where('assign_to',Auth::user()->id)->where('status','in_progress')->first();

                $taskEditors = RewardAssignment::where('task_id',$task_id)->get();
                $rewardAssigned=true;
                if(empty($taskEditors) || count($taskEditors) == 0){
                    $taskEditors = TaskEditor::where('task_id',$task_id)->where('user_id','!=',$taskObj->assign_to)->get();
                    $rewardAssigned=false;
                }

                if(!empty($taskObj)){
                    if($request->isMethod('post')){

                        $validator = \Validator::make($request->all(), [
                            'comment' => 'required'
                        ]);

                        if ($validator->fails())
                            return redirect()->back()->withErrors($validator)->withInput();

                        // upload documents of task.
                        $task_documents=[];
                        $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                        $user_id_encoded = $userIDHashID->encode(Auth::user()->id);
                        if($request->hasFile('attachments')) {
                            $files = $request->file('attachments');
                            if(count($files) > 0){
                                $totalAvailableDocs = TaskComplete::where('task_id',$task_id)->get();
                                $totalAvailableDocs= count($totalAvailableDocs) + 1;
                                foreach($files as $index=>$file){
                                    if(!empty($file)){

                                        $rules = ['attachments' => 'required', 'extension' => 'required|in:doc,docx,pdf,txt,jpg,png,ppt,pptx,jpeg,doc,xls,xlsx'];
                                        $fileData = ['attachments' => $file, 'extension' => strtolower($file->getClientOriginalExtension())];
                                        // doing the validation, passing post data, rules and the messages
                                        $validator = \Validator::make($fileData, $rules);
                                        if (!$validator->fails()) {
                                            if ($file->isValid()) {
                                                $destinationPath = base_path().'/uploads/tasks/'.$task_id_encoded; // upload path
                                                if(!\File::exists($destinationPath)){
                                                    $oldumask = umask(0);
                                                    @mkdir($destinationPath, 0775); // or even 01777 so you get the sticky bit set
                                                    umask($oldumask);
                                                }

                                                $destinationPath =$destinationPath.'/completed_docs';
                                                if(!\File::exists($destinationPath)){
                                                    $oldumask = umask(0);
                                                    @mkdir($destinationPath, 0775); // or even 01777 so you get the sticky bit set
                                                    umask($oldumask);
                                                }

                                                $destinationPath =$destinationPath.'/'.$user_id_encoded;
                                                if(!\File::exists($destinationPath)){
                                                    $oldumask = umask(0);
                                                    @mkdir($destinationPath, 0775); // or even 01777 so you get the sticky bit set
                                                    umask($oldumask);
                                                }

                                                $file_name =$file->getClientOriginalName();
                                                $extension = $file->getClientOriginalExtension(); // getting image extension
                                                $fileName = $task_id_encoded.'_'.$totalAvailableDocs . '.' . $extension; // renaming image
                                                $file->move($destinationPath, $fileName); // uploading file to given path

                                                // insert record into task_documents table
                                                $task_documents[]=['file_name'=>$file_name,
                                                    'file_path'=>'uploads/tasks/'.$task_id_encoded.'/completed_docs/'.$user_id_encoded.'/'.$fileName];

                                                $totalAvailableDocs++;
                                            }
                                        }
                                    }

                                }
                            }
                        }

                        TaskComplete::create([
                            'user_id'=>Auth::user()->id,
                            'task_id'=>$task_id,
                            'attachments'=>json_encode($task_documents),
                            'comments'=>$request->input('comment')
                        ]);

                        $taskBidder = TaskBidder::where('task_id',$task_id)->where('user_id',Auth::user()->id)->where('task_bidders.status',
                            'offer_accepted')->first();
                        if(!empty($taskBidder))
                            $taskBidder->update(['status'=>'task_completed']);

                        Task::find($task_id)->update(['status'=>'completion_evaluation']);

                        // add activity point for submit for approval task.
                        ActivityPoint::create([
                            'user_id'=>Auth::user()->id,
                            'task_id'=>$task_id,
                            'points'=>50,
                            'comments'=>'Task Completed',
                            'type'=>'task'
                        ]);

                        SiteActivity::create([
                            'user_id'=>Auth::user()->id,
                            'unit_id'=>$taskObj->unit_id,
                            'objective_id'=>$taskObj->objective_id,
                            'task_id'=>$task_id,
                            'comment'=>'<a href="'.url('userprofiles/'.$user_id_encoded.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                                .Auth::user()->first_name.' '.Auth::user()->last_name
                                .'</a> complete task <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                                .$taskObj->name.'</a>'
                        ]);

                        $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                        $unitCreator = User::find(Auth::user()->id);

                        $toEmail = $unitCreator->email;
                        $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                        $subject="Task completed by ".$unitCreator->first_name.' '.$unitCreator->last_name;

                        \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                        {
                            $message->to($toEmail,$toName)->subject($subject);
                            if(!empty($siteAdminemails))
                                $message->bcc($siteAdminemails,"Admin")->subject($subject);

                            $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                        });

                        $request->session()->flash('msg_val', "Task Completed successfully!!!");
                        return redirect('tasks');
                    }
                    else{
                        view()->share('taskObj',$taskObj);
                        view()->share('taskCompleteObj',$taskCompleteObj);
                        view()->share('taskEditors',$taskEditors );
                        view()->share('rewardAssigned',$rewardAssigned);
                        return view('tasks.partials.complete_task');
                    }
                }
            }
        }
        return view('errors.404');
    }

    public function re_assign(Request $request,$task_id){
        $task_id_encoded = $task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                if(!empty($taskObj)){
                    Task::find($task_id)->update(['status'=>'assigned']);
                    $taskBidderObj = TaskBidder::where('task_id',$task_id)->where('user_id',$taskObj->assign_to)->first();
                    if(!empty($taskBidderObj))
                        $taskBidderObj->update(['status'=>'re_assigned']);

                    $validator = \Validator::make($request->all(), [
                        'comment' => 'required'
                    ]);

                    if ($validator->fails())
                        return redirect()->back()->withErrors($validator)->withInput();

                    TaskComplete::create([
                        'user_id'=>Auth::user()->id,
                        'task_id'=>$task_id,
                        'attachments'=>null,
                        'comments'=>$request->input('comment')
                    ]);

                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id_encoded = $userIDHashID->encode(Auth::user()->id);
                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$taskObj->unit_id,
                        'objective_id'=>$taskObj->objective_id,
                        'task_id'=>$taskObj->id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id_encoded.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a> re-assigned task <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                            .$taskObj->name.'</a>'
                    ]);

                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find($taskObj->assign_to);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Task re-assigned to ".$unitCreator->first_name.' '.$unitCreator->last_name;

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });
                    $request->session()->flash('msg_val', "Task assigned successfully!!!");
                    return redirect('tasks');
                }
            }
        }
        $request->session()->flash('msg_val', "Task were not found. Please again later.");
        $request->session()->flash('msg_type', "danger");

        return redirect('tasks');
    }

    public function mark_as_complete(Request $request,$task_id){
        $task_id_encoded=$task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskObj = Task::find($task_id);
                if(!empty($taskObj)){
                    $taskEditors = RewardAssignment::where('task_id',$task_id)->get();

                    if(empty($taskEditors) || count($taskEditors) == 0)
                        $taskEditors = TaskEditor::where('task_id',$task_id)->where('user_id','!=',$taskObj->assign_to)->get();



                    // validate percentage split.
                    $percentageError = [];
                    $totalPercentage=0;
                    if(!empty($taskEditors) && count($taskEditors) > 0){
                        $allUsersRewardPercentage = $request->input('amount_percentage');
                        if(!empty($allUsersRewardPercentage)){
                            foreach($allUsersRewardPercentage  as $u_id=>$percentage){
                                $editorExist = TaskEditor::where('task_id',$task_id)->where('user_id',$u_id)->get();
                                if($taskObj->user_id != $u_id && (empty($editorExist) || count($editorExist) == 0))
                                    $percentageError['amount_percentage['.$u_id.']']="Please enter percentage";
                                else
                                    $totalPercentage+=intval($percentage);
                            }
                        }

                        if(!empty($percentageError))
                            return redirect()->back()->withErrors($percentageError)->withInput();

                        if($totalPercentage < 100 || $totalPercentage > 100)
                            return redirect()->back()->withErrors(['split_error'=>"Please split 100% among all users."])->withInput();
                    }

                    // insert task reward assignment into table. to use where transaction take place. to give % of amount to user.
                    if(!empty($taskEditors) && count($taskEditors) > 0 ){
                        $allUsersRewardPercentage = $request->input('amount_percentage');
                        if(!empty($allUsersRewardPercentage)){
                            foreach($allUsersRewardPercentage  as $u_id=>$percentage){
                                $rewardAssignedObj = RewardAssignment::where('task_id',$task_id)->where('user_id',$u_id)->first();
                                if(!empty($rewardAssignedObj) && count($rewardAssignedObj) > 0){
                                    $rewardAssignedObj->update([
                                        'reward_percentage'=>$percentage
                                    ]);
                                }
                                else{
                                    RewardAssignment::create([
                                        'task_id'=>$task_id,
                                        'user_id'=>$u_id,
                                        'reward_percentage'=>$percentage
                                    ]);
                                }
                            }
                        }
                    }

                    // Transfer rewards to all users
                    \App\User::transferRewards($task_id);

                    Task::find($task_id)->update(['status'=>'completed']);

                    $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                    $user_id_encoded = $userIDHashID->encode(Auth::user()->id);

                    $taskBidderObj = TaskBidder::where('task_id',$taskObj->id)->where('user_id',
                        $taskObj->assign_to)->where('charge_type','points')->first();
                    if(!empty($taskBidderObj) && count($taskBidderObj) > 0)
                    {
                        ActivityPoint::create([
                            'user_id'=>$taskObj->assign_to,
                            'task_id'=>$taskObj->id,
                            'points'=>$taskBidderObj->amount,
                            'comments'=>'Task Completed Points',
                            'type'=>'task'
                        ]);
                    }
                    SiteActivity::create([
                        'user_id'=>Auth::user()->id,
                        'unit_id'=>$taskObj->unit_id,
                        'objective_id'=>$taskObj->objective_id,
                        'task_id'=>$taskObj->id,
                        'comment'=>'<a href="'.url('userprofiles/'.$user_id_encoded.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                            .Auth::user()->first_name.' '.Auth::user()->last_name
                            .'</a> approved completed task <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                            .$taskObj->name.'</a>'
                    ]);


                    $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                    $unitCreator = User::find($taskObj->assign_to);

                    $toEmail = $unitCreator->email;
                    $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                    $subject="Task completed by supperadmin ";

                    \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                    {
                        $message->to($toEmail,$toName)->subject($subject);
                        if(!empty($siteAdminemails))
                            $message->bcc($siteAdminemails,"Admin")->subject($subject);

                        $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                    });

                    $request->session()->flash('msg_val', "Task completed successfully!!!");
                    return redirect('tasks');
                }
            }
        }
        return redirect('errors.404');
    }

    public function cancel_task(Request $request,$task_id){
        $task_id_encoded=$task_id;
        if(!empty($task_id)){
            $taskIDHashID = new Hashids('task id hash',10,\Config::get('app.encode_chars'));
            $task_id = $taskIDHashID->decode($task_id);
            if(!empty($task_id)){
                $task_id = $task_id[0];
                $taskCancelObj  = TaskCancel::join('users','task_cancel.user_id','=','users.id')
                    ->where('task_id',$task_id)
                    ->select(['task_cancel.*','users.first_name','users.last_name'])
                    ->orderBy('id','asc')
                    ->get();
                if(Auth::user()->role == "superadmin")
                    $taskObj = Task::where('id','=',$task_id)->first();
                else
                    $taskObj = Task::where('id','=',$task_id)->where('assign_to',Auth::user()->id)->where('status','in_progress')->first();
                if(!empty($taskObj)){
                    if($request->isMethod('post')){
                        $validator = \Validator::make($request->all(), [
                            'comment' => 'required'
                        ]);

                        if ($validator->fails())
                            return redirect()->back()->withErrors($validator)->withInput();

                        TaskCancel::create([
                            'user_id'=>Auth::user()->id,
                            'task_id'=>$task_id,
                            'comments'=>$request->input('comment')
                        ]);

                        $taskBidder = TaskBidder::where('task_id',$task_id)->where('user_id',Auth::user()->id)->where('task_bidders.status',
                            'offer_accepted')->first();
                        if(!empty($taskBidder))
                            $taskBidder->update(['status'=>'task_canceled']);

                        Task::find($task_id)->update(['status'=>'cancelled']);

                        // add activity point for submit for cancel task.
                       /* ActivityPoint::create([
                            'user_id'=>Auth::user()->id,
                            'task_id'=>$task_id,
                            'points'=>50,
                            'comments'=>'Task Completed',
                            'type'=>'task'
                        ]);*/

                        $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
                        $user_id_encoded = $userIDHashID->encode(Auth::user()->id);

                        SiteActivity::create([
                            'user_id'=>Auth::user()->id,
                            'unit_id'=>$taskObj->unit_id,
                            'objective_id'=>$taskObj->objective_id,
                            'task_id'=>$task_id,
                            'comment'=>'<a href="'.url('userprofiles/'.$user_id_encoded.'/'.strtolower(Auth::user()->first_name.'_'.Auth::user()->last_name)).'">'
                                .Auth::user()->first_name.' '.Auth::user()->last_name
                                .'</a> cancelled task <a href="'.url('tasks/'.$task_id_encoded .'/'.$taskObj->slug).'">'
                                .$taskObj->name.'</a>'
                        ]);

                        $siteAdminemails = User::where('role','superadmin')->pluck('email')->all();
                        $unitCreator = User::find(Auth::user()->id);

                        $toEmail = $unitCreator->email;
                        $toName= $unitCreator->first_name.' '.$unitCreator->last_name;
                        $subject="Task cancelled by ".$toName;

                        \Mail::send('emails.registration', ['userObj'=> $unitCreator ], function($message) use ($toEmail,$toName,$subject,$siteAdminemails)
                        {
                            $message->to($toEmail,$toName)->subject($subject);
                            if(!empty($siteAdminemails))
                                $message->bcc($siteAdminemails,"Admin")->subject($subject);

                            $message->from(\Config::get("app.support_email"), \Config::get("app.site_name"));
                        });

                        $request->session()->flash('msg_val', "Task Cancelled successfully!!!");
                        return redirect('tasks');
                    }
                    else{
                        view()->share('taskObj',$taskObj);
                        view()->share('taskCancelObj',$taskCancelObj);
                        return view('tasks.partials.cancel_task');
                    }
                }
            }
        }
    }

    public function lists(Request $request)
    {
        $unit_id = $request->segment(2);
        if(!empty($unit_id)){
            $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)){
                $unit_id= $unit_id[0];
                $taskObj = Task::where('unit_id',$unit_id)->orderBy('tasks.id','desc')->paginate(\Config::get('app.page_limit'));
                $taskObj->unit = Unit::getUnitWithCategories($unit_id);
                $site_activity = SiteActivity::where('unit_id',$unit_id)->orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
                view()->share('taskObj',$taskObj);
                view()->share('site_activity',$site_activity);
                view()->share('unit_activity_id',$unit_id);
                return view('tasks.partials.list');
            }
        }
        return view('errors.404');
    }

    public function get_tasks_paginate(Request $request){
        $from_page = $request->input('from_page');
        $objective_id = $request->input('objective_id');
        $unit_id = $request->input('unit_id');
        $page_limit = \Config::get('app.page_limit');
        $taskObj = Task::orderBy('id', 'desc');
        if(!empty($unit_id )) {
            $unitIDHashID= new Hashids('unit id hash',10,\Config::get('app.encode_chars'));
            $unit_id = $unitIDHashID->decode($unit_id);
            if(!empty($unit_id)) {
                $unit_id = $unit_id[0];
                $taskObj = $taskObj->where('unit_id', $unit_id);
            }
        }
        if(!empty($objective_id)) {
            $objectiveIDHashID= new Hashids('objective id hash',10,\Config::get('app.encode_chars'));
            $objective_id = $objectiveIDHashID->decode($objective_id);
            if(!empty($objective_id)) {
                $objective_id = $objective_id[0];
                $taskObj = $taskObj->where('objective_id', $objective_id);
            }
        }
        $taskObj = $taskObj->paginate($page_limit);
        view()->share('tasks',$taskObj);
        view()->share('from_page',$from_page);
        view()->share('unit_id',$unit_id);
        view()->share('objective_id',$objective_id);
        $html = view('tasks.partials.more_tasks')->render();
        return \Response::json(['success'=>true,'html'=>$html]);

    }
}
