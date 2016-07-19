@extends('layout.default')
@section('page-css')
<style>
    .related_para{margin:0 0 10px;}
</style>
@endsection
@section('content')
<div class="container">
    <div class="row">
        @include('elements.user-menu',['page'=>'units'])
    </div>
    <div class="row form-group">
        <div class="col-sm-12 ">
            <div class="col-sm-6 grey-bg unit_description both-div" style="min-height: 250px;">
                <h1 class="unit-heading">
                    <span class="glyphicon glyphicon-list-alt"></span> {{$unitObj->name}}

                </h1>
                @if(!empty($cityName))
                    <p><span class="glyphicon glyphicon-map-marker"> </span> &nbsp;{{$cityName}}</p>
                @endif
                <p><span class="glyphicon glyphicon-tag"> </span> Unit Categories&nbsp;: {{$unitObj->category_name}}</p>
                <a href="{!! url('units/'.$unitIDHashID->encode($unitObj->id).'/edit') !!}" id="edit_unit_btn" class="btn orange-bg">
                    <span class="glyphicon glyphicon-plus"></span> Edit Unit
                </a>
            </div>
            <div class="col-sm-6 grey-bg unit_description">
                <div class="row">
                    <div class="col-sm-offset-5 col-sm-7">
                        <div class="panel form-group marginTop10">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-xs-12">
                                        <strong>{!! trans('messages.unit_funds')!!}</strong>
                                    </div>
                                    <div class="col-xs-6">{!! trans('messages.available') !!}</div>
                                    <div class="col-xs-6 text-right">{{number_format($availableFunds,0)}}$</div>
                                    <div class="col-xs-6">{!! trans('messages.awarded') !!}</div>
                                    <div class="col-xs-6 text-right">{{number_format($awardedFunds,0)}}$</div>
                                </div>
                                <div>
                                    <div class="col-xs-12 text-right" style="padding-right: 0px;">
                                        <a class="btn orange-bg btn-xs" href="{!! url('funds/donate/unit/'.$unitIDHashID->encode($unitObj->id)) !!}">
                                            {!! trans('messages.add_funds') !!}
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-xs-5">
                                        <strong>{!! trans('messages.unit_links') !!}</strong>
                                    </div>
                                    <div class="col-xs-7 text-right">
                                        <a href="#">{!! trans('messages.forum') !!}</a> | <a href="#">{!! trans('messages.wiki') !!}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-xs-8">
                                        <strong>Tasks for bidding</strong>
                                    </div>
                                    <div class="col-xs-4 text-right">
                                        @if($taskForBidding > 0)
                                        <a href="{!! url('units/available_bid/'.$unitIDHashID->encode($unitObj->id)) !!}">
                                            {{$taskForBidding}} (bid now)
                                        </a>
                                        @else
                                            <span class="badge">0</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(count($related_units) > 0 || !empty($unitObj->parent_id))
    <?php $i=1;?>
    <div class="row form-group">
        <div class="col-sm-12">
            <div class="panel panel-default panel-dark-grey">
                <div class="panel-heading">
                    <h4>Relations to Other Units</h4>
                </div>
                <div class="panel-body relationULPanel">
                    <ul class="relationUL">
                        @if(!empty($unitObj->parent_id))
                        <li>Parent:
                            <?php $slug = \App\Unit::getSlug($unitObj->parent_id); ?>
                            <a href="{!! url('units/'.$unitIDHashID->encode($unitObj->parent_id).'/'.$slug) !!}"
                               class="no-decoration">
                                <span class="label label-default">{{\App\Unit::getUnitName($unitObj->parent_id)}}</span>
                            </a>
                        </li>
                        @endif
                        @if(!empty($related_units))
                        <li>Related:
                            @foreach($related_units as $id=>$unit_name)
                            <?php $slug = \App\Unit::getSlug($id); ?>
                            <a href="{!! url('units/'.$unitIDHashID->encode($id).'/'.$slug) !!}" class="no-decoration">
                                <span class="label label-default">{{$unit_name}} </span>
                            </a>
                            <?php $i++; ?>
                            @endforeach
                        </li>
                        @endif

                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div class="row form-group">
        <div class="col-sm-12">
            <div class="panel panel-default panel-dark-grey">
                <div class="panel-heading">
                    <h4>Unit Description</h4>
                </div>
                <div class="panel-body">
                    {!! $unitObj->description !!}
                </div>
            </div>
        </div>
    </div>
    <div class="row form-group">
        <div class="col-sm-12">
            <div class="panel panel-default panel-dark-grey">
                <div class="panel-heading">
                    <h4>{!! trans('messages.objectives') !!}</h4>
                </div>
                <div class="panel-body table-inner table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>{!! trans('messages.date_created') !!}</th>
                            <th>{!! trans('messages.title') !!}</th>
                            <th>{!! trans('messages.task_statistics') !!}</th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th>{!! trans('messages.available') !!}</th>
                            <th>{!! trans('messages.in_progress') !!}</th>
                            <th>{!! trans('messages.completed') !!}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                            @if(count($objectivesObj) > 0)
                                @foreach($objectivesObj as $obj)
                                    <tr>
                                        <td>{{\App\Library\Helpers::timetostr($obj->created_at)}}</td>
                                        <td>
                                            <a href="{!! url('objectives/'.$objectiveIDHashID->encode($obj->id).'/'.$obj->slug) !!}"
                                               title="edit">
                                                {{$obj->name}}
                                            </a>
                                        </td>
                                        <td>{{\App\Task::getTaskCount('available',$obj->id)}}</td>
                                        <td>{{\App\Task::getTaskCount('in-progress',$obj->id)}}</td>
                                        <td>{{\App\Task::getTaskCount('completed',$obj->id)}}</td>
                                        <td>
                                            @if(\Auth::check())
                                            <a class="btn btn-xs btn-primary"
                                               href="{!! url('objectives/'.$objectiveIDHashID->encode($obj->id).'/edit') !!}" title="edit">
                                                <span class="glyphicon glyphicon-edit"></span>
                                            </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6">No record(s) found.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-sm-12">
            <a class="btn orange-bg" id="add_objective_btn" href="{!! url('objectives/'.$unitIDHashID->encode($unitObj->id).'/add') !!}">
                <span class="glyphicon glyphicon-plus"></span> {!! trans('messages.add_objective') !!}
            </a>
            <!--<button class="btn orange-bg" id="see_all_objective_btn" type="button">{!! trans('messages.see_all_objectives') !!}</button>-->
        </div>
    </div>
    <!--<div class="row form-group">
        <div class="col-sm-12">
            <div class="panel panel-default panel-dark-grey">
                <div class="panel-heading">
                    <h4>{!! trans('messages.tasks') !!}</h4>
                </div>
                <div class="panel-body table-inner table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>{!! trans('messages.date_created') !!}</th>
                            <th>{!! trans('messages.title') !!}</th>
                            <th>{!! trans('messages.objective') !!}</th>
                            <th>{!! trans('messages.status') !!}</th>
                            <th>{!! trans('messages.award') !!}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if(count($taskObj) > 0)
                            @foreach($taskObj as $task)
                                <tr>
                                    <td>2 weeks ago</td>
                                    <td>Task number 1</td>
                                    <td>Title of Objective for this task</td>
                                    <td>Assigned to User 1</td>
                                    <td>$ 10</td>
                                </tr>
                            @endforeach
                        @else
                        <tr>
                            <td colspan="5">No record(s) found.</td>
                        </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-sm-12">
            <button class="btn orange-bg" type="button" id="add_task_btn"><span class="glyphicon glyphicon-plus"></span> {!! trans('messages.add_task') !!}</button>
            <button class="btn orange-bg" type="button" id="see_all_task_btn">{!! trans('messages.see_all_tasks') !!}</button>
        </div>
    </div>-->
    <div class="row form-group">
        <div class="col-sm-6 col-xs-12">
            @include('elements.site_activities')
        </div>
    </div>
</div>
@include('elements.footer')
@stop
@section('page-scripts')
@section('page-scripts')
<script>
    $(function(){
        $(".unit_description").css("min-height",($(".both-div").height())+10+'px');

    })
</script>
@endsection
