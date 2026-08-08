<?php

/*
 *  Instance Configuration
 *  ----------------------
 *  Edit this file and not config.php for imageboard configuration.
 *
 *  You can copy values from config.php (defaults) and paste them here.
 */



	$config['stylesheets']['Burichan'] = 'burichan.css';
	$config['stylesheets']['Caffe'] = 'caffe.css';
	$config['stylesheets']['Futaba'] = 'futaba.css';
	$config['stylesheets']['Confraria'] = 'confraria.css';
	$config['stylesheets']['Dark'] = 'dark.css';
	$config['stylesheets']['Dark Roach'] = 'dark_roach.css';
	$config['stylesheets']['Favela'] = 'favela.css';
	
	$config['stylesheets_board'] = true;

	$config['additional_javascript'][] = 'js/style-select.js';

	$config['additional_javascript'][] = 'js/options.js';
	$config['additional_javascript'][] = 'js/options/general.js';
	$config['additional_javascript'][] = 'js/hide-form.js';

	@include('inc/secrets.php');
?>
