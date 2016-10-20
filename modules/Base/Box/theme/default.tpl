{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}

    {php}
        load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
        eval_js_once('document.body.id=null'); //pointer-events:none;
    {/php}
    <canvas class="Base_Help__tools" style="height:3000px;width:3000px;" id="help_canvas" width="3000px"
            height="3000px"></canvas>
    <img class="Base_Help__tools" style="display: none;" id="Base_Help__help_arrow"
         src="{$theme_dir}/Base/Help/arrow.png"/>
    <div class="Base_Help__tools comment" style="display: none;" id="Base_Help__help_comment">
        <div id="Base_Help__help_comment_contents"></div>
        <div class="button_next" id="Base_Help__button_next">{'Next'|t}</div>
        <div class="button_next" id="Base_Help__button_finish">{'Finish'|t}</div>
    </div>
    <header class="row">
        <div class="col-lg-2 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <div class="pull-left">{$menu}</div>
                    <button class="btn btn-default pull-right" {$home.href}>
                        <div id="home-bar1">
                                {$home.label}
                        </div>
                    </button>
                </div>
                <div class="panel-body">
                    {$logo}
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-xs-5 pull-right">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <div class="pull-right">{$indicator}</div>
                </div>
                <div class="panel-body">
                        <div class="search" id="search_box" style="margin-bottom: 8px;">{$search}</div>
                        <div class="filter" id="filter_box">{$filter}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-xs-3 pull-right">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {$help}
                </div>
                <div class="panel-body" id="launchpad_button_section">
                    {$launchpad}
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>
                </div>
                <div class="panel-body">
                    {$actionbar}
                </div>
            </div>
        </div>
    </header>
    <!-- -->
    <div id="content">
        <div id="content_body" style="top: 50px;">
            {$main}
        </div>
    </div>

    <footer>
        <div class="pull-left">
            <a href="http://epe.si" target="_blank"><b>EPESI</b> powered</a>
        </div>
        <div class="pull-right">
            <span style="float: right">{$version_no}</span>
            {if isset($donate)}
                <span style="float: right; margin-right: 30px">{$donate}</span>
            {/if}
        </div>
    </footer>

    {$status}

{/if}
