<div style="text-align:left; width: 950px;">
{php}
eval_js_once('show_filters = 0');
eval_js('var b=document.getElementById(\'recordbrowser_filters\');if(b){if(!show_filters){b.style.display=\'none\';document.getElementById(\'hide_filter_b\').style.display=\'none\';}else{document.getElementById(\'show_filter_b\').style.display=\'none\';}}');
{/php}
<input type="button" onClick="document.getElementById('recordbrowser_filters').style.display='block';this.style.display='none';document.getElementById('hide_filter_b').style.display='block';show_filters=1;" id="show_filter_b" value="Show filters">
<input type="button" onClick="document.getElementById('recordbrowser_filters').style.display='none';this.style.display='none';document.getElementById('show_filter_b').style.display='block';show_filters=0;" id="hide_filter_b" value="Hide filters">
</div><br>

{$form_open}
<div id="recordbrowser_filters">
{foreach item=f from=$filters}
	{$form_data.$f.label}&nbsp;{$form_data.$f.html}
{/foreach}
<br>
{$form_data.submit.html}
</div>
{$form_close}