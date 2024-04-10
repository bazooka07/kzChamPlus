<?php
if(!defined('PLX_ROOT')) { exit; }

# Control du token du formulaire
plxToken::validateFormToken($_POST);

if(filter_has_var(INPUT_POST, 'import')) {
	$other = filter_input(INPUT_POST, 'import', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	$filename = __DIR__ . "import/$other.php";
	if(!empty($other)) {
		if(file_exists($filename)) {
			include $filename;
			header('Location: parametres_plugin.php?p=' . $plugin);
			exit;
		}
	}
}

if(filter_has_var(INPUT_POST, 'name')) {
	if($plxPlugin::DEBUG) {
		error_log(
			date('Y-m-d H:i') . PHP_EOL . '$_POST = ' . print_r($_POST, true),
			3,
			PLX_ROOT . $plxAdmin->aConf['racine_articles'] . $plugin . '.log'
		);
	}

	$reqs = array();
	foreach(array_merge($plxPlugin->paramsNames, array('order'=>FILTER_VALIDATE_INT)) as $name=>$filter) {
		$reqs[$name] = filter_input(INPUT_POST, $name, $filter, FILTER_REQUIRE_ARRAY);
		if($name == 'name' and empty($reqs['name']) and !is_array($reqs['name'])) {
			header('Location: parametres_plugin.php?p=' . $plugin);
			exit;
		}
	}

	$orders = array();
	$names = array_map('trim' , $reqs['name']);
	foreach($names as $indice=>$name) {
		if(!empty($name)) {
			$plxPlugin->setParam('name' . $indice, preg_replace('#[\s-]+#', '_', $name), 'string');
			$orders[$indice] = (!empty($reqs['order'][$indice])) ? intval($reqs['order'][$indice]) : -1;
			foreach(array_keys($plxPlugin->paramsNames) as $n) {
				if($n == 'name') { continue; }
				$param = $n . $indice;
				if(!empty($reqs[$n][$indice])) {
					if($plxPlugin->isNumeric($n)) {
						$value = intval(trim($reqs[$n][$indice]));
						$format = 'numeric';
					} else {
						$value = trim($reqs[$n][$indice]);
						$format = 'cdata';
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
	$plxPlugin->orderConfig = $orders;

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

	<form id="<?= $plugin; ?>ConfigForm" method="post"> <!--  action="/variables.php" -->
		<?= plxToken::getTokenPostMethod() ?>
		<div class="scrollable-table"><table class="full-width" data-rows-num='name^="order"'>
			<thead>
				<tr>
<?php
// $notes = array('name', 'place');
$notes = array('name', 'grid');
$selects = array('entry', 'place');
foreach (array_keys($plxPlugin->paramsNames) as $name) {
	$xtra = array_search($name, $notes);
	switch($name) {
		case 'entry' :
		case 'place' :
			$className = ' class="select1"'; break;
		case 'name' :
		case 'label' :
		case 'group' :
			$className = ' class="small"'; break;
		default : $className = '';
	}
?>
				<th<?= $className; ?>><?php $plxPlugin->lang(strtoupper('L_TITLE_'.$name)); if(is_integer($xtra)) echo '<sup>' . ($xtra + 1). '</sup>'?></th>
<?php
}
?>
				</tr>
			</thead>
			<tbody id="<?= $plugin; ?>Table">
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
				<input type="checkbox" value="1" name="<?= $k; ?>" id="id_<?= $k; ?>"<?= $checked; ?> />
				<label for="id_<?= $k; ?>" style="display: inline-block;"><?php $plxPlugin->lang(strtoupper('L_'.$k)); ?></label>
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
<?php
if(!empty($notes)) {
?>
	<div>
<?php
	foreach($notes as $k=>$v) {
		$i = $k+1;
?>
		<p><sup><?= $i; ?></sup> <em><?php $plxPlugin->lang('L_WARNING' . $i); ?></em></p>
<?php
	}
?>
	</div>
<?php
}

if(!empty($plxPlugin->helpFile)) {
?>
	<div id="<?= $plugin; ?>HelpView">
		<div>
<?php readfile($plxPlugin->helpFile); ?>
			<p><input type="button" value="Masquer" class="close" /></p>
		</div>
	</div>
<?php
}

$otherConfigs = $plxPlugin->importConfigList();
if(!empty($otherConfigs)) {
	$id = 'import-dialog';
?>
<!-- Hack against PluCss -->
<style type="text/css">
	.modal .modal-container label { color: #000; }
	.modal-container input { position: initial; }
	.in-action-bar { z-index: 780; }
</style>
<div class="modal">
	<input id="<?= $id; ?>" type="checkbox" checked />
	<div class="modal__overlay">
		<label for="<?= $id; ?>" style="position: absolute; top: 1rem; right: 1rem; font-size: 200%; background-color: #888; ">&#10006;</label>
		<div id="modal__box" class="modal__box" style="margin-top: 50vh; transform: translateY(-50%);">
			<form name="<?= $plugin; ?>ConfigImport" method="post" class="inline-form modal-container" style="margin: 0 auto;">
				<?= plxToken::getTokenPostMethod() ?>
				<div class="modal-caption"><?php $plxPlugin->lang('L_IMPORT_PLUGIN'); ?></div>
				<ul class="unstyled-list">
<?php
		foreach($otherConfigs as $config) {
			$id = 'id_' . $config;
?>
					<li>
						<input type="radio" id="<?= $id; ?>" name="import" value="<?= $config; ?>" required />
						<label for="<?= $id; ?>"><?= $config; ?></label>
					</li>
<?php
		}
?>
				</ul>
				<div>
					<input type="submit" id="<?= $plugin; ?>ImportBtn" />
				</div>
			</form>
		</div>
	</div>
</div>
<?php
}

if(empty($plxAdmin->plxPlugins->aPlugins[$plugin])) {
	// Plugin inactif
	$src = PLX_PLUGINS . "$plugin/$plugin.js";
?>
		<script type="text/javascript" src="<?= $src; ?>" data-plugin="<?= $plugin; ?>"></script>
<?php
}
