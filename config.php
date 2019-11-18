<?php
if(!defined('PLX_ROOT')) { exit; }

# Control du token du formulaire
plxToken::validateFormToken($_POST);

if(filter_has_var(INPUT_POST, 'import')) {
	$other = filter_input(INPUT_POST, 'import', FILTER_SANITIZE_STRING) {

	}
}

if(filter_has_var(INPUT_POST, 'name')) {

	$reqs = array();
	foreach($plxPlugin->paramsNames as $name=>$filter) {
		$reqs[$name] = filter_input(INPUT_POST, $name, $filter, FILTER_REQUIRE_ARRAY);
		if($name == 'name' and empty($reqs['name']) and !is_array($reqs['name'])) {
			header('Location: parametres_plugin.php?p=' . $plugin);
			exit;
		}
	}

	$names = array_map('trim' , $reqs['name']);
	foreach($names as $indice=>$name) {
		if(!empty($name)) {
			$plxPlugin->setParam('name' . $indice, preg_replace('#[\s-]+#', '_', $name), 'string');
			foreach(array_keys($plxPlugin->paramsNames) as $n) {
				if($n == 'name') { continue; }
				$param = $n . $indice;
				if(!empty($reqs[$n][$indice])) {
					if($plxPlugin->isNumeric($n)) {
						$value = intval(trim($reqs[$n][$indice]));
						$format = 'numeric';
					} else {
						$value = trim($reqs[$n][$indice]);
						$format = 'string';
					}
					$plxPlugin->setParam($param, $value, $format);
				} elseif($n == 'label') {
					$plxPlugin->setParam($param, ucfirst($names[$indice]), 'string');
				} else {
					$plxPlugin->delParam($param);
				}
			}
		} else {
			foreach(array_keys($plxPlugin->paramsNames) as $n) {
				$plxPlugin->delParam($n . $indice);
			}
		}
	}

	// input[type="checkbox"]
	foreach($plxPlugin->options as $k) {
		if(filter_input(INPUT_POST, $k, FILTER_VALIDATE_BOOLEAN) === true) {
			$plxPlugin->setParam($k, '1', 'numeric');
		} else {
			$plxPlugin->delParam($k);
		}
	}

	$plxPlugin->saveParams();
	header('Location: parametres_plugin.php?p=' . $plugin);
	exit;
}

?>
	<form id="<?php echo $plugin; ?>ConfigForm" method="post"> <!--  action="/variables.php" -->
		<?php echo plxToken::getTokenPostMethod() ?>
		<div class="scrollable-table"><table class="full-width" data-rows-num='name^="order"'>
			<thead>
				<tr>
<?php
$notes = array('name', 'place');
$selects = array('entry', 'place');
foreach (array_keys($plxPlugin->paramsNames) as $name) {
	$xtra = array_search($name, $notes);
	$className = (in_array($name, $selects)) ? ' class="select1"' : '';
?>
				<th<?php echo $className; ?>><?php $plxPlugin->lang(strtoupper('L_TITLE_'.$name)); if(is_integer($xtra)) echo '<sup>' . ($xtra + 1). '</sup>'?></th>
<?php
}
?>
				</tr>
			</thead>
			<tbody id="<?php echo $plugin; ?>Table"	data-indice="<?php echo $plxPlugin->newIndice(); ?>">
<?php
	if(!empty($plxPlugin->indexFields)) {
		foreach(array_keys($plxPlugin->indexFields) as $i) {
			$plxPlugin->printFieldConfig($i);
		}
	} else {
		$plxPlugin->printFieldConfig(-1);
	}
?>
			</tbody>
		</table></div>

		<div>
<?php
		foreach($plxPlugin->options as $k) {
			$checked = ($plxPlugin->getParam($k) > 0) ? ' checked' : '';
?>
			<p>
				<input type="checkbox" value="1" name="<?php echo $k; ?>" id="id_<?php echo $k; ?>"<?php echo $checked; ?> />
				<label for="id_<?php echo $k; ?>"><?php $plxPlugin->lang(strtoupper('L_'.$k)); ?></label>
			</p>
<?php	} ?>
		</div>
		<div class="in-action-bar">
<?php
if(!empty($plxPlugin->helpFile)) {
?>
			<input type="button" id="helpBtn" value="<?php $plxPlugin->lang('L_HELP_LABEL') ?>" />
<?php
}
?>
			<input type="button" id="newFieldBtn" value="<?php $plxPlugin->lang('L_ADD') ?>" />
			<input type="submit" value="<?php $plxPlugin->lang('L_SAVE') ?>" />
		</div>
	</form>
	<p><?php $plxPlugin->lang('L_WARNING'); ?></p>
<?php
if(!empty($plxPlugin->helpFile)) {
?>
	<div id="<?php echo $plugin; ?>HelpView">
		<?php readfile($plxPlugin->helpFile); ?>
	</div>
<?php
}

$otherConfigs = $plxPlugin->importConfigList();
if(!empty($otherConfigs)) {
?>
	<div id="<?php echo $plugin; ?>-config">
		<p>Importer une config</p>
		<form method="post">
			<?php echo plxToken::getTokenPostMethod() ?>
			<ul>
<?php
	foreach($otherConfigs as $config) {
		$id = 'id_' . $config;
?>
				<li>
					<input type="radio" id="<?php echo $id; ?>" name="import" value="<?php echo $config; ?>">
					<label for="<?php echo $id; ?>"><?php echo $config; ?></label>
				</li>
<?php
	}
?>
			</ul>
			<div>
				<input type="submit" />
			</div>
		</form>
	</div>
<?php
}
?>
