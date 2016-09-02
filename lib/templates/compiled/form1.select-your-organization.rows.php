<?php
use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array();
    $partials = array();
    $cx = array(
        'flags' => array(
            'jstrue' => false,
            'jsobj' => false,
            'spvar' => true,
            'prop' => false,
            'method' => false,
            'lambda' => false,
            'mustlok' => false,
            'mustlam' => false,
            'echo' => false,
            'partnc' => false,
            'knohlp' => false,
            'debug' => isset($options['debug']) ? $options['debug'] : 1,
        ),
        'constants' => array(),
        'helpers' => isset($options['helpers']) ? array_merge($helpers, $options['helpers']) : $helpers,
        'partials' => isset($options['partials']) ? array_merge($partials, $options['partials']) : $partials,
        'scopes' => array(),
        'sp_vars' => isset($options['data']) ? array_merge(array('root' => $in), $options['data']) : array('root' => $in),
        'blparam' => array(),
        'partialid' => 0,
        'runtime' => '\LightnCandy\Runtime',
    );
    
    return ''.LR::sec($cx, ((is_array($in) && isset($in['rows'])) ? $in['rows'] : null), null, $in, true, function($cx, $in) {return '	<div class="row organization'.htmlentities((string)((is_array($in) && isset($in['css_classes'])) ? $in['css_classes'] : null), ENT_QUOTES, 'UTF-8').'">
		<div class="col-lg-12">
			<h3>'.htmlentities((string)((is_array($in) && isset($in['name'])) ? $in['name'] : null), ENT_QUOTES, 'UTF-8').'</h3>
			'.((is_array($in) && isset($in['desc'])) ? $in['desc'] : null).'
			<a class="btn btn-primary btn-lg" href="'.htmlentities((string)((is_array($in) && isset($in['link'])) ? $in['link'] : null), ENT_QUOTES, 'UTF-8').'">'.htmlentities((string)((is_array($in) && isset($in['button_text'])) ? $in['button_text'] : null), ENT_QUOTES, 'UTF-8').'</a>
		</div>
	</div>
	<hr />
';}).'';
};
?>