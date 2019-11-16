<?php
/*
 * plugin ChamPlus
 *
 * ré-écriture complète de champArt.
 * Nécessite Pluxml 5.5 ou +, HTML5, PHP 5.6
 *
 * déplacement de la feuille de styles dans le dossier css et renommée admin.css. suppression du hook AdminTopEndHead
 * les paramètres champ, type et groupe sont renommés en name, textarea et group
 * le nouveau paramètre textarea est numérique (valeur 0 ou 1)
 * suppression des entités HTML dans le fichier de lang (UTF-8 !!!)
 * utilisation des attributs placehorder et required pour les balises <input type="text" />
 * suppression du hook AdminArticleTop
 * on peut préciser une chaîne de format pour afficher les champs (mot-clés #name#, #label#, #value#, #group")
 * suppression du fichier d'aide. Voir aide dans panneau de config.
 *
 * Pas de textarea dans les pages statiques !!
 * */

/* changelog
 * la fonction self::chamPlusArticle() est remplacée par le hook plxShowLastArtListContent ()version PluXml >= 5.5)
 * 2019-11-11 : création de admin.php
 * 2019-11-04 : fixed in AdminArticleInitData()
 * 2017-01-02 : fixed in _get_fields_art_loop()
*/

class chamPlus extends plxPlugin {
	const PREFIX = 'cps_';
	const PREFIX2 = 'champArt'; // Articles créés avec le plugin champArt

	const ADMIN_ARTICLE_CODE =
		'<?php $plxAdmin->plxPlugins->aPlugins[\'' .
		__CLASS__ .
		'\']->adminEntry((!empty($result)) ? $result : false, #PLACE#); ?>';

	const ADMIN_STATIC_CODE =
		'<?php $plxAdmin->plxPlugins->aPlugins[\'' .
		__CLASS__ .
		'\']->adminEntry($plxAdmin->aStats[$id], #PLACE#); ?>';

	const LIGNE = 3;
	const BLOCK_TEXT = 1;
	const MEDIA = 2;
	// pour champArt valeurs possibles pour $fieldTypes: ligne, bloc
	public $fieldTypes = array(
		self::LIGNE			=> 'ligne',
		self::MEDIA			=> 'media',
		self::BLOCK_TEXT	=> 'bloc-texte'
	);

	/* only for static page */
	const BOTTOM_STATIC = 1;
	const TOP_STATIC = 5;
	/* where in the article page */
	const TOP_ART = 2;
	const BOTTOM_ART = 3;
	const SIDEBAR_ART = 4;

	// pour champArt clés possibles pour $places ; top, side, bot, foot
	public $places = array(
		self::BOTTOM_ART	=> 'Pied article',
		self::SIDEBAR_ART	=> 'sidebar',
		self::TOP_ART		=> 'Tête article',
		self::BOTTOM_STATIC	=> 'Pied static',
		self::TOP_STATIC	=> 'Tête static',
	);
	public $staticPlaces = array(self::BOTTOM_STATIC, self::TOP_STATIC);

	public $paramsNames = array(
		'name' =>	FILTER_SANITIZE_STRING, // nom du champ
		'label' =>	FILTER_SANITIZE_STRING, // libellé du champ
		'entry' =>	FILTER_VALIDATE_INT, // type de saisie : ligne, bloc-texte, photo (codé en numérique)
		'group' =>	FILTER_SANITIZE_STRING,
		'place' =>	FILTER_VALIDATE_INT // emplacement pour la saisie (codé en numérique)
	);

	public $options = array('no_integration', 'champart'); # extended for Pluxml version <= 5.4

	public $order = 0;

	public function __construct($default_lang) {
		parent::__construct($default_lang);

		/* ********** hooks inside class.plx.motor.php ******** */
		$this->addHook('plxMotorParseArticle', 'plxMotorParseArticle');
		$this->addHook('plxMotorGetStatiques', 'plxMotorGetStatiques');

		if(defined('PLX_ADMIN')) {
			parent::setConfigProfil(PROFIL_ADMIN);
			parent::setAdminProfil(PROFIL_ADMIN, PROFIL_MANAGER);
			parent::setAdminMenu($this->getLang('L_TITLE_MENU'), '', $this->getLang('HELP_MENU'));

			/* ******** hooks inside class.plx.admin.php ********** */
			$this->addHook('plxAdminEditArticleXml', 'plxAdminEditArticleXml');
			$this->addHook('plxAdminEditStatique', 'plxAdminEditStatique');
			$this->addHook('plxAdminEditStatiquesUpdate', 'plxAdminEditStatiquesUpdate');
			$this->addHook('plxAdminEditStatiquesXml', 'plxAdminEditStatiquesXml');

			/* ******** hooks inside top.php ****************** */
			$this->addHook('AdminFootEndBody', 'AdminFootEndBody');

			switch(basename($_SERVER['PHP_SELF'], '.php')) {
				case 'article' :
					/* ******** hooks inside article.php ****************** */
					$this->addHook('AdminArticlePreview', 'AdminArticlePreview');
					$this->addHook('AdminArticlePostData', 'AdminArticlePostData');
					$this->addHook('AdminArticleParseData', 'AdminArticleParseData');
					$this->addHook('AdminArticleTop', 'AdminArticleTop');
					$this->addHook('AdminArticleContent', 'AdminArticleContent');
					$this->addHook('AdminArticleSidebar', 'AdminArticleSidebar');
					break;
				case 'statique' :
					/* ******** hooks inside statique.php ***************** */
					$this->addHook('AdminStaticTop', 'AdminStaticTop');
					$this->addHook('AdminStatic', 'AdminStatic');
					break;
				case 'plugin' :
					break;
				case 'parametres_plugin':
					$filename = __dir__ . '/lang/' . $default_lang . '-help.php';
					if(file_exists($filename)) {
						$this->helpFile = $filename;
					}
					break;
			}
		} else { // site
			/* ********** hooks inside class.plx.show.php ******** */
			if (defined('PLX_VERSION') and version_compare(PLX_VERSION, '5.5', '>=')) {
				$this->addHook('plxShowLastArtListContent', 'plxShowLastArtListContent');
			}

			/* ********** Use these hooks for your theme ********* */
			// Hook du plugin à utiliser sur le site dans un thème pour un article ou une page statique
			$this->addHook('chamPlus', 'chamPlus');
			// renvoie tous les champs sous forme de tableau
			$this->addHook('chamPlusList', 'chamPlusList');

			/* ******* compatibilté avec le plugin champArt  ****** */
			$this->addHook('champArt', 'champArt');
		}
	}

	/*
	 * Surcharge de la méthode plxPlugin::loadParams()
	 * importe les paramètres du plugin dans une propriété $fields plus fonctionnelle
	 * */
	public function loadParams() {
		parent::loadParams();

		$fields = array();
		$indexFields = array();
		$params = $this->getParams();
		$names = array_filter(
			array_keys($params),
			function($k) { return (strpos($k, 'name') === 0); }
		);
		foreach(array_map(function($v) { return substr($v, strlen('name')); }, $names) as $indice) {
			$entry = array();
			foreach(array('label', 'group') as $k) {
				$value = $this->getParam($k . $indice);
				if(!empty($value)) { $entry[$k] = $value; }
			}
			foreach(array('entry', 'place') as $k) {
				$value = intval($this->getParam($k . $indice));
				if(!empty($value)) { $entry[$k] = $value; }
			}
			$nameField = $this->getParam('name' . $indice);
			$fields[$nameField] = $entry;
			$entry['name'] = $nameField;
			$indexFields[$indice] = $entry;
		}

		$this->fields = $fields;
		$this->indexFields = $indexFields;
	}

	/*
	 * Précise si l'entrée dans $_POST doit être numérique pour config.php
	 * */
	public function isNumeric($name) {
		return in_array($name, array('place', 'entry'));
	}

	private function isMedia($fieldName) {
		return $this->fields[$fieldName]['entry'] == self::MEDIA;
	}

	public function newIndice() {
		$t = array_keys($this->indexFields);
		return ((!empty($t)) ? max($t) + 1 : 1);
	}

	public function adminArtDisplay($indice) {
		return !in_array($this->getParam('place' . $indice), array(self::TOP_STATIC, self::BOTTOM_STATIC));
	}

	/*
	 * Imprime les rangées du tableau dans config.php
	 * */
	public function printFieldConfig($indice) {
?>
				<tr>
<?php
		foreach(array_keys($this->paramsNames) as $name) {
			// $value = (empty($new)) ? plxUtils::strCheck($this->getParam($name . $indice)) : '';
			$value = (array_key_exists($name, $this->indexFields[$indice])) ? $this->indexFields[$indice][$name] : '';
			$field = $name . '[' . $indice . ']';
			// $keyword = 'L_CHAMPLUS_' . $name;
?>
					<td>
<?php
			switch($name) {
				case 'entry':
					if(empty($value)) { $value = self::LIGNE; }
					plxUtils::printSelect($field, $this->fieldTypes, $value);
					$order = 'order[' . $indice . ']';
					$this->order++;
					plxUtils::printInput($order, $this->order, 'hidden');
					break;
				case 'place' :
					if(empty($value)) { $value = self::BOTTOM_ART; }
					plxUtils::printSelect($field, $this->places, $value);
					break;
				default:
					plxUtils::printInput($field, $value, 'text', '');
			}
?>
					</td>
<?php
		}
?>
				</tr>
<?php
	}

	/*
	 * Affiche l'édition d'un champ dans une page statique ou dans un article
	 * */
	public function adminEntry($data, $place=self::BOTTOM_ART) {
		$entries = array_filter($this->fields, function($value) use($place) {
			return (!empty($value['place']) and $value['place'] == $place);
		});
		foreach($entries as $key => $params) {
			$fieldName = self::PREFIX . $key;
			$caption = $params['label'];
			$value = (!empty($data) and array_key_exists($fieldName, $data)) ? $data[$fieldName] : '';
			if($place != self::TOP_STATIC and $place != self::BOTTOM_STATIC) { // article
				$size = ($place == self::SIDEBAR_ART) ? '27-255' : '42-255';
				$cols = 35;
				$rows = 8;
				$className = ($place == self::SIDEBAR_ART) ? '' : 'full-width';
			} else { // static page - no sidebar !
				$size = '50-255';
				$className = 'full-width';
				$cols = 35;
				$rows = 8;
			}
?>
				<div class="grid">
					<div class="col sml-12<?php if($place == self::TOP_ART) { echo ' med-7 lrg-8'; } /* hack against PluXml */ ?>">
<?php
			switch($params['entry']) {
				case self::LIGNE :
?>
						<label for="id_title"><?php echo $caption ?>&nbsp;:</label>
						<?php plxUtils::printInput($fieldName, plxUtils::strCheck($value), 'text', $size, false, $className); echo "\n"; ?>
<?php
					break;
				case self::BLOCK_TEXT :
					if(!$place != self::SIDEBAR_ART) { // for static 140,30 otherwise 35,8
?>
						<label for="id_<?php echo $fieldName; ?>"><?php echo $caption; ?>&nbsp;:&nbsp;<a id="toggler_<?php echo $fieldName; ?>" href="javascript:void(0)" onclick="toggleDiv('toggle_<?php echo $fieldName; ?>', 'toggler_<?php echo $fieldName; ?>', '<?php echo L_ARTICLE_CHAPO_DISPLAY ?>','<?php echo L_ARTICLE_CHAPO_HIDE ?>')"><?php echo (empty($value)) ? L_ARTICLE_CHAPO_DISPLAY : L_ARTICLE_CHAPO_HIDE; ?></a></label>
						<div id="toggle_<?php echo $fieldName; ?>"<?php echo ($value !='') ? '' : ' style="display:none"' ?>>
						<?php plxUtils::printArea($fieldName, plxUtils::strCheck($value), $cols, $rows, false, 'full-width'); echo "\n"; ?>
						</div>
<?php
					}
					break;
				case self::MEDIA :
?>
						<label for="id_<?php echo $fieldName; ?>">
							<?php echo $caption; ?>&nbsp;:&nbsp;
							<a title="<?php echo L_THUMBNAIL_SELECTION ?>" id="toggler_<?php echo $fieldName; ?>" href="javascript:void(0)" onclick="mediasManager.openPopup('id_<?php echo $fieldName; ?>', true)" style="outline:none; text-decoration: none">+</a>
						</label>
						<?php plxUtils::printInput($fieldName,plxUtils::strCheck($value), 'text', $size, false, $className, '', 'onkeyup="refreshImg(this.value)"'); ?>
						<div id="id_<?php echo $fieldName; ?>_img">
						<?php
						$src = $value;
						if(!preg_match('@^(?:https?|data):@', $value)) {
							$src = PLX_ROOT . $value;
							$src = is_file($src) ? $src : false;
						}
						if($src) {
							echo <<< EOT
<img src="$src" title="$value" />\n
EOT;
						}
						?>
						</div>
<?php
					break;
			}
?>
					</div>
				</div>
<?php
		}
	}

	/* ========================== HOOKS ========================= */
	public function AdminFootEndBody() {
		$src = PLX_PLUGINS . __CLASS__ . '/' . __CLASS__ . '.js';
?>
		<script type="text/javascript" src="<?php echo $src; ?>" data-plugin="<?php echo __CLASS__; ?>"></script>
<?php
	}

	/* -------------------- article.php ------------------------ */
	const ADMIN_ARTICLE_PARSE_DATA_CODE = <<< 'ADMIN_ARTICLE_PARSE_DATA_CODE'
	$#FIELD_NAME# = $result['#FIELD_NAME#'];
ADMIN_ARTICLE_PARSE_DATA_CODE;
	public function AdminArticleParseData() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and !in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::ADMIN_ARTICLE_PARSE_DATA_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	const ADMIN_ARTICLE_PREVIEW_CODE = <<< 'ADMIN_ARTICLE_PREVIEW_CODE'
	$art['#FIELD_NAME#'] = $_POST['#FIELD_NAME#'];
ADMIN_ARTICLE_PREVIEW_CODE;
	public function AdminArticlePreview() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and !in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::ADMIN_ARTICLE_PREVIEW_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	const ADMIN_ARTICLE_POSTDATA_CODE = <<< 'ADMIN_ARTICLE_POSTDATA_CODE'
	$#FIELD_NAME# = $_POST['#FIELD_NAME#'];
ADMIN_ARTICLE_POSTDATA_CODE;
	public function AdminArticlePostData() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and !in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::ADMIN_ARTICLE_POSTDATA_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	// Add fields in article.php
	public function AdminArticleTop()		{ echo str_replace('#PLACE#', self::TOP_ART,		self::ADMIN_ARTICLE_CODE); }
	public function AdminArticleContent()	{ echo str_replace('#PLACE#', self::BOTTOM_ART,		self::ADMIN_ARTICLE_CODE); }
	public function AdminArticleSidebar()	{ echo str_replace('#PLACE#', self::SIDEBAR_ART,	self::ADMIN_ARTICLE_CODE); }
	// Add fields in statique.php
	public function AdminStaticTop() 		{ echo str_replace('#PLACE#', self::TOP_STATIC,		self::ADMIN_STATIC_CODE); }
	public function AdminStatic()			{ echo str_replace('#PLACE#', self::BOTTOM_STATIC,	self::ADMIN_STATIC_CODE); }

	// Load the fields of article from XML file
	const PLXMOTOR_PARSE_ARTICLE_CODE = <<< 'PLXMOTOR_PARSE_ARTICLE_CODE'
	$art['#FIELD_NAME#'] = (isset($iTags['#FIELD_NAME#'])) ? plxUtils::getValue($values[$iTags['#FIELD_NAME#'][0]]['value']) : '';
PLXMOTOR_PARSE_ARTICLE_CODE;
	public function plxMotorParseArticle() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and !in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::PLXMOTOR_PARSE_ARTICLE_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	// Save the  fields of article to XML file
	const PLXADMIN_EDIT_ARTICLE_XML_CODE = <<< 'PLXADMIN_EDIT_ARTICLE_XML_CODE'
	$xml .= "\t<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck(trim($content['#FIELD_NAME#']))."]]></#FIELD_NAME#>\n";
PLXADMIN_EDIT_ARTICLE_XML_CODE;
	public function plxAdminEditArticleXml() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and !in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDIT_ARTICLE_XML_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	// load data from statiques.xml in class.plx.motor
	const PLXMOTOR_GETSTATIQUES_CODE = <<< 'PLXMOTOR_GETSTATIQUES_CODE'
	$f = '#FIELD_NAME#';
	$value = (array_key_exists($f, $iTags)) ? plxUtils::getValue($values[$iTags[$f][$i]]['value']) : '';
	$this->aStats[$number][$f] = $value;
PLXMOTOR_GETSTATIQUES_CODE;
	public function plxMotorGetStatiques() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::PLXMOTOR_GETSTATIQUES_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	const PLXADMIN_EDITSTATIQUE_CODE = <<< 'PLXADMIN_EDITSTATIQUE_CODE'
		$this->aStats[\$content['id']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
PLXADMIN_EDITSTATIQUE_CODE;
	public function plxAdminEditStatique() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDITSTATIQUE_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	const PLXADMIN_EDITSTATIQUES_UPDATE_CODE = <<< 'PLXADMIN_EDITSTATIQUES_UPDATE_CODE'
	$this->aStats[$static_id]['#FIELD_NAME#'] = (isset($this->aStats[$static_id]['#FIELD_NAME#']) ? $this->aStats[$static_id]['#FIELD_NAME#'] : '');
PLXADMIN_EDITSTATIQUES_UPDATE_CODE;
	public function plxAdminEditStatiquesUpdate() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDITSTATIQUES_UPDATE_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	const PLXADMIN_EDITSTATIQUES_XML_CODE = <<< 'PLXADMIN_EDITSTATIQUES_XML_CODE'
	$xml .= "<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck($static['#FIELD_NAME#'])."]]></#FIELD_NAME#>";
PLXADMIN_EDITSTATIQUES_XML_CODE;
	public function plxAdminEditStatiquesXml() {
		echo '<?php' . PHP_EOL;
		$entries = array_filter($this->fields, function($value) {
			return (!empty($value['place']) and in_array($value['place'], $this->staticPlaces));
		});
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDITSTATIQUES_XML_CODE) . PHP_EOL;
		}
		echo '?>' . PHP_EOL;
	}

	const PLXSHOW_LASTARTLIST_CONTENT_CODE = <<< 'PLXSHOW_LASTARTLIST_CONTENT_CODE'
<?php
if(preg_match_all('#PATTERN#', $format, $matches)) {
	$staticPlaces = array('#STATIC_PLACES#');
	$replaces = array();
	foreach($matches[0] as $k) {
		if(!in_array($k, $staticPlaces)) {
			$replaces['#PREFIX#' . $k] = plxUtils::strCheck($art[$k]);
		}
	}
	$row = strtr($row, $replaces);
}
?>
PLXSHOW_LASTARTLIST_CONTENT_CODE;
	public function plxShowLastArtListContent() {
		echo strtr(self::PLXSHOW_LASTARTLIST_CONTENT_CODE, array(
			'#PATTERN#'			=> '%#' . self::PREFIX . '_(?:' . implode('|', array_keys($this->fields)). ')%',
			'#STATIC_PLACES#'	=> implode('\', \'', $this->staticPlaces),
			'#PREFIX#'			=> '#' . self::PREFIX . '_'
		)) . PHP_EOL;
	}

	/* ********************** Hooks spécifiques au plugin ****************** */

	/* ********************************************************************************
	 * si params est de type string, alors on affiche la valeur du champ correspondant
	 * si params est de type array, il peut contenir jusqu'à 2 éléments
	 * le 1er élément est le nom du champ
	 * si le 2ème élement est égal à false, alors on affiche la valeur du champ comme dans le 1er cas
	 * si le 2ème élement est égal  à true, alors on renvoir la valeur du champ sans l'afficher
	 * sinon le 2ème élément, de type string, est un format pour afficher le champ
	 *
	 * Si la valeur du champ est vide (empty) alors
	 * - s'il n'y pas de 3ème paramètre, on affiche rien
	 * - si le 3ème paramètre est de type string, c'est la chaine à utiliser quand la valeur est nulle.
	 * ******************************************************************************* */
	public function chamPlus($params) {
		global $plxMotor;

		if (is_string($params)) { # affiche uniquement la valeur du champ
			list($name, $format, $empty_format) = array($params, false, false);
		} elseif(is_array($params)) {
			list($name, $format, $empty_format) = array_pad($params, 3, false);
		} else {
			echo "What do you want ?";
			return;
		}

		if(!array_key_exists($name, $this->$fields)) {
			echo $params . $this->getLang('L_BAD_VALUE');
			return;
		}

		$nameField = self::PREFIX . $name;
		if ($plxMotor->mode == 'place') {
			$static_id =  $plxMotor->cible;
			$value = plxUtils::strCheck($plxMotor->aStats[$static_id][$nameField]);
		}
		else
			$value = $plxMotor->plxRecord_arts->f($nameField);

		# traitement
		if ($format === true)
			// pas d'affichage, on retourne simplement la valeur
			return $value;
		elseif($format === false) {
			// Pas de chaine de format, on imprime la valeur du champ
			if (!$this->isMedia($name) or empty($this->getParam('no_integration'))) {
				// on affiche uniquement la valeur
				echo $value;
			} else {  // C'est un média est une image
				$title = basename($value);

				if (preg_match('#\.(?:jpe?g|png|svg|gif)$#', $value)) { // C'est une image
					if(file_exists(PLX_ROOT . $value)) {
						$imagesize = getimagesize(PLX_ROOT . $value);
						$attrs = ' ' . $imagesize[3];
					} else {
						$attrs = '';
					}
					echo <<< EOT
<img src="$value" alt="$value" title="$title"$attrs />
EOT;
				} else {  // Ce n'est pas une image
					echo <<< EOT
<a href="$value" target="_blank">$title</a>
EOT;
				}
			}
		}
		else { // format défini
			$fmt = (!empty($value)) ? $format : $empty_format;
			if (is_string($fmt)) {
				echo strtr($fmt, array(
					'#name#'	=> $name,
					'#value#'	=> $value,
					'#label#'	=> $this->fields[$name]['label'],
					'#group#'	=> $this->fields[$name]['group'],
					'#groupe#'	=> $this->fields[$name]['group'],
					'#place#'	=> $this->fields[$name]['place']
				));
			} else {
				$this->lang('L_BAD_FORMAT');
			}
		}
		return false;
	}

	public function chamPlusList($pretty_print=false) {
		if($pretty_print) {
?>
<table class="<?php echo __CLASS__; ?>">
	<thead>
		<tr>
<?php	foreach(array_keys($plxPlugin->paramsNames) as $name) { ?>
			<th><?php $plxPlugin->lang(strtoupper('L_CHAMPLUS_TITLE_'.$name)); ?></th>
<?php	} ?>
	</tr></thead>
	<tbody>
<?php
	foreach(array_keys($plxPlugin->indexFields) as $i) {
?>
				<tr>
<?php
		foreach(array_keys($this->paramsNames) as $name) {
			$value = $this->$indexFields[$indice][$name];
			if(empty($value)) { $value = '&nbsp;'; }
?>
					<td>
<?php
			switch($name) {
				case 'entry':	echo $this->fieldTypes[$value]; break;
				case 'place':	echo $this->places[$value]; break;
				default:		echo $value;
			}
?>
					</td>
<?php
		}
?>
				</tr>
<?php
	}
?>
	</tbody>
</table>
<?php
		} else {
			return $this->fields;
		}
	}

	/*
	 * Pour les utilisateurs du plugin champArt :
	 * si $value se termine par '_R' on renvoie la valeur du champ
	 * si $value se termine par _L on imprime la valeur du champ, précédée de l'étiquette'
	 * sinon on imprime la valeur du champ
	 * */
	public function champArt($param) {
		if(preg_match('#(' . implode('|', array_keys($this->fields)). ')(?:_(L|R))?$#i', $param, $matches)) {
			$nameField = $matches[1];
			if(array_key_exists($nameField, $this->fields) and !in_array($this->fields[$nameField]['entry'], $this->staticPlaces)) {
				$value = $value = $plxMotor->plxRecord_arts->f(self::PREFIX . $nameField);
				if(empty($matches[2])) {
					echo $value;
				} else {
					switch(strtoupper($matches[2])) {
						case 'L' :
							$label = $this->fields[$nameField]['label'];
							echo <<< EOT
<span class="label">$label</span> $value
EOT;
							break;
						case 'R' :
							return $value;
							break;
					}
				}
			}
		}
	}
}
?>
