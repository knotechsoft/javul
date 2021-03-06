<?php

namespace App\Http\Controllers;

use App\ActivityPoint;
use App\AreaOfInterest;
use App\JobSkill;
use App\Objective;
use App\SiteActivity;
use App\Task;
use App\TaskBidder;
use App\Unit;
use Hashids\Hashids;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(){
        $this->middleware('auth',['except'=>['user_profile']]);
    }

    public function user_profile(Request $request,$user_id)
    {
        if(!empty($user_id)){
            $userIDHashID= new Hashids('user id hash',10,\Config::get('app.encode_chars'));
            $user_id = $userIDHashID->decode($user_id);
            if(!empty($user_id)){
                $user_id = $user_id [0];
                $userObj = User::find($user_id);
                $unitsObj = Unit::with(['objectives','tasks'])->where('units.user_id',$user_id)->get();
                $objectivesObj = Objective::where('user_id',$user_id)->get();
                $tasksObj = Task::where('user_id',$user_id)->get();

                $activityPoints = ActivityPoint::where('user_id',$user_id)->sum('points');
                $site_activities = SiteActivity::where('user_id',$user_id)->take(10)->orderBy('created_at','desc')->get();

                $skills = [];
                if(!empty($userObj->job_skills))
                    $skills = JobSkill::whereIn('id',explode(",",$userObj->job_skills))->get();

                $interestObj = [];
                if(!empty($userObj->job_skills))
                    $interestObj = AreaOfInterest::whereIn('id',explode(",",$userObj->area_of_interest))->get();

                view()->share('objectivesObj',$objectivesObj);
                view()->share('tasksObj',$tasksObj);
                view()->share('interestObj',$interestObj);
                view()->share('skills',$skills);
                view()->share('site_activities',$site_activities );
                view()->share('activityPoints',$activityPoints);
                view()->share('userObj',$userObj);
                view()->share('unitsObj',$unitsObj);

                return view('users.profile');
            }
        }
        return view('errors.404');
    }

    public function my_tasks(){
        $myBids = TaskBidder::join('tasks','task_bidders.task_id','=','tasks.id')->where('task_bidders.user_id',
            Auth::user()->id)->whereNull('task_bidders.status')->select(['tasks.name','tasks.slug','tasks.status as task_status',
                'task_bidders.*'])->get();
        $myAssignedTask = Task::where('status','in_progress')->where('assign_to',Auth::user()->id)->get();

        $myEvaluationTask =[];
        $myCancelledTask = [];
        if(Auth::user()->role == "superadmin"){
            $myEvaluationTask = Task::join('task_complete','tasks.id','=','task_complete.task_id')
                ->join('users','task_complete.user_id','=','users.id')
                ->select(['tasks.name','slug','tasks.status','users.first_name','users.last_name','users.id as user_id',
                    'tasks.id as task_id','task_complete.attachments','task_complete.comments'])
                ->where('tasks.status','completion_evaluation')
                ->groupBy('task_complete.task_id')
                ->get();

            $myCancelledTask = Task::join('task_cancel','tasks.id','=','task_cancel.task_id')
                ->join('users','task_cancel.user_id','=','users.id')
                ->select(['tasks.name','slug','tasks.status','users.first_name','users.last_name','users.id as user_id',
                    'tasks.id as task_id','task_cancel.comments'])
                ->where('tasks.status','cancelled')
                ->groupBy('task_cancel.task_id')
                ->get();
            /*$myEvaluationTask = Task::with(['task_complete','task_complete.users'])
                    ->where('status','completion_evaluation')
                    ->get();*/
        }

        $site_activity = SiteActivity::orderBy('id','desc')->paginate(\Config::get('app.site_activity_page_limit'));
        view()->share('site_activity',$site_activity);
        view()->share('site_activity_text','global activity log');

        view()->share('myCancelledTask',$myCancelledTask);
        view()->share('myEvaluationTask',$myEvaluationTask);
        view()->share('myBids',$myBids);
        view()->share('myAssignedTask',$myAssignedTask);

        return view('users.my_tasks');
    }
}
