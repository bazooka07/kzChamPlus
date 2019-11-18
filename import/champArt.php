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

		$oldPlaces = array(
		'bot' =>	self::BOTTOM_ART,
		'side' =>	self::SIDEBAR_ART,
		'foot' =>	self::BOTTOM_ART // ?????
		);

		$place = (array_key_exists('pos_vie', $aParams)) ? $oldPlaces[$aParams['pos_vie']] : kzChamPlus::BOTTOM_ART;

		$newTypes = array(
			'ligne'	=> kzChamPlus::LIGNE,
			'bloc'	=> kzChamPlus::BLOCK_TEXT
		);

		$names = array_filter(array_keys($aParams), function($item) {
			return preg_match('#^champ\d+#', $item);
		});
		foreach($names as $name) {
			$i = preg_replace('#^champ#', '', $name);


			foreach(
				array(
					'name'			=> 'champ',
					'label'			=> 'label',
					'group'			=> 'groupe',
					'placeholder'	=> 'phold'
				) as $k => $old
			) { // $plxPlugin->$paramsNames

				$param = $k . $i;
				$oldParam = $old . $i;
				// $param is unchanged
				if(array_key_exists($oldParam, $aParams)) {
					$plxPlugin->setParam($param, $aParams[$oldParam]['value'], 'string');
				}
			}

			// entry and place
			$value = kzChamPlus::LIGNE;

			$oldParam = 'textarea' . $i;
			if(array_key_exists($oldParam)) {
				$oldCode = intval($aParams[$oldParam]);
				if(in_array($oldCode, $newTypes)) {
					$value = $newTypes[$oldCode];
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

$n = 0;
$errors = 0;
$ignores = 0;
foreach(glob('/*.xml') as $filename) {
	if(is_writable($filename)) {
		if(preg_match('#.*/\d{4}\.(?:draft,|home,)?\d{3}(?:(?:,\d{3})*\.\d{3}\.\d{12}\..*\.xml$#', $filename)) {
			file_put_contents(
				$filename,
				preg_replace(
					'<champArt_',
					'<' . kzChamPlus::PREFIX,
					file_get_contents($filename)
				);
			);
		} else {
			$ignores++;
		}
	} else {
		$errors++;
	}
	$n++;
}
if(!empty($errors)) {
	$msg = $n . ' articles modfiés';
	if(!empty($ignores)) {
		$msg .= " $ignores articles ignorés";
	}
	plxMsg::Info($msg);
} else {
	plxMsg::Error($errors . ' erreurs sur ' . $count . ' articles');
}
?>
