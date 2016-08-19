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
    
    return '<form action="" method="post">
	<p class="lead">Please select three <em>POTENTIAL</em> pickup dates.</p>
	<div class="alert alert-warning"><strong><em>Please note:</strong> <em>NONE</em> of the dates and times you select are confirmed until our schedulers are able to contact you directly.</em></div>
	<div class="row">
'.LR::sec($cx, ((is_array($in) && isset($in['pickupdays'])) ? $in['pickupdays'] : null), null, $in, true, function($cx, $in) {return '		<div class="col-md-4">
			<label for="yourname">Preferred Pickup Date '.htmlentities((string)(isset($cx['sp_vars']['index']) ? $cx['sp_vars']['index'] : null), ENT_QUOTES, 'UTF-8').'<span class="required">*</span>:</label><br />
			<input type="text" name="donor[pickupdate'.htmlentities((string)(isset($cx['sp_vars']['index']) ? $cx['sp_vars']['index'] : null), ENT_QUOTES, 'UTF-8').']" id="pickupdate'.htmlentities((string)(isset($cx['sp_vars']['index']) ? $cx['sp_vars']['index'] : null), ENT_QUOTES, 'UTF-8').'" class="date" gldp-id="gldatepicker'.htmlentities((string)(isset($cx['sp_vars']['index']) ? $cx['sp_vars']['index'] : null), ENT_QUOTES, 'UTF-8').'" value="'.htmlentities((string)((is_array($in) && isset($in['value'])) ? $in['value'] : null), ENT_QUOTES, 'UTF-8').'" />
			<div style="position: relative;">
				<div gldp-el="gldatepicker'.htmlentities((string)(isset($cx['sp_vars']['index']) ? $cx['sp_vars']['index'] : null), ENT_QUOTES, 'UTF-8').'" style="width: 400px; height: 300px;"></div>
			</div>
'.LR::sec($cx, ((is_array($in) && isset($in['times'])) ? $in['times'] : null), null, $in, true, function($cx, $in) {return '			<div class="radio">
				<label>
					<input type="radio" name="donor[pickuptime'.htmlentities((string)((isset($cx['sp_vars']['_parent']) && isset($cx['sp_vars']['_parent']['index'])) ? $cx['sp_vars']['_parent']['index'] : null), ENT_QUOTES, 'UTF-8').']" id="pickuptimes'.htmlentities((string)((is_array($in) && isset($in['key'])) ? $in['key'] : null), ENT_QUOTES, 'UTF-8').'" value="'.htmlentities((string)((is_array($in) && isset($in['time'])) ? $in['time'] : null), ENT_QUOTES, 'UTF-8').'"'.htmlentities((string)((is_array($in) && isset($in['checked'])) ? $in['checked'] : null), ENT_QUOTES, 'UTF-8').'>
					'.htmlentities((string)((is_array($in) && isset($in['time'])) ? $in['time'] : null), ENT_QUOTES, 'UTF-8').'
				</label>
			</div>
';}).'		</div>
';}).'	</div>
	<br />
	'.((is_array($in) && isset($in['priority_pickup_option'])) ? $in['priority_pickup_option'] : null).'
	<div class="row">
		<div class="col-md-12">
			<p><strong>Location of items:</strong></p>
'.LR::sec($cx, ((is_array($in) && isset($in['pickuplocations'])) ? $in['pickuplocations'] : null), null, $in, true, function($cx, $in) {return '			<div class="radio">
				<label>
					<input type="radio" name="donor[pickuplocation]" id="pickuplocation'.htmlentities((string)((is_array($in) && isset($in['key'])) ? $in['key'] : null), ENT_QUOTES, 'UTF-8').'" value="'.htmlentities((string)((is_array($in) && isset($in['location_attr_esc'])) ? $in['location_attr_esc'] : null), ENT_QUOTES, 'UTF-8').'"'.htmlentities((string)((is_array($in) && isset($in['checked'])) ? $in['checked'] : null), ENT_QUOTES, 'UTF-8').'>
					'.htmlentities((string)((is_array($in) && isset($in['location'])) ? $in['location'] : null), ENT_QUOTES, 'UTF-8').'
				</label>
			</div>
';}).'		</div>
	</div>
	<br />
	<div class="row">
		<div class="col-md-12"><p class="text-right"><button type="submit" class="btn btn-primary">Finish and Submit</button></p></div>
	</div>
	<input type="hidden" name="nextpage" value="'.htmlentities((string)((is_array($in) && isset($in['nextpage'])) ? $in['nextpage'] : null), ENT_QUOTES, 'UTF-8').'" />
</form>';
};
?>