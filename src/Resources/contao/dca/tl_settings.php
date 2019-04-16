<?php

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    ';{files_legend:hide}',
    "{dump_legend},dump_api_key;{files_legend:hide}",
    $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dump_api_key'] = array (
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['dump_api_key'],
	'inputType'               => 'text',
    'eval'                    => array('tl_class' => 'w50')
);
