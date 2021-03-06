@extends('layout.default')
@section('content')
    <div class="container">
        <div class="row form-group" style="margin-bottom:15px">
            @include('elements.user-menu',array('page'=>'home'))
        </div>
        <div class="row">
            <div class="col-md-4 left">
                <div class="site_activity_list">
                    <div class="site_activity_loading loading_dots" style="position: absolute;top:20%;left:43%;z-index: 9999;display: none;">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    @include('elements.site_activities',['ajax'=>false])
                </div>
            </div>
            <div class="col-md-8 right">
                <div class="row form-group">
                    <div class="col-sm-12">
                        <div class="panel panel-grey panel-default">
                            <div class="panel-heading featured_unit_heading">
                                <div class="featured_unit">
                                    <i class="fa fa-star"></i>
                                </div>
                                <h4>FEATURED UNIT</h4>
                                @if(!empty($authUserObj))
                                    <div class="pull-right" style="padding:10px;">
                                        <a href="{!! url('my_tasks') !!}" class="btn btn-xs pull-right">
                                            <span class="glyphicon glyphicon-tasks"></span> My Tasks
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-sm-8 featured_heading">
                                        <h4 class="colorLightGreen">Information Technology</h4>
                                    </div>
                                    <div class="col-sm-4 featured_heading text-right colorLightBlue">
                                        <i class="fa fa-home"></i> UNIT HOME PAGE
                                    </div>
                                </div>
                                <hr style="margin-top: 0px;">
                                <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum</p>
                            </div>
                        </div>
                        @if(count($recentUnits) > 5)
                        <!--<a class="btn orange-bg" href="{!! url('') !!}">{!! Lang::get('messages.all_units') !!}</a>-->
                        @endif
                        <!--<a class="btn orange-bg" href="{!! url('units/create') !!}">{!! Lang::get('messages.create_units') !!}</a>-->
                    </div>
                </div>
                <div class="row form-group">
                    <div class="col-sm-12">
                        <div class="panel panel-grey panel-default">
                            <div class="panel-heading">
                                <h4>MOST ACTIVE UNITS</h4>
                            </div>
                            <div class="panel-body list-group">
                                <table class="table table-striped">
                                    <thead>
                                        <th>Name</th>
                                        <th>Categories</th>
                                        <th>Location</th>
                                    </thead>
                                    <tbody>
                                    @if(count($recentUnits) > 0)
                                        @foreach($recentUnits as $unit)
                                            <?php $categories = \App\Unit::getCategoryNames($unit->category_id); ?>
                                            <tr>
                                                <td width="70%">
                                                    <a href="{!! url('units/'.$unitIDHashID->encode($unit->id).'/'.$unit->slug) !!}"
                                                       class="colorLightBlue" >
                                                        {{$unit->name}}
                                                    </a>
                                                </td>
                                                <td width="15%">
                                                    <a href="#">{{$categories}}</a>
                                                </td>
                                                <td>
                                                    @if(empty($unit->city_id) && $unit->country_id == 247)
                                                    GLOBAL
                                                    @else
                                                    {{\App\City::getName($unit->city_id)}}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="3">No unit found.</td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if(count($recentUnits) > 5)
                            <!--<a class="btn orange-bg" href="{!! url('') !!}">{!! Lang::get('messages.all_units') !!}</a>-->
                        @endif
                        <!--<a class="btn orange-bg" href="{!! url('units/create') !!}">{!! Lang::get('messages.create_units') !!}</a>-->
                    </div>
                </div>
                <div class="row form-group">
                    <div class="col-sm-12">
                        <div class="panel panel-grey panel-default">
                            <div class="panel-heading">
                                <h4>RECENTLY CREATED UNITS</h4>
                            </div>
                            <div class="panel-body list-group">
                                <table class="table table-striped">
                                    <thead>
                                    <th>Name</th>
                                    <th>Categories</th>
                                    <th>Location</th>
                                    </thead>
                                    <tbody>
                                    @if(count($recentUnits) > 0)
                                    @foreach($recentUnits as $unit)
                                    <?php $categories = \App\Unit::getCategoryNames($unit->category_id); ?>
                                    <tr>
                                        <td width="70%">
                                            <a href="{!! url('units/'.$unitIDHashID->encode($unit->id).'/'.$unit->slug) !!}"
                                               class="colorLightBlue" >
                                                {{$unit->name}}
                                            </a>
                                        </td>
                                        <td width="15%">
                                            <a href="#">{{$categories}}</a>
                                        </td>
                                        <td>
                                            @if(empty($unit->city_id) && $unit->country_id == 247)
                                                GLOBAL
                                            @else
                                                {{\App\City::getName($unit->city_id)}}
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    @else
                                    <tr>
                                        <td colspan="3">No unit found.</td>
                                    </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if(count($recentUnits) > 5)
                        <!--<a class="btn orange-bg" href="{!! url('') !!}">{!! Lang::get('messages.all_units') !!}</a>-->
                        @endif
                        <!--<a class="btn orange-bg" href="{!! url('units/create') !!}">{!! Lang::get('messages.create_units') !!}</a>-->
                    </div>
                </div>
            </div>

        </div>
    </div>
    @include('elements.footer')
@endsection
@section('page-scripts')
<script type="text/javascript">
    window.onresize = function(event) {
        var $iW = $(window).innerWidth();
        if ($iW < 992){
            $('.right').insertBefore('.left');
        }else{
            $('.right').insertAfter('.left');
        }
    }
    $(function(){
        var $iW = $(window).innerWidth();
        if ($iW < 992){
            $('.right').insertBefore('.left');
        }else{
            $('.right').insertAfter('.left');
        }
    })
</script>
@endsection