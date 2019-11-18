<?php
if(!defined('PLX_ROOT')) { exit; }

$parser = xml_parser_create(PLX_CHARSET);
xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);

if(xml_parse_into_struct($parser, file_get_contents($plxPlugin->getConfigPath(__FILE__)), $values, $tags) == 1) {
	if(!empty($tags['parameter'])) {
		$n = 1;
		foreach($tags['parameter'] as $i) {
			$node = $values[$i];
			if($node['tag'] == 'parameter') {
				$name = $node['attributes']['name'];
				$type = $node['attributes']['type'];
				$value = trim($node['value']);
				if(!empty($value)) {
					$plxPlugin->setParam($name, $value, $type);
				}
			}
		}
		$plxPlugin->saveParams();
	}
}
xml_parser_free($parser);

/*
  [101] =>
  array(5) {
    'tag' =>
    string(9) "parameter"
    'type' =>
    string(8) "complete"
    'level' =>
    int(2)
    'attributes' =>
    array(2) {
      'name' =>
      string(7) "place10"
      'type' =>
      string(7) "numeric"
    }
    'value' =>
    string(1) "9"
  }
 * */
?>
