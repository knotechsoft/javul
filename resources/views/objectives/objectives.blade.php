@extends('layout.default')
@section('content')
<div class="container">
    <div class="row form-group" style="margin-bottom: 15px;">
        @include('elements.user-menu',['page'=>'objectives'])
    </div>
    <div class="row form-group">
        <div class="col-sm-4">
            @include('elements.site_activities')
        </div>
        <div class="col-sm-8">
            <div class="panel panel-grey panel-default">
                <div class="panel-heading">
                    <h4>{!! trans('messages.objectives') !!}</h4>
                </div>
                <div class="panel-body table-inner table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Objective Name</th>
                            <th>Unit Name</th>
                            <th>Created By</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @if(count($objectives) > 0 )
                        @foreach($objectives as $objective)
                        <?php $unitslug =\App\Unit::getSlug($objective->unit_id); ?>
                        <tr>
                            <td><a href="{!! url('objectives/'.$objectiveIDHashID->encode($objective->id).'/'.$objective->slug)!!}">{{$objective->name}}</a></td>
                            <td><a href="{!! url('units/'.$unitIDHashID->encode($objective->unit_id).'/'.$unitslug )!!}">{{$objective->unit_name}}</a></td>
                            <td><a href="{!! url('userprofiles/'.$userIDHashID->encode($objective->user_id).'/'.strtolower
                            ($objective->first_name.'_'.$objective->last_name))!!}">
                                    {{$objective->first_name.' '.$objective->last_name}}
                                </a></td>
                            <td>{{$objective->status}}</td>
                            <td width="11%">
                                @if(\Auth::check())
                                <a class="btn btn-xs btn-primary"
                                   href="{!! url('objectives/'.$objectiveIDHashID->encode($objective->id).'/edit') !!}" title="edit">
                                    <span class="glyphicon glyphicon-edit"></span>
                                </a>
                                @endif
                                <?php $unitAdminID = \App\Task::checkUnitAdmin($objective->unit_id); ?>

                                @if(!empty($authUserObj) && ($authUserObj->role == "superadmin" || $unitAdminID ==
                                $authUserObj->id))
                                <a title="delete" href="#" class="btn btn-xs btn-danger delete-objective"
                                   data-id="{{$objectiveIDHashID->encode($objective->id)}}">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @else
                        <tr>
                            <td colspan="5">No record(s) found.</td>
                        </tr>
                        @endif
                        <tr style="background-color: #fff;text-align: right;">
                            <td colspan="5" >
                                <a href="{!! url('objectives/add')!!}"class="btn black-btn form-group" id="add_objective_btn" type="button">
                                    <span class="glyphicon glyphicon-plus"></span> {!! trans('messages.add_objective') !!}
                                </a>

                                <a href="{!! url('units/add')!!}"class="btn more-black-btn form-group" id="add_unit_btn"
                                   type="button">
                                    <span class="more_dots">...</span> MORE OBJECTIVES
                                </a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
@include('elements.footer')
@stop
@section('page-scripts')
<script type="text/javascript">
    var msg_flag ='{{ $msg_flag }}';
    var msg_type ='{{ $msg_type }}';
    var msg_val ='{{ $msg_val }}';
</script>
<script src="{!! url('assets/js/custom_tostr.js') !!}" type="text/javascript"></script>
<script src="{!! url('assets/js/objectives/delete_objective.js') !!}"></script>
@endsection