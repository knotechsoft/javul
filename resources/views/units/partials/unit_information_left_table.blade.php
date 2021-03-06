<div class="panel panel-grey panel-default">
    <div class="panel-heading">
        <h4>UNIT INFORMATION</h4>
    </div>
    <div class="panel-body unit-info-panel list-group">
        <div class="list-group-item">
            <div class="row">
                <div class="col-xs-12">
                    <label class="control-label upper">UNIT NAME</label>
                    <label class="control-label colorLightGreen form-control label-value">
                        <a href="{!! url('units/'.$unitIDHashID->encode($unitObj->id).'/'.$unitObj->slug) !!}" class="colorLightGreen" >{{$unitObj->name}}</a>
                    </label>
                </div>
            </div>
        </div>
        <div class="list-group-item">
            <div class="row">
                <div class="col-xs-4 unit-info-main-div">
                    <label class="control-label upper">UNIT LINKS</label>
                </div>
                <div class="col-xs-8" style="padding-top: 7px;">
                    <div class="row unit_info_row_1">
                        <div class="col-xs-12">
                            <ul class="unit_info_link_1" style="">
                                <li><a href="{!! url('objectives/'.$unitIDHashID->encode($unitObj->id).'/lists') !!}" class="colorLightBlue upper">OBJECTIVES</a></li>
                                <li class="mrgrtlt5">|</li>
                                <li><a href="{!! url('tasks/'.$unitIDHashID->encode($unitObj->id).'/lists') !!}" class="colorLightBlue upper">TASKS</a></li>
                                <li class="mrgrtlt5">|</li>
                                <li><a href="{!! url('issues/'.$unitIDHashID->encode($unitObj->id).'/lists') !!}" class="colorLightBlue upper">ISSUES</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12">
                            <ul class="unit_info_link_2">
                                <i class="fa fa-quote-right colorLightBlue"></i>
                                <li><a href="#" class="colorLightBlue upper">FORUM</a></li>
                                <i class="fa fa-comments colorLightBlue"></i>
                                <li><a href="#" class="colorLightBlue upper">CHAT</a></li>
                                <i class="fa fa-wikipedia-w colorLightBlue"></i>
                                <li><a href="#" class="colorLightBlue upper">WIKI</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="list-group-item">
            <div class="row">
                <div class="col-xs-4 borderRT paddingTB7">
                    <label class="control-label upper">OTHER LINKS</label>
                </div>
                <div class="col-xs-8 paddingTB7">
                    <label class="control-label colorLightBlue">LINK1, LINK2</label>
                </div>
            </div>
        </div>
        <div class="list-group-item">
            <div class="row">
                <div class="col-xs-4 borderRT paddingTB7">
                    <label class="control-label upper">UNIT CATEGORIES</label>
                </div>
                <div class="col-xs-8 paddingTB7">
                    <?php $category_names = \App\UnitCategory::getName($unitObj->category_id);
                    $category_ids = explode(",",$unitObj->category_id);
                    $category_names  = explode(",",$category_names ); ?>
                        @if(count($category_ids) > 0 )
                            @foreach($category_ids as $index=>$category)
                                <a class="upper colorLightBlue" href="{!! url('category/'.$unitCategoryIDHashID->encode($category))
                                !!}">{{$category_names[$index]}}</a>
                                @if(count($category_ids) > 1 && $index != count($category_ids) -1)
                                    <span>&#44;</span>
                                @endif
                            @endforeach
                        @endif
                </div>
            </div>
        </div>
        <div class="list-group-item">
            <div class="row">
                <div class="col-xs-4 borderRT paddingTB7">
                    <label class="control-label upper">UNIT LOCATION</label>
                </div>
                <div class="col-xs-8 paddingTB7">
                    <label class="control-label colorLightBlue upper">{{\App\City::getName($unitObj->city_id)}}</label>
                </div>
            </div>
        </div>
        <div class="list-group-item">
            <div class="row">
                <div class="col-xs-4 borderRT paddingTB7">
                    <label class="control-label upper" style="width: 100%;">
                        <span class="fund_icon">FUND</span>
                        <span class="text-right pull-right"> <div class="fund_paid"><i class="fa fa-plus"></i></div></span>
                    </label>
                </div>
                <div class="col-xs-8 paddingTB7">
                    <div class="row">
                        <div class="col-xs-6">Available</div>
                        <div class="col-xs-6 text-right">{{number_format($availableFunds,2)}} $</div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">Awarded</div>
                        <div class="col-xs-6 text-right">{{number_format($awardedFunds,2)}} $</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>