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
			'top' =>	kzChamPlus::TOP_ART,
			'side' =>	kzChamPlus::SIDEBAR_ART,
			'bot' =>	kzChamPlus::BOTTOM_ART,
			'foot' =>	kzChamPlus::BOTTOM_ART // ?????
		);

		$place = kzChamPlus::BOTTOM_ART;
		if(array_key_exists('pos_vie', $aParams)) {
			$pos_vie = $aParams['pos_vie']['value'];
			if(array_key_exists($pos_vie, $oldPlaces)) {
				$place = $oldPlaces[$pos_vie];
			}
		}

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
					'name'		=> 'champ',
					'label'		=> 'label',
					'group'		=> 'groupe',
					'invite'	=> 'phold',
					'entry'		=> 'type'
				) as $k => $old
			) { // $plxPlugin->$paramsNames

				$param = $k . $i;
				$oldParam = $old . $i;
				// $param is unchanged
				if(array_key_exists($oldParam, $aParams)) {
					$value = $aParams[$oldParam]['value'];

					if(empty($value)) { continue; }

					$type = 'string';
					if($k == 'entry') {
						$value = (array_key_exists($value, $newTypes)) ? $newTypes[$value] : kzChamPlus::LIGNE;
						$type = 'numeric';
					} elseif($k != 'name') {
						$value = htmlspecialchars_decode($value, ENT_QUOTES);
						$type = 'cdata';
						if($k == 'label') {
							$value .= '#'; // Φ © ® ™
						}
					}
					$plxPlugin->setParam($param, $value, $type);
				}
			}

			// integers
			$plxPlugin->setParam('place' . $i, $place, 'numeric');
		}

		$plxPlugin->saveParams();
	}
}
xml_parser_free($parser);

$n = 0;
$errors = 0;
$pattern = '#<' . kzChamPlus::PREFIX_IMPORT1 . '([\w-]+)>(.*?)</' . kzChamPlus::PREFIX_IMPORT1 . '#';
foreach($plxAdmin->plxGlob_arts->aFiles as $article) {
	$filename = PLX_ROOT . $plxAdmin->aConf['racine_articles'] . $article;
	if(is_writable($filename)) {
		file_put_contents(
			$filename,
			preg_replace(
				$pattern,
				"$0$1>\n\t<" . kzChamPlus::PREFIX . "$1>$2</" . kzChamPlus::PREFIX,
				file_get_contents($filename)
			)
		);
	} else {
		$errors++;
	}
	$n++;
}
if($errors == 0) {
	plxMsg::Info($n . ' ' . L_ARTICLE_MODIFY_SUCCESSFUL);
} else {
	plxMsg::Error(sprintf($plxPlugin->getLang('L_IMPORT_ARTCILES'), $errors, $n));
}
?>
