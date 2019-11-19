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
 * */

/* changelog
 * 2019-11-17 :le plugin chamPlus est renommé en kzChamPlus
 * la fonction self::chamPlusArticle() est remplacée par le hook plxShowLastArtListContent ()version PluXml >= 5.5)
 * 2019-11-11 : création de admin.php
 * 2019-11-04 : fixed in AdminArticleInitData()
 * 2017-01-02 : fixed in _get_fields_art_loop()
*/

class kzChamPlus extends plxPlugin {
	const PREFIX = 'cps_';
	const PREFIX_IMPORT1 = 'champArt_';

	const OPEN_CODE = '<?php $plxAdmin->plxPlugins->aPlugins[\'' . __CLASS__ . '\']->adminEntry(';
	const CLOSE_CODE = ', #PLACE#); ?>';

	const ADMIN_ARTICLE_CODE =	self::OPEN_CODE . '(!empty($result)) ? $result : false' . self::CLOSE_CODE;
	const ADMIN_STATIC_CODE =	self::OPEN_CODE . '$plxAdmin->aStats[$id]' .	self::CLOSE_CODE;
	const ADMIN_CAT_CODE =		self::OPEN_CODE . '$plxAdmin->aCats[$id]'  .	self::CLOSE_CODE;
	const ADMIN_USER_CODE =		self::OPEN_CODE . '$plxAdmin->aUsers[$id]' .	self::CLOSE_CODE;

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
	/* where in the categorie page */
	const TOP_CAT = 6;
	const BOTTOM_CAT = 7;
	/* where in the user page */
	const TOP_USER = 8;
	const BOTTOM_USER = 9;

	// pour champArt clés possibles pour $places ; top, side, bot, foot
	public $places = array(
		self::BOTTOM_ART	=> 'L_BOTTOM_ART_PLACE',
		self::SIDEBAR_ART	=> 'L_SIDEBAR_ART_PLACE',
		self::TOP_ART		=> 'L_TOP_ART_PLACE',
		self::BOTTOM_STATIC	=> 'L_BOTTOM_STATIC_PLACE',
		self::TOP_STATIC	=> 'L_TOP_STATIC_PLACE',
		self::BOTTOM_CAT	=> 'L_BOTTOM_CAT_PLACE',
		self::TOP_CAT		=> 'L_TOP_CAT_PLACE',
		self::BOTTOM_USER	=> 'L_BOTTOM_USER_PLACE',
		self::TOP_USER		=> 'L_TOP_USER_PLACE',
	);
	public $artPlaces =		array(self::BOTTOM_ART, self::TOP_ART, self::SIDEBAR_ART);
	public $staticPlaces =	array(self::BOTTOM_STATIC, self::TOP_STATIC);
	public $catPlaces =		array(self::BOTTOM_CAT, self::TOP_CAT);
	public $userPlaces =	array(self::BOTTOM_USER, self::TOP_USER);

	// Check self::loadParams and self::printFieldConfig for new fields
	public $paramsNames = array(
		'name' =>	FILTER_SANITIZE_STRING, // nom du champ
		'label' =>	FILTER_SANITIZE_STRING, // libellé du champ
		'entry' =>	FILTER_VALIDATE_INT,	// type de saisie : ligne, bloc-texte, photo (codé en numérique)
		'place' =>	FILTER_VALIDATE_INT,	// emplacement pour la saisie (codé en numérique)
		'invite'=>	FILTER_SANITIZE_STRING,
		'grid'	=>	FILTER_SANITIZE_STRING,
		'group' =>	FILTER_SANITIZE_STRING
	);

	public $options = array('no_integration' /*, 'champart'*/ ); # extended for Pluxml version <= 5.4

	public $order = 0;

	public function __construct($default_lang) {
		parent::__construct($default_lang);

		parent::setConfigProfil(PROFIL_ADMIN);

		$myScript = basename($_SERVER['PHP_SELF'], '.php');
		if($myScript == 'parametres_plugin') { // parametres_plugin.php?p=__CLASS__
			// Multi-linguisme
			foreach($this->places as $k => $v) {
				$this->places[$k] = $this->getLang($v);
			}
			$filename = __dir__ . '/lang/' . $default_lang . '-help.php';
			if(file_exists($filename)) {
				$this->helpFile = $filename;
			}
		} elseif(empty($this->fields)) {
			return;
		}

		/* ********** hooks inside class.plx.motor.php ******** */
		$this->addHook('plxMotorParseArticle', 'plxMotorParseArticle');
		$this->addHook('plxMotorGetStatiques', 'plxMotorGetStatiques');
		$this->addHook('plxMotorGetCategories', 'plxMotorGetCategories');
		$this->addHook('plxMotorGetUsers', 'plxMotorGetUsers');

		if(defined('PLX_ADMIN')) {
			parent::setAdminProfil(PROFIL_ADMIN, PROFIL_MANAGER);
			parent::setAdminMenu($this->getLang('L_TITLE_MENU'), '', $this->getLang('HELP_MENU'));

			/* ******** hooks inside top.php ****************** */
			$this->addHook('AdminFootEndBody', 'AdminFootEndBody');

			switch($myScript) {
				case 'article' :
					/* ******** hooks for article.php ****************** */
					$this->addHook('plxAdminEditArticleXml', 'plxAdminEditArticleXml');
					$this->addHook('AdminArticlePreview', 'AdminArticlePreview');
					$this->addHook('AdminArticlePostData', 'AdminArticlePostData');
					$this->addHook('AdminArticleParseData', 'AdminArticleParseData');
					$this->addHook('AdminArticleTop', 'AdminArticleTop');
					$this->addHook('AdminArticleContent', 'AdminArticleContent');
					$this->addHook('AdminArticleSidebar', 'AdminArticleSidebar');
					break;
				case 'statique' :
					/* ******** hooks for statique.php ***************** */
					$this->addHook('plxAdminEditStatique', 'plxAdminEditStatique');
					$this->addHook('plxAdminEditStatiquesUpdate', 'plxAdminEditStatiquesUpdate');
					$this->addHook('plxAdminEditStatiquesXml', 'plxAdminEditStatiquesXml');
					$this->addHook('AdminStaticTop', 'AdminStaticTop');
					$this->addHook('AdminStatic', 'AdminStatic');
					break;
				case 'categorie' :
					/* ******** hooks for categorie.php ***************** */
					$this->addHook('plxAdminEditCategorie', 'plxAdminEditCategorie');
					$this->addHook('plxAdminEditCategoriesUpdate', 'plxAdminEditCategoriesUpdate');
					$this->addHook('plxAdminEditCategoriesXml', 'plxAdminEditCategoriesXml');
					$this->addHook('AdminCategoryTop', 'AdminCategoryTop');
					$this->addHook('AdminCategory', 'AdminCategory');
					break;
				case 'user' :
					/* ******** hooks for user.php ***************** */
					$this->addHook('plxAdminEditUser', 'plxAdminEditUser');
					$this->addHook('plxAdminEditUsersUpdate', 'plxAdminEditUsersUpdate');
					$this->addHook('plxAdminEditUsersXml', 'plxAdminEditUsersXml');
					$this->addHook('AdminUserTop', 'AdminUserTop');
					$this->addHook('AdminUser', 'AdminUser');
					break;
				case 'plugin' :
					break;
			}
		} else { // site
			/* ********** hooks inside class.plx.show.php ******** */
			if (defined('PLX_VERSION') and version_compare(PLX_VERSION, '5.5', '>=')) {
				$this->addHook('plxShowLastArtListContent', 'plxShowLastArtListContent');
				$this->addHook('plxShowLastCatListContent', 'plxShowLastCatListContent');
			}

			/* ********** Use these hooks for your theme ********* */
			// Hook du plugin à utiliser sur le site dans un thème pour un article ou une page statique
			$this->addHook('kzChamPlus', 'kzChamPlus');
			$this->addHook('chamPlus', 'kzChamPlus');
			// renvoie tous les champs sous forme de tableau
			$this->addHook('chamPlusList', 'kzChamPlusList');

			/* ******* compatibilité pour les thèmes dédiés au plugin champArt  ****** */
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
		if(empty($params)) { return; }

		$names = array_filter(
			array_keys($params),
			function($k) { return (strpos($k, 'name') === 0); }
		);
		if(empty($names)) { return; }

		foreach(array_map(function($v) { return substr($v, strlen('name')); }, $names) as $indice) {
			$entry = array();
			foreach(array('label', 'group', 'invite', 'grid') as $k) {
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

	public function saveParams() {
		if(!empty($this->orderConfig)) {
			// Sorting parameters of plugin
			$pattern = '#^(' . implode('|', $this->paramsNames). ')(\d+)$#';
			$orders = $this->orderConfig;
			uksort($this->aParams, function($a, $b) use($pattern, $orders) {
				if(
					preg_match($pattern, $a, $matchA) and
					preg_match($pattern, $b, $matchB)
				) {
					if($matchA[2] == $matchB[2]) {
						return strcmp($matchA[1], $matchB[1]);
					} else {
						return ($orders[$matchA[2]] - $orders[$matchB[2]]);
					}
				} else {
					return (!empty($matchA[2])) ? -1 : 1;
				}
			});
		}

		parent::saveParams();
	}

	public function getConfigPath($filename=false) {
		$path1 = preg_replace('#/'. __CLASS__ . '.xml$#', '/', $this->plug['parameters.xml']);
		return $path1 . ((is_string($filename)) ? basename($filename , '.php') . '.xml' : '');
	}

	public function importConfigList() {
		if(!empty($this->fields)) { return false; }

		$output = array();
		$path1 = self::getConfigPath();
		foreach(array('champArt', 'chamPlus') as $k) {
			if(is_readable($path1 . $k . '.xml') and file_exists(__DIR__ . '/import/' . $k . '.php')) {
				$output[] = $k;
			}
		}
		return (!empty($output)) ? $output : false;
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
		if(empty($this->indexFields)) { return 2; }
		$t = array_keys($this->indexFields);
		return ((!empty($t)) ? max($t) + 1 : 2);
	}

	public function adminArtDisplay($indice) {
		return in_array($this->indexFields[$indice]['place'], $this->artPlaces);
	}

	/*
	 * For config.php. Add row in the table for a field with attributes for every key of $this->paramsNames :
	 * name, label entry, place, group, ...
	 * */
	public function printFieldConfig($indice) {
?>
				<tr>
<?php
		foreach(array_keys($this->paramsNames) as $name) {
			if($indice > 0) {
				$value = (array_key_exists($name, $this->indexFields[$indice])) ? $this->indexFields[$indice][$name] : '';
				$suffixe = '[' . $indice . ']';
			} else {
				$value = '';
				$suffixe = '[1]';
			}
			$field = $name . $suffixe;
?>
					<td>
<?php
			switch($name) {
				case 'entry':
					if(empty($value)) { $value = self::LIGNE; }
					plxUtils::printSelect($field, $this->fieldTypes, $value);
					if($indice >= 0) {
						$order = 'order[' . $indice . ']';
						$this->order++;
					} else {
						$order = 'order[1]';
					}
					plxUtils::printInput($order, $this->order, 'hidden');
					break;
				case 'place' :
					if(empty($value)) { $value = self::BOTTOM_ART; }
					plxUtils::printSelect($field, $this->places, $value);
					break;
				case 'name' :
					plxUtils::printInput($field, $value, 'text', '', false, '', '', 'pattern="\w*"');
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
	 * insert fields into several pages
	 * */
	public function adminEntry($data, $place=self::BOTTOM_ART) {
		if(empty($this->fields)) { return; }

		$entries = array_filter($this->fields, function($value) use($place) {
			return (!empty($value['place']) and $value['place'] == $place);
		});
		if(empty($entries)) { return; }
?>
				<div class="grid <?php echo __CLASS__; ?>">
<?php
		foreach($entries as $key => $params) {
			$fieldName = self::PREFIX . $key;
			$caption = $params['label'];
			$value = (!empty($data) and array_key_exists($fieldName, $data)) ? addslashes($data[$fieldName]) : '';

			# default values
			$cols = 35;
			$rows = 8;
			$size = '';
			$className = 'full-width';

			if(in_array($place, $this->artPlaces)) { // article
				$size = ($place == self::SIDEBAR_ART) ? '27-255' : '42-255';
				$className = ($place == self::SIDEBAR_ART) ? '' : 'full-width';
			} elseif(in_array($place, $this->staticPlaces)) { // static page - no sidebar !
				$size = '50-255';
			} elseif(in_array($place, $this->catPlaces)) {
				// nothing to do
			} elseif(in_array($place, $this->userPlaces)) {
				// nothing to do
			} else {
				return; // Bye !!!
			}
			$grid = 'sml-12';
			if(!empty($params['grid'])) {
				$grid = $params['grid'];
			} elseif($place == self::TOP_ART) {
				$grid .= ' med-7 lrg-8'; /* hack against PluXml */
			}
			$grid = preg_replace('#^col\b\s*#', '', $grid);
			$placeholder = (!empty($params['invite'])) ? $params['invite'] . '"' : '';
?>
					<div class="col <?php echo $grid; ?>">
<?php
			switch($params['entry']) {
				case self::LIGNE :
?>
						<label for="id_title"><?php echo $caption ?>&nbsp;:</label>
						<?php plxUtils::printInput($fieldName, plxUtils::strCheck($value), 'text', $size, false, $className, $placeholder); echo PHP_EOL; ?>
<?php
					break;
				case self::BLOCK_TEXT :
					if(!$place != self::SIDEBAR_ART) { // for static 140,30 otherwise 35,8
?>
						<label for="id_<?php echo $fieldName; ?>"><?php echo $caption; ?>&nbsp;:&nbsp;<a id="toggler_<?php echo $fieldName; ?>" href="javascript:void(0)" onclick="toggleDiv('toggle_<?php echo $fieldName; ?>', 'toggler_<?php echo $fieldName; ?>', '<?php echo L_ARTICLE_CHAPO_DISPLAY ?>','<?php echo L_ARTICLE_CHAPO_HIDE ?>')"><?php echo (empty($value)) ? L_ARTICLE_CHAPO_DISPLAY : L_ARTICLE_CHAPO_HIDE; ?></a></label>
						<div id="toggle_<?php echo $fieldName; ?>"<?php echo ($value !='') ? '' : ' style="display:none"' ?>>
						<?php plxUtils::printArea($fieldName, plxUtils::strCheck($value), $cols, $rows, false, 'full-width'); echo PHP_EOL; ?>
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
						<?php plxUtils::printInput($fieldName,plxUtils::strCheck($value), 'text', $size, false, $className, $placeholder, 'onkeyup="refreshImg(this.value)"'); ?>
						<div id="id_<?php echo $fieldName; ?>_img">
						<?php
						$src = $value;
						$alt = basename($value);
						$attr =  '';
						if(!preg_match('@^(?:https?|data):@', $value)) {
							$src = PLX_ROOT . $value;
							if(file_exists($src)) {
								$thumbName = preg_replace('#\.(jpe?g|png|gif)$#', '.tb.$1', $src);
								if(file_exists($thumbName)) { $src = $thumbName; }
								list($width, $height, $type, $attr) = getimagesize($src);
							} else {
								$src = false;
							}
						}
						if($src) {
							$className = __CLASS__;
							echo <<< EOT
<img src="$src" alt="$alt" class="$className" title="$value"$attr />\n
EOT;
						}
						?>
						</div>
<?php
					break;
			}
?>
					</div>
<?php
		}
?>
				</div>
<?php
	}

	// Add fields in article.php
	public function AdminArticleTop()		{ echo str_replace('#PLACE#', self::TOP_ART,		self::ADMIN_ARTICLE_CODE); }
	public function AdminArticleContent()	{ echo str_replace('#PLACE#', self::BOTTOM_ART,		self::ADMIN_ARTICLE_CODE); }
	public function AdminArticleSidebar()	{ echo str_replace('#PLACE#', self::SIDEBAR_ART,	self::ADMIN_ARTICLE_CODE); }
	// Add fields in statique.php
	public function AdminStaticTop() 		{ echo str_replace('#PLACE#', self::TOP_STATIC,		self::ADMIN_STATIC_CODE); }
	public function AdminStatic()			{ echo str_replace('#PLACE#', self::BOTTOM_STATIC,	self::ADMIN_STATIC_CODE); }
	// Add fields in categorie.php
	public function AdminCategoryTop() 		{ echo str_replace('#PLACE#', self::TOP_CAT,		self::ADMIN_CAT_CODE); }
	public function AdminCategory()			{ echo str_replace('#PLACE#', self::BOTTOM_CAT,		self::ADMIN_CAT_CODE); }
	// Add fields in user.php
	public function AdminUserTop() 			{ echo str_replace('#PLACE#', self::TOP_USER,		self::ADMIN_USER_CODE); }
	public function AdminUser()				{ echo str_replace('#PLACE#', self::BOTTOM_USER,	self::ADMIN_USER_CODE); }

	private function _process($filter, $code) {
		if(empty($this->fields)) { return; }

		$entries = array_filter($this->fields, function($value) use($filter) {
			return (!empty($value['place']) and in_array($value['place'], $filter));
		});
		if(empty($entries)) { return; }

		echo '<?php ' . PHP_EOL;
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, $code) . PHP_EOL;
		}
		echo ' ?>' . PHP_EOL;
	}
	/* ========================== HOOKS for  getting and saving values ========================= */

	/*
	 * manage overlay for thumbnails.
	 * Useful scripts for config.php and admin.php
	 * */
	public function AdminFootEndBody() {
		// overlay for thumbnail like medias.php
		if(in_array(basename($_SERVER['PHP_SELF'], '.php'), array('article', 'statique', 'categorie', 'user'))) {
			$id = __CLASS__ . '-modal';
?>
<div class="modal">
	<input id="<?php echo $id; ?>" type="checkbox">
	<div class="modal__overlay">
		<label for="<?php echo $id; ?>">&#10006;</label>
		<div id="modal__box" class="modal__box">
			<img id="<?php echo __CLASS__; ?>-modal-img" />
		</div>
	</div>
</div>
<?php
		}

		$src = PLX_PLUGINS . __CLASS__ . '/' . __CLASS__ . '.js';
?>
<!--
$this->fields = <?php print_r($this->fields); ?>
-->
		<script type="text/javascript" src="<?php echo $src; ?>" data-plugin="<?php echo __CLASS__; ?>"></script>
<?php
	}

	/* -------------------- article.php ------------------------ */
	const ADMIN_ARTICLE_PARSE_DATA_CODE = <<< 'EOT'
	$#FIELD_NAME# = $result['#FIELD_NAME#'];
EOT;
	const ADMIN_ARTICLE_PREVIEW_CODE = <<< 'EOT'
	$art['#FIELD_NAME#'] = $_POST['#FIELD_NAME#'];
EOT;
	const ADMIN_ARTICLE_POSTDATA_CODE = <<< 'EOT'
	$#FIELD_NAME# = $_POST['#FIELD_NAME#'];
EOT;
	const PLXMOTOR_PARSE_ARTICLE_CODE = <<< 'EOT'
	$art['#FIELD_NAME#'] = (isset($iTags['#FIELD_NAME#'])) ? plxUtils::getValue($values[$iTags['#FIELD_NAME#'][0]]['value']) : '';
EOT;
	const PLXADMIN_EDIT_ARTICLE_XML_CODE = <<< 'EOT'
	$xml .= "\t<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck(trim($content['#FIELD_NAME#']))."]]></#FIELD_NAME#>\n";
EOT;

	public function AdminArticleParseData()			{ self::_process($this->artPlaces,		self::ADMIN_ARTICLE_PARSE_DATA_CODE); }
	public function AdminArticlePreview()			{ self::_process($this->artPlaces,		self::ADMIN_ARTICLE_PREVIEW_CODE); }
	public function AdminArticlePostData()			{ self::_process($this->artPlaces,		self::ADMIN_ARTICLE_POSTDATA_CODE); }
	public function plxMotorParseArticle()			{ self::_process($this->artPlaces,		self::PLXMOTOR_PARSE_ARTICLE_CODE); }
	public function plxAdminEditArticleXml()		{ self::_process($this->artPlaces,		self::PLXADMIN_EDIT_ARTICLE_XML_CODE); }

	/* ------------------ statique.php ------------------------- */
	const PLXMOTOR_GETSTATIQUES_CODE = <<< 'EOT'
	$f = '#FIELD_NAME#';
	$value = (array_key_exists($f, $iTags)) ? plxUtils::getValue($values[$iTags[$f][$i]]['value']) : '';
	$this->aStats[$number][$f] = $value;
EOT;
	const PLXADMIN_EDITSTATIQUE_CODE = <<< 'EOT'
		$this->aStats[$content['id']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
EOT;
	// A revoir
	const PLXADMIN_EDITSTATIQUES_UPDATE_CODE = <<< 'EOT'
	$this->aStats[$static_id]['#FIELD_NAME#'] = (isset($this->aStats[$static_id]['#FIELD_NAME#']) ? $this->aStats[$static_id]['#FIELD_NAME#'] : '');
EOT;
	const PLXADMIN_EDITSTATIQUES_XML_CODE = <<< 'EOT'
	$xml .= "<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck($static['#FIELD_NAME#'])."]]></#FIELD_NAME#>";
EOT;

	public function plxMotorGetStatiques()			{ self::_process($this->staticPlaces,	self::PLXMOTOR_GETSTATIQUES_CODE); }
	public function plxAdminEditStatique()			{ self::_process($this->staticPlaces,	self::PLXADMIN_EDITSTATIQUE_CODE); }
	public function plxAdminEditStatiquesUpdate()	{ self::_process($this->staticPlaces,	self::PLXADMIN_EDITSTATIQUES_UPDATE_CODE); }
	public function plxAdminEditStatiquesXml()		{ self::_process($this->staticPlaces,	self::PLXADMIN_EDITSTATIQUES_XML_CODE); }

	/* ------------------ categorie.php ------------------------- */
	const PLXMOTOR_GETCATEGORIES_CODE = <<< 'EOT'
	$f = '#FIELD_NAME#';
	$value = (array_key_exists($f, $iTags)) ? plxUtils::getValue($values[$iTags[$f][$i]]['value']) : '';
	$this->aCats[$number][$f] = $value;
EOT;
	const PLXADMIN_EDITCATEGORIE_CODE = <<< 'EOT'
		$this->aCats[$content['id']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
EOT;
	// A revoir
	const PLXADMIN_EDITCATEGORIES_UPDATE_CODE = <<< 'EOT'
	$this->aCats[$cat_id]['#FIELD_NAME#'] = (isset($this->aCats[$cat_id]['#FIELD_NAME#']) ? $this->->aCats[$cat_id]['#FIELD_NAME#'] : '');
EOT;

	const PLXADMIN_EDITCATEGORIES_XML_CODE = <<< 'EOT'
	$xml .= "<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck($cat['#FIELD_NAME#'])."]]></#FIELD_NAME#>";
EOT;

	public function plxMotorGetCategories()			{ self::_process($this->catPlaces,		self::PLXMOTOR_GETCATEGORIES_CODE); }
	public function plxAdminEditCategorie()			{ self::_process($this->catPlaces,		self::PLXADMIN_EDITCATEGORIE_CODE); }
	public function plxAdminEditCategoriesUpdate()	{ self::_process($this->catPlaces,		self::PLXADMIN_EDITCATEGORIES_UPDATE_CODE); }
	public function plxAdminEditCategoriesXml()		{ self::_process($this->catPlaces,		self::PLXADMIN_EDITCATEGORIES_XML_CODE); }

	/* ------------------ user.php ------------------------- */
	const PLXMOTOR_GETUSERS_CODE = <<< 'EOT'
	$f = '#FIELD_NAME#';
	$value = (array_key_exists($f, $iTags)) ? plxUtils::getValue($values[$iTags[$f][$i]]['value']) : '';
	$this->aUsers[$number][$f] = $value;
EOT;
	const PLXADMIN_EDITUSER_CODE = <<< 'EOT'
		$this->aUsers[$content['id']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
EOT;
	const PLXADMIN_EDITUSERS_UPDATE_CODE = <<< 'EOT'
EOT;
	const PLXADMIN_EDITUSERS_XML_CODE = <<< 'EOT'
	$xml .= "<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck($user['#FIELD_NAME#'])."]]></#FIELD_NAME#>";
EOT;

	public function plxMotorGetUsers()			{ self::_process($this->userPlaces,		self::PLXMOTOR_GETUSERS_CODE); }
	public function plxAdminEditUser()			{ self::_process($this->userPlaces,		self::PLXADMIN_EDITUSER_CODE); }
	public function plxAdminEditUsersUpdate()	{ self::_process($this->userPlaces,		self::PLXADMIN_EDITUSERS_UPDATE_CODE); }
	public function plxAdminEditUsersXml()		{ self::_process($this->userPlaces,		self::PLXADMIN_EDITUSERS_XML_CODE); }

	/* ------------------ Hooks for plxShow ---------------- */

	const PLXSHOW_LASTARTLIST_CONTENT_CODE = <<< 'EOT'
<?php
if(preg_match_all('#PATTERN#', $format, $matches)) {
	$places = array('#PLACES#');
	$replaces = array();
	foreach($matches[0] as $k) {
		if(in_array($k, $places)) {
			$replaces['#PREFIX#' . $k] = plxUtils::strCheck($art[$k]);
		}
	}
	$row = strtr($row, $replaces);
}
?>
EOT;
	public function plxShowLastArtListContent() {
		echo strtr(self::PLXSHOW_LASTARTLIST_CONTENT_CODE, array(
			'#PATTERN#'	=> '%#' . self::PREFIX . '_(?:' . implode('|', array_keys($this->fields)). ')%',
			'#PLACES#'	=> implode('\', \'', $this->artPlaces),
			'#PREFIX#'	=> '#' . self::PREFIX . '_'
		)) . PHP_EOL;
	}


	const PLXSHOW_LASTCATLIST_CONTENT_CODE  = <<< 'EOT'
<?php
if(preg_match_all('#PATTERN#', $format, $matches)) {
	$places = array('#PLACES#');
	$replaces = array();
	foreach($matches[0] as $k) {
		if(in_array($k, $places)) {
			$replaces['#PREFIX#' . $k] = plxUtils::strCheck($art[$k]);
		}
	}
	$name =strtr($name, $replaces);
}
?>
EOT;

	/*
	 * Unlike plxShow::LastArtList(), this hook is missing in plxShow::catList()
	 * */
	public function plxShowLastCatListContent() {
		echo strtr(self::PLXSHOW_LASTCATLIST_CONTENT_CODE, array(
			'#PATTERN#'	=> '%#' . self::PREFIX . '_(?:' . implode('|', array_keys($this->fields)). ')%',
			'#PLACES#'	=> implode('\', \'', $this->catPlaces),
			'#PREFIX#'	=> '#' . self::PREFIX . '_'
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
	public function kzChamPlus($params) {
		global $plxMotor;

		if (is_string($params)) { # affiche uniquement la valeur du champ
			list($name, $format, $empty_format) = array($params, false, false);
		} elseif(is_array($params)) {
			list($name, $format, $empty_format) = array_pad($params, 3, false);
		} else {
			echo "What do you want ?";
			return;
		}

		if(!array_key_exists($name, $this->fields)) {
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

	public function kzChamPlusList($param=false) {
		if(!is_string($param)) {
?>
<table class="<?php echo __CLASS__; ?>">
	<caption class="text-center"><?php echo __CLASS__; ?> plugin</caption>
	<thead>
		<tr>
<?php
			foreach(array_keys($this->paramsNames) as $name) { ?>
			<th><?php $this->lang(strtoupper('L_TITLE_'.$name)); ?></th>
<?php
			}
?>
	</tr></thead>
	<tbody>
<?php
			foreach(array_keys($this->indexFields) as $indice) {
?>
				<tr>
<?php
				foreach(array_keys($this->paramsNames) as $name) {
					$value = (array_key_exists($name, $this->indexFields[$indice])) ? $this->indexFields[$indice][$name] : false;
?>
					<td>
<?php
					switch($name) {
						case 'entry':	echo (!empty($value)) ? $this->fieldTypes[$value] : '&nbsp;'; break;
						case 'place':	echo (!empty($value)) ? $this->places[$value] : '&nbsp;';  break;
						default:		echo (!empty($value)) ? $value : '&nbsp;';
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
			$content = array();
			foreach($this->fields as $name=>$fiche) {
				$buf = array();
				foreach(array_keys($this->paramsNames) as $k) {
					if(array_key_exists($k, $fiche)) {
						$value = addslashes($fiche[$k]);
						$buf[] = "'$k' => '$value'";
					}
				}
				$content[] = "'$name' => array(". implode(',', $buf) .')';
			}
			echo '<?php $output = array(' .  implode(',', $content) . '); ?>';
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
			if(array_key_exists($nameField, $this->fields) and in_array($this->fields[$nameField]['entry'], $this->artPlaces)) {
				$value = $value = $plxMotor->plxRecord_arts->f(self::PREFIX . $nameField);
				if(empty($matches[2])) {
					echo $value;
				} elseif(!in_array($param, $this->artPlaces)) {
					echo PHP_EOL . "<strong>$param is not a field for articles</strong>" . PHP_EOL;
				} else {
					switch(strtoupper($matches[2])) {
						case 'L' :
							$label = $this->fields[$nameField]['label'];
							echo <<< EOT
<span class="label">$label</span> $value
EOT;
							break;
						case 'R' :
							echo "<?php \$$param = '$value'; ?>" . PHP_EOL;
							break;
					}
				}
			}
		}
	}
}
?>
