<?php
if(!defined('PLX_ROOT')) { exit; }

plxToken::validateFormToken($_POST);

if(empty($_SESSION[$plugin])) {
	$_SESSION[$plugin] = array(
		'author'	=> '',
		'cat'		=> '',
		'tag'		=> '',
		'pubFrom'	=> '2000-01-01',
		'pubTo'		=> '',
		'status'	=> '',
		'field'		=> false,
		'template'	=> ''
	);
}

/* ----- listing of authors ----- */
$tempAuth = $plxAdmin->aUsers;
uasort($tempAuth, function($a, $b) { return strcmp($a['name'], $b['name']); }); // Tri alphabétique
$authors = array('' => $plxPlugin->getLang('L_ALL'));
foreach($tempAuth as $id=>$fiche) {
	if(/* !empty($fiche['active']) and */ empty($fiche['delete'])) {
		$authors[$id] = $fiche['name'];
	}
}
/* ----- listing of categories ----- */
$tempCat = $plxAdmin->aCats;
uasort($tempCat, function($a, $b) { return strcmp($a['name'], $b['name']); }); // Tri alphabétique);
$cats = array(
	''		=> L_ARTICLES_ALL_CATEGORIES,
	'000'	=> L_UNCLASSIFIED
);
foreach($tempCat as $id=>$fiche) {
	if(!empty($fiche['active']) and intval($fiche['articles']) > 0) {
		$cats[$id] = $fiche['name'];
	}
}
/* ----- listing of tagged articles -------- */
$tempTag = array();
foreach($plxAdmin->aTags as $id_art=>$fiche) {
	if(!empty($fiche['active'])) {
		foreach(explode(',', $fiche['tags']) as $t) {
			$tag = strtolower(trim($t));
			if(!array_key_exists($tag, $tempTag)) { $tempTag[$tag] = array(); }
			$tempTag[$tag][] = $id_art;
		}
	}
}
$tags = array('' => '');
foreach($tempTag as $tag=>$set_arts) {
	if(empty($tag)) { $tag = '---'; }
	if(count($set_arts) > 0) {
		sort($set_arts);
		$tags[implode(',', $set_arts)] = $tag;
	} else {
		$tags[$set_arts] = $tag;
	}
}
asort($tags);
$tags[array_keys($tags)[0]] = $plxPlugin->getLang('L_ALL');
// echo "<pre><code>\n"; print_r($tags); echo "</code></pre>\n";
/* ----- listing of status for articles -------- */
$artStatus = array(
	''		=> $plxPlugin->getLang('L_ALL'),
	'pub'	=> L_PUBLISHED,
	'draft'	=> L_DRAFT,
	'mod'	=> $plxPlugin->getLang('L_MODERATE')
);
/* ------ Listing of extras fields for articles ------- */
$artFields = array();
foreach(array_keys($plxPlugin->indexFields) as $indice) {
	if($plxPlugin->adminArtDisplay($indice)) {
		$artFields[$plxPlugin->getParam('name' . $indice)] = $plxPlugin->getParam('label' . $indice);
	}
}
if($_SESSION[$plugin]['field'] === false) {
	$_SESSION[$plugin]['field'] = array_keys($artFields)[0];
}
/* ------ Listing of templates for articles ------- */
$tempTpl = array_map(
	function($item) {
		return basename($item, '.php');
	},
	glob(PLX_ROOT . $plxAdmin->aConf['racine_themes'] . $plxAdmin->aConf['style'] . '/article*.php')
);
$templates = array('' => $plxPlugin->getLang('L_ALL'));
foreach($tempTpl as $v) {
	$templates[$v] = ucwords($v);
}

$filters = array(
	'author'	=> '@^(' . implode('|', array_keys($tempAuth)) . ')?$@', // 3 digits exclusivement
	'cat'		=> '@^(000|' . implode('|', array_keys($tempCat)) . ')?$@', // idem
	'tag'		=> '@^(' . str_replace('||', '|', implode('|', array_keys($tags))) . ')?$@', // Id d'un ou plusieurs articles sur 4 digits
	'pubFrom'	=> '@(^\d{4}-\d{2}-\d{2})?$@',
	'pubTo'		=> '@(^\d{4}-\d{2}-\d{2})?$@',
	'status'	=> '@^(pub|mod|draft)?$@',
	'field'		=> '@^(' . implode('|', array_keys($artFields)) . ')$@',
	'template'	=> '@^(' . implode('|', $tempTpl) . ')?$@'
);

/* ------  Pagination ---------- */
$page = filter_input(INPUT_POST, 'artsPage', FILTER_VALIDATE_INT);
if(is_integer($page)) {
	$_SESSION[$plugin]['artsPage'] = $page;
}

/* --------- Traitement des articles cochés ------- */
if(filter_has_var(INPUT_POST, 'idArts')) {
	// Sauvegarde des articles modifiés
	$idArts = filter_input(INPUT_POST, 'idArts', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);
	if(is_array($idArts)) {
		$id = (count($idArts) == 1) ? $idArts[0] : '(' . implode('|', $idArts) . ')';
		$plxAdmin->prechauffage('@^' . $id . '\..*\.xml$@');
		$plxAdmin->page = 1;
		if($plxAdmin->getArticles()) {
			$req = filter_input(INPUT_POST, 'new_tag', FILTER_SANITIZE_STRING);
			if(is_string($req)) { $new_tag = trim($req); }
			$req = filter_input(INPUT_POST, 'del_tag', FILTER_SANITIZE_STRING);
			if(is_string($req)) { $del_tag = trim($req); }
			$arts = filter_input(INPUT_POST, 'arts', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);
			foreach($plxAdmin->plxRecord_arts->result as $content) {
				$idArt = $content['numero'];
				foreach(array_keys($artFields) as $k) {
					$field = $plxPlugin::PREFIX . $k;
					$content[$field] = filter_var($arts[$idArt][$field], FILTER_SANITIZE_STRING);
				}

				// Gestion des tags
				if(!empty($new_tag) or !empty($del_tag)) {
					if(!empty(trim($content['tags']))) {
						$tags = array_unique(array_map('trim', explode(',', $content['tags'])));
						if(!empty($del_tag)) { $tags = array_diff($tags, array($del_tag)); }
					} else {
						$tags = array();
					}
					if(!empty($new_tag)) { $tags[] = $new_tag; }
					sort($tags);
					$content['tags'] = implode(',', $tags);
				}

				// Hack against plxAdmin::editArticle
				$content['artId'] = $idArt;
				if(substr(basename($content['filename']), 0, 1) == '_') {
					$content['moderate'] = '1';
				}
				$content['catId'] = (!empty($content['categorie'])) ? explode(',', $content['categorie']) : array('000');

				// Préservation des dates
				foreach(array('creation', 'update', 'publication') as $dt) {
					$src = ($dt != 'publication') ? 'date_' . $dt : 'date';
					$content['date_' . $dt . '_year'] =	substr($content[$src], 0, 4);
					$content['date_' . $dt . '_month'] =	substr($content[$src], 4, 2);
					$content['date_' . $dt . '_day'] =		substr($content[$src], 6, 2);
					$content['date_' . $dt . '_time'] =	substr($content[$src], 8, 4);
				}
				$content['date_update_old'] = $content['date_update'];

				$result = $plxAdmin->editArticle($content, $idArt);
			}
			header('Location: plugin.php?p=' . $plugin);
			exit;
		}
	}
}

if(filter_has_var(INPUT_POST, 'filter_btn')) {
	// Filtrage des articles
	foreach($filters as $key=>$pattern) {
		if(filter_input(INPUT_POST, $key, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $pattern))) !== false) {
			$_SESSION[$plugin][$key] = $_POST[$key];
		}
	}
	$_SESSION[$plugin]['artsPage'] = 1;
}
if(!array_key_exists($_SESSION[$plugin]['tag'], $tags)) {
	$_SESSION[$plugin]['tag'] = '';
}

/* ------------- forms start here ------------- */

function printButton($i, $currentPage, $caption='') {
	$disabled = ($i === $currentPage) ? ' disabled' : '';
?>
			<button type="button" data-page="<?= $i ?>"<?= $disabled ?>><?= !empty($caption) ? $caption : $i ?></button>
<?php
}

?>
<form id="<?= $plugin; ?>FilterForm" method="post"><!--  action="/variables.php" -->
	<?= plxToken::getTokenPostMethod() ?>
	<div class="filter">
		<div>
			<label for="id_author"><?= defined('L_AUTHOR') ? L_AUTHOR : L_ARTICLE_LIST_AUTHORS ?></label>
<?php plxUtils::printSelect('author', $authors, $_SESSION[$plugin]['author']); ?>
		</div><div>
			<label for="id_cat"><?= L_CATEGORY ?></label>
<?php plxUtils::printSelect('cat', $cats, $_SESSION[$plugin]['cat']); ?>
		</div><div>
			<label for="id_tag"><?= L_ARTICLE_TAGS_FIELD ?></label>
<?php plxUtils::printSelect('tag', $tags, $_SESSION[$plugin]['tag']); ?>
		</div>
		<div class="date">
			<label for="id_pubFrom"><?php $plxPlugin->lang('L_PUB_FROM'); ?></label>
			<input type="date" id="id_pubFrom" name="pubFrom" value="<?= $_SESSION[$plugin]['pubFrom']; ?>" />
		</div><div class="date">
			<label for="id_pubTo"><?php $plxPlugin->lang('L_PUB_TO'); ?></label>
			<input type="date" id="id_pub_To" name="pubTo" value="<?= $_SESSION[$plugin]['pubTo']; ?>"/>
		</div><div>
			<label for="id_status"><?= L_ARTICLE_STATUS; ?></label>
<?php plxUtils::printSelect('status', $artStatus, $_SESSION[$plugin]['status']); ?>
		</div><div>
			<label for="id_field" title="<?php $plxPlugin->lang('L_FIRST_DISPLAYED_FIELD'); ?>"><?php $plxPlugin->lang('L_FIELD'); ?></label>
<?php plxUtils::printSelect('field', $artFields, $_SESSION[$plugin]['field']); ?>
		</div><div>
			<label for="id_template"><?php $plxPlugin->lang('L_TEMPLATE'); ?></label>
<?php plxUtils::printSelect('template', $templates, $_SESSION[$plugin]['template']); ?>
		</div><div class="date">
			<label>&nbsp;</label>
			<input type="submit" name="filter_btn"  id="<?= $plugin; ?>-filter" value="<?php $plxPlugin->lang('L_FILTER'); ?>" />
		</div>
	</div>
</form>
<?php
// ----------- tableau des articles ---------------
?>
<form id="<?= $plugin; ?>ArtsForm" method="post"><!--  action="/variables.php" -->
	<?= plxToken::getTokenPostMethod() ?>
	<input type="hidden" name="artsPage" value="<?= $plxAdmin->page; ?>" />
		<div class="articles scrollable-table"><table class="full-width">
			<thead>
				<tr id="<?= $plugin; ?>-adminHead">
					<th><input type="checkbox" id="<?= $plugin; ?>-selectAll" /></th>
					<th>Date</th>
					<th>Titre article</th>
<?php
// http://www.iconarchive.com/show/koloria-icons-by-graphicrating.html
$drop = $plxPlugin->getLang('L_DROP');
$change = $plxPlugin->getLang('L_CHANGE');
$src = PLX_PLUGINS . $plugin;

// rotation des champs
$fks = array_keys($artFields);
$i = array_search($_SESSION[$plugin]['field'], $fks);
$fieldKeys = (is_integer($i) and $i > 0) ? array_merge(array_slice($artFields, $i, null, true), array_slice($artFields, 0, $i, true)) : $artFields;
foreach($fieldKeys as $titleCol) {
?>
					<th>
						<span><?= $titleCol ?></span>
						<button type="button"><img src="<?= $src ?>/drop.png" alt="<?= $drop ?>" title="<?= $drop ?>" /></button>
						<button type="button"><img src="<?= $src ?>/edit.png" alt="<?= $change ?>" title="<?= $change ?>" /></button>
					</th>
<?php
}
?>
				</tr>
			</thead>
			<tbody id="<?= $plugin; ?>-arts">
<?php
/* -------- Génération du filtre de fichiers article ------- */
// https://wiki.pluxml.org/developper/developpement/#comprendre-le-nom-des-fichiers-xml-des-articles
$parts = array();
// one or set of articles
$tagCC = $_SESSION[$plugin]['tag'];
if(preg_match('@^\d{4}(,\d{4})*$@', $tagCC, $matches)) {
	$parts[] = (strlen($tagCC) == 4) ? $tagCC : '(' . str_replace(',', '|', $tagCC) . ')';
} else {
	$parts[] = '\d{4}'; // idArt
}
// categories
$idCat = trim($_SESSION[$plugin]['cat']);
$prefix = '_?';
switch($_SESSION[$plugin]['status']) {
	# manque la catégorie home si classé (!000)
	case 'draft':
		switch($idCat) {
			case '000' :	$parts[] = 'draft,000'; break; // articles sans catégorie
			case '' :	 	$parts[] = 'draft,(?:home|\d{3})(?:,\d{3)*'; break; // articles de toute catégorie
			default: 		$parts[] = 'draft,(?:home,|\d{3},)*' . $idCat . '(?:,\d{3})*'; // articles de la catégorie précise $idCat
		}
		break;
	case 'pub':
		$prefix = '';
		switch($idCat) {
			case '000' :	$parts[] = '000'; break; // articles sans catégorie
			case '' :	 	$parts[] = '(?:home|\d{3})(?:,\d{3})*'; break; // articles de toute catégorie
			default: 		$parts[] = '(?:home,|\d{3},)*' . $idCat . '(,\d{3})*'; // articles de la catégorie précise $idCat
		}
		break;
	case 'mod':
		$prefix = '_'; // No break !
	default:
		switch($idCat) {
			case '000' :	$parts[] = '(?:draft,)?000'; break; // articles sans catégorie
			case '' :	 	$parts[] = '(?:draft,)?(?:home|\d{3})(?:,\d{3})*'; break; // articles de toute catégorie
			default: 		$parts[] = '(?:draft,|home,|\d{3},)*' . $idCat . '(,\d{3})*'; // articles de la catégorie précise $idCat
		}
}
// author
$w = $_SESSION[$plugin]['author'];
$parts[] = (empty($w) or $w == '000') ? '\d{3}' : $w;
// date
$digitsCount = 12;
$dateFrom = str_replace('-', '', $_SESSION[$plugin]['pubFrom']);
$dateTo = str_replace('-', '', $_SESSION[$plugin]['pubTo']);
if(!empty($dateFrom) and !empty($dateTo) and $dateFrom[0] == $dateTo[0]) {
	for($i=0,$iMax=8; $i<$iMax; $i++) {
		if(substr($dateFrom, $i, 1) != substr($dateTo, $i, 1)) {
			break;
		}
	}
	$parts[] = substr($dateFrom, 0, $i) . '\d{' . ($digitsCount - $i) . '}';
} else {
	$parts[] = '\d{' . $digitsCount . '}';
}
switch($_SESSION[$plugin]['status']) {
	case 'mod' :	$prefix = '_'; break;
	case 'pub' :	$prefix = ''; break;
	default :		$prefix = '_?';
}
$patternArt = '@^' . $prefix . implode('\.', $parts) . '\..*\.xml$@';

$enableTags = false;
// $plxAdmin->plxGlob_arts->query($patternArt);
$plxAdmin->prechauffage($patternArt);
$plxAdmin->page = (!empty($_SESSION[$plugin]['artsPage'])) ? $_SESSION[$plugin]['artsPage'] : 1;
if($plxAdmin->getArticles('all')) {
	while($plxAdmin->plxRecord_arts->loop()) { // Boucle sur les articles sélectionnés
		$dateArt = substr($plxAdmin->plxRecord_arts->f('date'), 0, 8);
		if(
			(!empty($_SESSION[$plugin]['template']) and $_SESSION[$plugin]['template'] . '.php' != $plxAdmin->plxRecord_arts->f('template')) or
			(!empty($_SESSION[$plugin]['pubFrom'])  and strcmp($dateArt, $dateFrom) < 0) or
			(!empty($_SESSION[$plugin]['pubTo'])    and strcmp($dateArt, $dateTo) > 0)
		) {
			continue;
		}

		$idArt = $plxAdmin->plxRecord_arts->f('numero');
		$dateModif = plxDate::formatDate($plxAdmin->plxRecord_arts->f('date'));
		$title = $plxAdmin->plxRecord_arts->f('title');
		$catIds = explode(',', $plxAdmin->plxRecord_arts->f('categorie'));
		$draft = (in_array('draft', $catIds)) ? ' <strong>' . (defined('L_DRAFT') ? L_DRAFT : L_CATEGORY_DRAFT) . '</strong>' : '';
?>
				<tr>
					<td><input type="checkbox" name="idArts[]" value="<?= $idArt ?>" title="artId: <?= $idArt ?>" /></td>
					<td><?= $dateModif ?></td>
					<td>
						<p><a href="article.php?a=<?= $idArt ?>"><?= $title ?></a><?= $draft ?></p>
<?php
		$t = trim($plxAdmin->plxRecord_arts->f('tags'));
		if(!empty($t)) {
?>
						<p><em><?= L_ARTICLE_TAGS_FIELD . ': ' . $t ?></em></p>
<?php
		}
?>
					</td>
<?php
		foreach(array_keys($fieldKeys) as $field) {
			$fieldName = $plugin::PREFIX . $field;
			$name = "arts[$idArt][$fieldName]";
			$value = $plxAdmin->plxRecord_arts->f($fieldName);
			if($value === false) { $value = ''; }
?>
				<td><input name="<?= $name ?>" value="<?= $value ?>" class="field" /></td>
<?php
		}
?>
				</tr>
<?php
		$enableTags = true;
	}
} else {
?>
				<tr>
					<td>&nbsp;</td>
					<td colspan="<?= count($fieldKeys) + 2; ?>"><?= L_NO_ARTICLE; ?></td>
				</tr>
<?php
}
?>
			</tbody>
		</table></div>
<?php
if($enableTags) {
?>
			<div class="tag">
				<div>
					<label for="id_new_tag"><?php $plxPlugin->lang('L_NEW_TAG'); ?></label>
					<input name="new_tag" id="id_new_tag" />
				</div>
				<div>
<?php
	$t = $_SESSION[$plugin]['tag'];
	if(!empty($t) and preg_match('@^\w@', $t)) {
?>
					<input type="checkbox" name="del_tag" id="id_new_tag" value="<?= $tags[$t]; ?>" />
					<label for="id_del_tag"><?php $plxPlugin->lang('L_DEL_TAG'); ?> : <?= $tags[$t]; ?></label>
<?php
	} else {
?>
					<p><?php $plxPlugin->lang('L_NO_SELECTED_TAG'); ?></p>
<?php
	}
?>
				</div>
			</div>
<?php
}
?>
		<div class="in-action-bar">
			<input type="submit" id="<?= $plugin; ?>-submit" disabled />
<?php
$c = $plxAdmin->plxGlob_arts->query($patternArt);
if(!empty($c)) {
	$artsCount = count($c);
	// plxUtils::debugJS($artsCount, "[$plugin] \$artsCount");
	if($artsCount > $plxAdmin->bypage) {
?>
			<div id="<?= $plugin; ?>Pagination">
				<span><?php $plxPlugin->lang('L_PAGE'); ?></span>
<?php
		$bypage = intval($plxAdmin->bypage);
		$pages = intval(($artsCount + $bypage -1) / $bypage);
		if($pages > 2) {
			$i = ($plxAdmin->page > 1) ? $plxAdmin->page - 1 : 1;
			printButton($i, $plxAdmin->page, '&lt;');
		}

		for($i=1, $iMax = $pages; $i<=$iMax; $i++) {
			printButton($i, $plxAdmin->page);
		}

		if($pages > 2) {
			$i = ($plxAdmin->page < $pages - 1) ? $plxAdmin->page + 1 : $pages;
			printButton($i, $plxAdmin->page, '&gt;');
		}
?>
			</div>
<?php
	}
}
?>
		</div>
</form>
<div><em><?php $plxPlugin->lang('L_ADMIN_WARNING'); ?></em></div>
<pre style="background-color: #444; color: yellow; padding: 0 1rem 0.5rem;"><code><?php
	/* ---- pour débogage ------- */
	echo "Filtre fichiers article : $patternArt" . PHP_EOL;
	// echo "Champlus\n";	print_r($plxPlugin->indices());
	// echo '$aUsers = ';	print_r($plxAdmin->aUsers);
	// echo '$aCats = ';	print_r($plxAdmin->aCats);
	// echo '$aTags = ';	print_r($plxAdmin->aTags);
	// echo '$_SESSION[\'' . $plugin . '\'] = '; print_r($_SESSION[$plugin]);
	// echo '$_POST = ';	print_r($_POST);
	// echo '$filters = ';	print_r($filters);
	// echo '$artFields = '; print_r($artFields);
?></code></pre>
