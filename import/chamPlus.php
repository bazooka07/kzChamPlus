<?php
if(!defined('PLX_ROOT')) { exit; }

$parser = xml_parser_create(PLX_CHARSET);
xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);

if(xml_parse_into_struct($parser, file_get_contents($plxPlugin->getConfigPath(__FILE__)), $values, $tags) == 1) {
	if(!empty($tags['parameter'])) {
		// Import parametres plugin
		$aParams = array();
		foreach($tags['parameter'] as $i) {
			$node = $values[$i];
			if($node['tag'] == 'parameter') {
				if(array_key_exists('attributes', $node)) {
					$name = $node['attributes']['name'];
					$type = $node['attributes']['type'];
					$value = (array_key_exists('value', $node)) ? trim($node['value']) : false;
					if(!empty($value)) {
						$aParams[$name] = array(
							'type'	=> $type,
							'value'	=> $value
						);
					}
				}
			}
		}

		// traitement
		$newTypes = array(
			0 => kzChamPlus::LIGNE, // ex. sidebar,
			1 => kzChamPlus::BLOCK_TEXT, // ex. bloc-texte
			2 => kzChamPlus::MEDIA, // ex. media
			3 => kzChamPlus::LIGNE  // ex. ligne
		);

		$names = array_filter(array_keys($aParams), function($item) {
			return preg_match('#^name\d+#', $item);
		});
		foreach($names as $name) {
			$i = preg_replace('#^name#', '', $name);
			foreach(array('name', 'label', 'group') as $k) { // $plxPlugin->$paramsNames
				$param = $k . $i;
				// $param is unchanged
				if(array_key_exists($param, $aParams)) {
					$plxPlugin->setParam($param, $aParams[$param]['value'], 'string');
				}
			}

			// entry and place
			$value = kzChamPlus::LIGNE;
			$place = kzChamPlus::BOTTOM_ART;


			$oldParam = 'textarea' . $i;
			if(array_key_exists($oldParam, $aParams)) {
				$oldCode = intval($aParams[$oldParam]);
				if(in_array($oldCode, $newTypes)) {
					$value = $newTypes[$oldCode];
					$static = 'static' . $i;
					if(!empty($aParams[$static])) {
						$place = kzChamPlus::BOTTOM_STATIC;
					} elseif($oldCode == 0) {
						$place = kzChamPlus::SIDEBAR_ART;
					}
				}
			}
			// integers
			$plxPlugin->setParam('entry' . $i, $value, 'numeric');
			$plxPlugin->setParam('place' . $i, $place, 'numeric');
		}

		if(!empty($aParams['no_integration'])) {
			$plxPlugin->setParam('no_integration', 1, 'numeric');
		}

		$plxPlugin->saveParams();
	}
}
xml_parser_free($parser);
?>
