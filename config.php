<?php
if(!defined('PLX_ROOT')) { exit; }

if(filter_has_var(INPUT_POST, 'name')) {
	# Control du token du formulaire
	plxToken::validateFormToken($_POST);

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
		<fieldset>
		<table class="full-width" data-rows-num='name^="order"'>
			<thead>
				<tr>
<?php	foreach (array_keys($plxPlugin->paramsNames) as $name) { ?>
				<th><?php $plxPlugin->lang(strtoupper('L_CHAMPLUS_TITLE_'.$name)); ?></th>
<?php	} ?>
				</tr>
			</thead>
			<tbody id="<?php echo $plugin; ?>Table"	data-indice="<?php echo $plxPlugin->newIndice(); ?>">
<?php
	foreach ($plxPlugin->indices(true) as $i) {
		$plxPlugin->printFieldConfig($i);
	}
?>
			</tbody>
		</table>
		<div>
<?php
		foreach($plxPlugin->options as $k) {
			$checked = ($plxPlugin->getParam($k) > 0) ? ' checked' : '';
?>
			<p>
				<input type="checkbox" value="1" name="<?php echo $k; ?>" id="id_<?php echo $k; ?>"<?php echo $checked; ?> />
				<label for="id_<?php echo $k; ?>"><?php $plxPlugin->lang(strtoupper('L_CHAMPLUS_'.$k)); ?></label>
			</p>
<?php	} ?>
		</div>
		</fieldset>
		<div class="in-action-bar">
<?php
if(!empty($plxPlugin->helpFile)) {
?>
			<input type="button" id="helpBtn" value="<?php $plxPlugin->lang('L_CHAMPLUS_HELP_LABEL') ?>" />
<?php
}
?>
			<input type="button" id="newFieldBtn" value="<?php $plxPlugin->lang('L_CHAMPLUS_ADD') ?>" />
			<input type="submit" value="<?php $plxPlugin->lang('L_CHAMPLUS_SAVE') ?>" />
		</div>
	</form>
	<p><?php $plxPlugin->lang('L_CHAMPLUS_WARNING'); ?></p>
<?php
if(!empty($plxPlugin->helpFile)) {
?>
	<div id="<?php echo $plugin; ?>HelpView">
		<?php readfile($plxPlugin->helpFile); ?>
	</div>
<?php
}
echo "<!-- " . $plxPlugin->helpFile . " -->\n";
?>
