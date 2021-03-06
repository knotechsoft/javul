<nav class="navbar navbar-grey navbar-orange">
    <div class="container">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{!! url('') !!}" style="margin-top:5px;color:#3f3f3f; ">
                <img class="logo" src="{!! url('assets/images/logo.png') !!}" />JAVUL
            </a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->

        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <li class="active"><a href="{!! url('') !!}" class="header_nav_menus home"><span class="glyphicon
                glyphicon-home"></span></a></li>
                <li><a href="#" class="header_nav_menus">{!! trans('messages.about_this_site') !!}</a></li>
                <li><a href="#" class="header_nav_menus">{!! trans('messages.faq') !!}</a></li>
                <li><a href="#" class="header_nav_menus">{!! trans('messages.how_can_i_help') !!}</a></li>
                <li><a href="#" class="header_nav_menus">{!! trans('messages.joul.org_unit') !!}</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right search-navbar">
                @if (empty($authUserObj))
                <!--<li><a href="{!! url('register') !!}">
                        <button type="button" class="btn btn-default orange-bg usermenu-btns">{!! trans('messages.signup')!!}</button>
                    </a></li>
                <li><a href="{!! url('login') !!}">
                        <button type="button" class="btn btn-default orange-bg usermenu-btns">{!! trans('messages.sign_in') !!}</button>
                    </a>
                </li>-->
                @else
                <!--<li>
                    <a class="header_nav_menus" href="{!! url('account') !!}">
                        {!! trans('messages.welcome') !!} : {{$authUserObj->first_name.' '.$authUserObj->last_name}}
                    </a>
                </li>
                <li><a href="{!! url('account/logout') !!}">
                        <button type="button" class="btn btn-default orange-bg usermenu-btns">{!! trans('messages.sign_out') !!}</button>
                    </a>
                </li>-->
                @endif


                <li class="search_div_main" style="padding-top: 15px;">
                    <div class="input-group add-on">
                        <input type="text" class="form-control" id="search_box" name="search_box" placeholder="{!! trans('messages.search_for') !!}"
                               aria-describedby="basic-addon1">
                        <div class="input-group-btn">
                            <button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i></button>
                        </div>
                    </div>
                </li>
            </ul>

        </div><!-- /.navbar-collapse -->
    </div><!-- /.container-fluid -->
</nav>