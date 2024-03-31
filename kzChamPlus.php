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
 * 2020-05-10 : Ajout type yes_no pour les champs
 * 2020-11-13 : Ajout du hook plxShowStaticListEnd
 * 2020-10-22 : Corrections linguistiques pour l'occitan (contribution de "Rubén")
 * 2020-06-15 : gestion de core/admin/profil.php. https://forum.pluxml.org/discussion/6604/plugin-kzchamplus-des-champs-en-plus-dans-articles-pages-categories-users-gestion-mots-cles/p2 by "sken"
 * 2020-06-22 : Fix bug pour categories.php. https://forum.pluxml.org/discussion/6604/plugin-kzchamplus-des-champs-en-plus-dans-articles-pages-categories-users-gestion-mots-cles/p2 by "flip-flip"
 * 2020-05-25 : Remplacer $this->urlRewrite(..) par $this->plxMotor->urlRewrite(..) et gestion champ vide dans plxShowLastArtListContent()
 * 2020-05-19 : Fix pour plxShowLastArtListContent(), kzChamPlus(). Diverses optimisations de code.
 * 2020-03-04 : Fix pour pages statiques place => static. Mis à jour help
 * 2020-02-22 : Fix against PluXml-5.8 (toggleDiv is missing) - Fix indice for new field in config.php
 * 2019-11-17 : le plugin chamPlus est renommé en kzChamPlus
 * la fonction self::chamPlusArticle() est remplacée par le hook plxShowLastArtListContent ()version PluXml >= 5.5)
 * 2019-11-11 : création de admin.php
 * 2019-11-04 : fixed in AdminArticleInitData()
 * 2017-01-02 : fixed in _get_fields_art_loop()
*/

class kzChamPlus extends plxPlugin {
	const DEBUG = false;
	const PREFIX = 'cps_';
	const PREFIX_IMPORT1 = 'champArt_';

	const HOOKS = array(
		'plxMotor'	=> array(
			'plxMotorParseArticle',
			'plxMotorGetStatiques',
			'plxMotorGetCategories',
			'plxMotorGetUsers',
		),
		'article'	=> array(
			'plxAdminEditArticleXml',
			'AdminArticlePreview',
			'AdminArticlePostData',
			'AdminArticleParseData',
			'AdminArticleTop',
			'AdminArticleContent',
			'AdminArticleSidebar',
		),
		'statique'	=> array(
			'plxAdminEditStatique',
			'plxAdminEditStatiquesUpdate',
			'plxAdminEditStatiquesXml',
			'AdminStaticTop',
			'AdminStatic',
		),
		'categorie'	=> array(
			'plxAdminEditCategorie',
			'plxAdminEditCategoriesUpdate',
			'plxAdminEditCategoriesXml',
			'AdminCategoryTop',
			'AdminCategory',
		),
        'categories'    => array(
            'plxAdminEditCategoriesUpdate',
            'plxAdminEditCategoriesXml'
        ),
		'user'		=> array(
			'plxAdminEditUser',
			'plxAdminEditUsersUpdate',
			'plxAdminEditUsersXml',
			'AdminUserTop',
			'AdminUser',
		),
		'profil'	=> array(
			'plxAdminEditProfil',
			'plxAdminEditUsersUpdate', // Déjà employé par script user.php
			'plxAdminEditUsersXml', // Déjà employé par script user.php
			'AdminProfilTop',
			'AdminProfil',
		),
		'plugin'	=> array(
			'plxAdminEditArticleXml',
		),
	);

	const OPEN_CODE = '<?php $plxAdmin->plxPlugins->aPlugins[\'' . __CLASS__ . '\']->adminEntry(';
	const CLOSE_CODE = ', #PLACE#); ?>';

	const START_CODE = '<?php /* ' . __CLASS__ . ' plugin */' . PHP_EOL;
	const END_CODE = PHP_EOL . '?>';

	const ADMIN_ARTICLE_CODE =	self::OPEN_CODE . '(!empty($result)) ? $result : false'		. self::CLOSE_CODE;
	const ADMIN_STATIC_CODE =	self::OPEN_CODE . '$plxAdmin->aStats[$id]'					. self::CLOSE_CODE;
	const ADMIN_CAT_CODE =		self::OPEN_CODE . '$plxAdmin->aCats[$id]'					. self::CLOSE_CODE;
	const ADMIN_USER_CODE =		self::OPEN_CODE . '$plxAdmin->aUsers[$id]'					. self::CLOSE_CODE;
	const ADMIN_PROFIL_CODE =	self::OPEN_CODE . '$plxAdmin->aUsers[$_SESSION[\'user\']]'	. self::CLOSE_CODE;

	const LIGNE			= 3;
	const BLOCK_TEXT	= 1;
	const MEDIA			= 2;
	const YES_NO		= 4;
	// pour champArt valeurs possibles pour $fieldTypes: ligne, bloc
	public $field_types = array(
		self::LIGNE			=> 'L_LINE',
		self::MEDIA			=> 'L_MEDIA',
		self::BLOCK_TEXT	=> 'L_NOTEPAD',
		self::YES_NO		=> 'L_YES_NO',
	);


	/* only for static page */
	const BOTTOM_STATIC = 1;
	const TOP_STATIC	= 5;
	/* where in the article page */
	const TOP_ART		= 2;
	const BOTTOM_ART	= 3;
	const SIDEBAR_ART	= 4;
	/* where in the categorie page */
	const TOP_CAT		= 6;
	const BOTTOM_CAT	= 7;
	/* where in the user page */
	const TOP_USER		= 8;
	const BOTTOM_USER	= 9;

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
	const PLACES = array(
		'article'	=> array(self::BOTTOM_ART, self::TOP_ART, self::SIDEBAR_ART),
		'statique'	=> array(self::BOTTOM_STATIC, self::TOP_STATIC),
		'categorie'	=> array(self::BOTTOM_CAT, self::TOP_CAT),
		'user'		=> array(self::BOTTOM_USER, self::TOP_USER),
	);
	const EDIT_NAME_VARIABLE_XML = array(
		'article'	=> 'content',
		'statique'	=> 'static',
		'categorie'	=> 'cat',
		'user'		=> 'user',
	);

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
			foreach($this->field_types as $k=>$v) {
				$this->field_types[$k] = $this->getLang($v);
			}
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
		foreach(self::HOOKS['plxMotor'] as $hook) {
			$this->addHook($hook, $hook);
		}

		if(defined('PLX_ADMIN')) {
			parent::setAdminProfil(PROFIL_ADMIN, PROFIL_MANAGER);
			parent::setAdminMenu($this->getLang('L_TITLE_MENU'), '', $this->getLang('HELP_MENU'));

			/* ******** hooks inside top.php ****************** */
			$this->addHook('AdminFootEndBody', 'AdminFootEndBody');

			if(array_key_exists($myScript, self::HOOKS)) { // article, statique, categorie, user
				foreach(self::HOOKS[$myScript] as $hook) {
					$this->addHook($hook, $hook);
				}
			}
		} else { // site
			/* ********** hooks inside class.plx.show.php ******** */
			if (defined('PLX_VERSION') and version_compare(PLX_VERSION, '5.5', '>=')) {
				$this->addHook('plxShowLastArtListContent', 'plxShowLastArtListContent');
				// $this->addHook('plxShowLastCatListContent', 'plxShowLastCatListContent');
			}
			$this->addHook('plxShowStaticListEnd', 'plxShowStaticListEnd');
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
			$pattern = '#^(' . implode('|', array_keys($this->paramsNames)). ')(\d+)$#';
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

	// Deprecated. See : newFieldBtn.addEventListener('click', function(event) {...} in javascript
	public function newIndice() {
		if(empty($this->indexFields)) { return 2; }
		$t = array_keys($this->indexFields);
		return ((!empty($t)) ? max($t) + 1 : 2);
	}

	public function adminArtDisplay($indice) {
		return in_array($this->indexFields[$indice]['place'], self::PLACES['article']);
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
					plxUtils::printSelect($field, $this->field_types, $value);
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
				case 'grid' :
					plxUtils::printInput($field, $value, 'text', '', false, '', '', 'pattern="(?:sml|med|lrg)-\d{1,2}(?: (?:sml|med|lrg)-\d{1,2})*"');
					break;
				case 'name' :
					plxUtils::printInput($field, $value, 'text', '', false, '', '', 'pattern="\w*"');
					break;
				default:
					plxUtils::printInput($field, $value, 'text', '');
			}
			if($name != 'place') {
				print(PHP_EOL); // Add eol after every plxUtils::printInput(..)
			}
?>
					</td>
<?php
		}
?>
				</tr>
<?php
	}


	public function printArea($name, $value='', $cols=false, $rows=false, $readonly=false, $class=false, $extras = false) {
		$attrs = array(
			'id="id_' . $name . '"',
			'name="' . $name . '"'
		);
		if(!empty($cols) and is_integer($cols)) { $attrs[] = 'cols="' . $cols . '"'; }
		if(!empty($rows) and is_integer($rows)) { $attrs[] = 'rows="' . $rows . '"'; }
		$classList = array();
		if($readonly === true) { $classList[] = 'readonly'; }
		if(!empty($class) and is_string($class) and strlen(trim($class)) > 0) { $classList[] = trim($class); }
		if(!empty($classList)) { $attrs[] = 'class="' . implode(' ', $classList) . '"'; }
		if(is_string($extras)) { $attrs[] = $extras; }
		echo '<textarea ' . implode(' ', $attrs) . '>' . $value . '</textarea>';
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
				<div class="grid <?= __CLASS__; ?>">
<?php
		foreach($entries as $key => $params) {
			$fieldName = self::PREFIX . $key;
			$caption = $params['label'];
			$value = (!empty($data) and array_key_exists($fieldName, $data)) ? addslashes($data[$fieldName]) : '';

			# default values
			$cols = 35; $rows = 8;
			$size = '';
			$className = 'full-width';

			if(in_array($place, self::PLACES['article'])) { // article
				$size = ($place == self::SIDEBAR_ART) ? '27-255' : '42-255';
				$className = ($place == self::SIDEBAR_ART) ? '' : 'full-width';
			} elseif(in_array($place, self::PLACES['statique'])) { // static page - no sidebar !
				$size = '50-255';
				$cols = 140; $rows = 30;
			} elseif(in_array($place, self::PLACES['categorie'])) {
				// nothing to do
			} elseif(in_array($place, self::PLACES['user'])) {
				// nothing to do
			} else {
				return; // Bye !!!
			}
			$grid = '';
			// $grid : A mettre à jour pour PluXml 6.0
			if(!empty($params['grid'])) {
				$grid = $params['grid'];
			} elseif($place == self::TOP_ART) {
				$grid .= ' med-7 lrg-8'; /* hack against PluXml */
			}
			$grid = preg_replace('#^col\b\s*#', '', $grid);
			$placeholder = (!empty($params['invite'])) ? $params['invite'] : '';
?>
					<div class="col<?= !empty($grid) ? ' ' . $grid : '' ?>">
<?php
			switch($params['entry']) {
				case self::LIGNE :
?>
						<label for="id_<?= $fieldName; ?>"><?= $caption ?>&nbsp;:</label>
						<?php plxUtils::printInput($fieldName, plxUtils::strCheck($value), 'text', $size, false, $className, $placeholder); echo PHP_EOL; ?>
<?php
					break;
				case self::BLOCK_TEXT :
					if(!$place != self::SIDEBAR_ART) { // for static 140,30 otherwise 35,8
						$extras = (!empty($placeholder)) ? 'placeholder="' . $placeholder . '"' : false;
						$style = ($value !='') ? '' : ' style="display:none;"';
?>
						<label for="id_<?= $fieldName; ?>"><?= $caption; ?>&nbsp;:&nbsp;<a href="javascript:void(0)" onclick="kzToggleDiv('toggle_<?= $fieldName; ?>', '<?= L_ARTICLE_CHAPO_DISPLAY ?>', '<?= L_ARTICLE_CHAPO_HIDE ?>')"><?= (empty($value)) ? L_ARTICLE_CHAPO_DISPLAY : L_ARTICLE_CHAPO_HIDE; ?></a></label>
						<div id="toggle_<?= $fieldName; ?>"<?= $style ?>>
						<?php self::printArea($fieldName, plxUtils::strCheck($value), $cols, $rows, false, 'full-width', $extras); echo PHP_EOL; ?>
						</div>
<?php
					}
					break;
				case self::MEDIA :
?>
						<label for="id_<?= $fieldName; ?>">
							<?= $caption; ?>&nbsp;:&nbsp;
							<a title="<?= L_THUMBNAIL_SELECTION ?>" id="toggler_<?= $fieldName; ?>" href="javascript:void(0)" onclick="mediasManager.openPopup('id_<?= $fieldName; ?>', true)" style="outline:none; text-decoration: none">+</a>
						</label>
						<?php plxUtils::printInput($fieldName,plxUtils::strCheck($value), 'text', $size, false, $className, $placeholder, 'onkeyup="refreshImg(this.value)"'); ?>
						<div id="id_<?= $fieldName; ?>_img" class="media">
<?php
						if(!empty($value) and preg_match('#\.(?:jpe?g|png|gif)$#', $value)) {
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
?>
<img src="<?= $src ?>" alt="<?= $alt ?>" class="<?= __CLASS__ ?>" title="<?= $value ?>"<?= $attr ?> />
<?php
							}
						}
?>
						</div>
<?php
					break;
				case self::YES_NO :
					$options = array(
						'0'	=> $this->getLang('L_NO'),
						'1'	=> $this->getLang('L_YES'),
					);
?>
						<label for="id_<?= $fieldName; ?>"><?= $caption ?>&nbsp;:</label>
						<?php plxUtils::printSelect($fieldName, $options, $value); echo PHP_EOL; ?>
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
	// Add fields in profi.php
	public function AdminProfilTop()		{ echo str_replace('#PLACE#', self::TOP_USER,		self::ADMIN_PROFIL_CODE); }
	public function AdminProfil()			{ echo str_replace('#PLACE#', self::BOTTOM_USER,	self::ADMIN_PROFIL_CODE); }

	private function _process($filter1, $code) {
		if(empty($this->fields) or !array_key_exists($filter1, self::PLACES)) { return; }

		$entries = array_filter($this->fields, function($value) use($filter1) {
			return (!empty($value['place']) and in_array($value['place'], self::PLACES[$filter1]));
		});
		if(empty($entries)) { return; }

		echo self::START_CODE;
		foreach(array_keys($entries) as $key) {
			$fieldName = self::PREFIX . $key;
			echo str_replace('#FIELD_NAME#', $fieldName, $code) . PHP_EOL;
		}
		echo self::END_CODE;
	}

	const PLX_ADMIN_EDIT_XML_CODE = <<< 'EOT'
$kzValue = trim($#NAME#['#FIELD_NAME#']);
if(defined('PLX_URL_RESSOURCES')) {
	$caption = plxUtils::cdataCheck($kzValue);
} else {
	$caption = (!empty($kzValue)) ? '<![CDATA[' . plxUtils::cdataCheck($kzValue) . ']]>' : '';
}
if(isset($xml)) {
   $xml .= "\t<#FIELD_NAME#>" . $caption . "</#FIELD_NAME#>" . PHP_EOL;
} else {
?>
	<#FIELD_NAME#><?= $caption ?></#FIELD_NAME#>
<?php
}
EOT;

	private function _plxAdminEditXml($filter1) {
		self::_process($filter1, str_replace('#NAME#', self::EDIT_NAME_VARIABLE_XML[$filter1], self::PLX_ADMIN_EDIT_XML_CODE));
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
	if(isset($doc)) {
		$children = $doc->xpath('/#FIELD_NAME#');
		$art['#FIELD_NAME#'] = !empty($children) ? (string) $children[0] : '';
	} else {
		# retro-compatibilité
		$art['#FIELD_NAME#'] = (isset($iTags['#FIELD_NAME#'])) ? plxUtils::getValue($values[$iTags['#FIELD_NAME#'][0]]['value']) : '';
	}
EOT;

	public function AdminArticleParseData()			{ self::_process('article', self::ADMIN_ARTICLE_PARSE_DATA_CODE); }
	public function AdminArticlePreview()			{ self::_process('article', self::ADMIN_ARTICLE_PREVIEW_CODE); }
	public function AdminArticlePostData()			{ self::_process('article', self::ADMIN_ARTICLE_POSTDATA_CODE); }
	public function plxMotorParseArticle()			{ self::_process('article', self::PLXMOTOR_PARSE_ARTICLE_CODE); }
	public function plxAdminEditArticleXml()		{ self::_plxAdminEditXml('article'); }

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

	public function plxMotorGetStatiques()			{ self::_process('statique', self::PLXMOTOR_GETSTATIQUES_CODE); }
	public function plxAdminEditStatique()			{ self::_process('statique', self::PLXADMIN_EDITSTATIQUE_CODE); }
	public function plxAdminEditStatiquesUpdate()	{ self::_process('statique', self::PLXADMIN_EDITSTATIQUES_UPDATE_CODE); }
	public function plxAdminEditStatiquesXml()		{ self::_plxAdminEditXml('statique'); }

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
	$this->aCats[$cat_id]['#FIELD_NAME#'] = isset($this->aCats[$cat_id]['#FIELD_NAME#']) ? $this->aCats[$cat_id]['#FIELD_NAME#'] : '';
EOT;

	public function plxMotorGetCategories()			{ self::_process('categorie', self::PLXMOTOR_GETCATEGORIES_CODE); }
	public function plxAdminEditCategorie()			{ self::_process('categorie', self::PLXADMIN_EDITCATEGORIE_CODE); }
	public function plxAdminEditCategoriesUpdate()	{ self::_process('categorie', self::PLXADMIN_EDITCATEGORIES_UPDATE_CODE); }
	public function plxAdminEditCategoriesXml()		{ self::_plxAdminEditXml('categorie'); }

	/* ------------------ user.php ------------------------- */
	const PLXMOTOR_GETUSERS_CODE = <<< 'EOT'
	if(isset($item)) {
		$value = !empty($item->#FIELD_NAME#) ? (string) $item->#FIELD_NAME# : '';
		$this->aUsers[$itemId]['#FIELD_NAME#'] = $value;
	} else {
		# Rétro-compatibilité
		$f = '#FIELD_NAME#';
		$value = (array_key_exists($f, $iTags)) ? plxUtils::getValue($values[$iTags[$f][$i]]['value']) : '';
		$this->aUsers[$number][$f] = $value;
	}
EOT;
	const PLXADMIN_EDITUSER_CODE = <<< 'EOT'
		$this->aUsers[$content['id']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
EOT;
	const PLXADMIN_EDITUSERS_UPDATE_CODE = <<< 'EOT'
EOT;

	public function plxMotorGetUsers()			{ self::_process('user', self::PLXMOTOR_GETUSERS_CODE); }
	public function plxAdminEditUser()			{ self::_process('user', self::PLXADMIN_EDITUSER_CODE); }
	public function plxAdminEditUsersUpdate()	{ self::_process('user', self::PLXADMIN_EDITUSERS_UPDATE_CODE); }
	public function plxAdminEditUsersXml()		{ self::_plxAdminEditXml('user'); }

	/* ------------------ profil.php ---------------------- */

	const PLXADMIN_EDITPROFIL_CODE = <<< 'EOT'
		$this->aUsers[$_SESSION['user']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
EOT;
	public function plxAdminEditProfil() { self::_process('user', self::PLXADMIN_EDITPROFIL_CODE); }

	/* ------------------ Hooks for plxShow ---------------- */

	public function plxShowLastArtListContent() {
		if(empty($this->fields)) { return; }

		$selected_fields = array_filter($this->fields,
			function($v) { return in_array($v['place'], self::PLACES['article']); }
		);

		if(empty($selected_fields)) { return; }

		$pattern = '%#(' . self::PREFIX . '(?:' . implode('|', array_keys($selected_fields)) . ')\b)%';
		$medias = array_keys(array_filter($selected_fields,
			function($v) { return $v['entry'] == self::MEDIA; }
		));

		echo self::START_CODE;
?>
if(preg_match_all('<?= $pattern ?>', $format, $matches)) {
	$replaces = array();
	$medias = <?= empty($medias) ? 'false' : 'array(\''. self::PREFIX . implode('\', \''. self::PREFIX, $medias) . '\')' ?>;
	foreach($matches[1] as $k0) {
		$k1 = '#' . $k0;
		if(array_key_exists($k1, $replaces)) { continue; }

		if(array_key_exists($k0, $art)) {
			$value = trim($art[$k0]);
			if(!empty($value) and !empty($medias) and in_array($k0, $medias)) {
				$value = $this->plxMotor->urlRewrite($value);
			}
		}

		$replaces[$k1] = $value;
	}
	$row = strtr($row, $replaces);
}
<?php
		echo self::END_CODE;
	}

	public function plxShowStaticListEnd() {
		$entries = array_filter($this->fields, function($value, $key) {
			return (!empty($value['place']) and in_array($value['place'], self::PLACES['statique']));
		}, ARRAY_FILTER_USE_BOTH);
		if(empty($entries)) { return; }

		echo self::START_CODE;
?>
	$kzEntries = array(
<?php
	foreach(array_keys($entries) as $e) {
?>
		'<?= self::PREFIX . $e ?>',
<?php
	}
?>
	);
	if($this->plxMotor->aStats) {
		$kzI = empty($extra) ? 0 : 1;
		$kzGroups = array(); // pour les sous-menus des groupes
		foreach($this->plxMotor->aStats as $statId=>$v) {
			if(!empty($v['active']) && in_array($v['menu'], array('oui', 1))) {
				$replaces = array();
				foreach($kzEntries as $x) {
					$replaces['#' . $x] = $v[$x];
				}

				if(empty($v['group'])) {
					$menus[$kzI][0] = strtr($menus[$kzI][0], $replaces);
					$kzI++;
				} else {
					if(!isset($kzGroups[$v['group']])) {
						$kzGroups[$v['group']] = 0;
					}
					$menus[$v['group']][$kzGroups[$v['group']]] = strtr($menus[$v['group']][$kzGroups[$v['group']]], $replaces);
					$kzGroups[$v['group']]++;
				}
			}
		}
	}
<?php
		echo self::END_CODE;
	}

	/*
	 * Unlike plxShow::LastArtList(), this hook is missing in plxShow::catList()
	 * */
	public function plxShowLastCatListContent() {
		$pattern = '%#(' . self::PREFIX . '(?:' . implode('|', array_keys(
			array_filter(
				$this->fields,
				function($v) { return in_array($v['place'], self::PLACES['categorie']); }
			)
		)) . ')\b)%';

		echo self::START_CODE;
?>
if(preg_match_all('<?= $pattern ?>', $format, $matches)) {
	$replaces = array();
	foreach($matches[1] as $k0) {
		$k1 = '#' . $k0;
		if(array_key_exists($k1, $replaces)) { continue; }

		$replaces[$k1] = array_key_exists($k0, $v) ? plxUtils::strCheck($v[$k0]) : '';
	}
	$name =strtr($name, $replaces);
}
<?php
		echo self::END_CODE;
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
	 *
	 * Si un 4ème paramètre existe, c'est l'identifiant d'une catégorie, d'un utilisateur ou d'une page statique
	 * ******************************************************************************* */
	public function kzChamPlus($params) {
		global $plxMotor;

		if (is_string($params)) { # affiche uniquement la valeur du champ
			list($name, $format, $empty_format, $extra) = array($params, false, false, false);
		} elseif(is_array($params)) {
			list($name, $format, $empty_format, $extra) = array_pad($params, 4, false);
		} else {
			if($format === true) {
				return false;
			} else {
				echo 'What do you want ?';
				return;
			}
		}

		if(!array_key_exists($name, $this->fields)) {
			if($format === true) {
				return false;
			} else {
				echo $name . ' : ' . $this->getLang('L_UNKNOWN_FIELD');
				return;
			}
		}

		$place = $this->fields[$name]['place'];
		if($extra === false) {
			if((
				$plxMotor->mode == 'static' xor
				in_array($place, array(self::TOP_STATIC, self::BOTTOM_STATIC))
			) or (
				in_array($place, array(self::TOP_CAT, self::BOTTOM_CAT)) and
				$plxMotor->mode != 'categorie'
			)) {
				if($format === true) {
					return false;
				} else {
					echo $name . ' : ' . $this->getLang('L_FORBIDDEN_FIELD');
					return;
				}
			}
		}

		if($extra !== false and !is_numeric($extra)) {
			if($format === true) {
				return false;
			} else {
				echo $extra . ' : ' . $this->getLang('L_NUMERIC_FIELD_REQUIRED');
				return;
			}
		}

		$nameField = self::PREFIX . $name;
		// Only useful for categorie, static page. See for user
		$id = ($extra !== false) ? str_pad($extra, 3, '0', STR_PAD_LEFT) : $plxMotor->cible;
		switch($place) {
			case self::BOTTOM_ART :
			case self::SIDEBAR_ART :
			case self::TOP_ART :
				$value = $plxMotor->plxRecord_arts->f($nameField);
				break;
			case self::TOP_CAT :
			case self::BOTTOM_CAT :
				$value = plxUtils::strCheck($plxMotor->aCats[$id][$nameField]);
				break;
			case self::TOP_STATIC :
			case self::BOTTOM_STATIC :
				$value = plxUtils::strCheck($plxMotor->aStats[$id][$nameField]);
				break;
			case self::BOTTOM_USER :
			case self::TOP_USER :
				$authorId = ($extra !== false) ? str_pad($extra, 3, '0', STR_PAD_LEFT) : $plxMotor->plxRecord_arts->f('author');
				$value = plxUtils::strCheck($plxMotor->aUsers[$authorId][$nameField]);
				break;
		}

		# traitement
		if ($format === true)
			// pas d'affichage, on retourne simplement la valeur
			return $value;
		elseif($format === false) {
			if(!empty(trim($value))) {
				// Pas de chaine de format, on imprime la valeur du champ
				if (!$this->isMedia($name) or !empty($this->getParam('no_integration'))) {
					// on affiche uniquement la valeur
					echo $value;
				} else {  // C'est un média est une image
					$title = basename($value);

					if (preg_match('#\.(?:jpe?g|png|svg|gif|webp)$#', $value)) { // C'est une image
						if(file_exists(PLX_ROOT . $value)) {
							$imagesize = getimagesize(PLX_ROOT . $value);
							$attrs = ' ' . $imagesize[3];
						} else {
							$attrs = '';
						}
?>
<img src="<?= $value ?>" alt="<?= $title?>" <?= $attrs ?> />
<?php
					} else {  // Ce n'est pas une image
?>
<a href="<?= $value ?>" target="_blank" download><?= $title ?></a>
<?php
					}
				}
			}
		} else { // format défini
			$fmt = (!empty($value)) ? $format : $empty_format;
			if (is_string($fmt)) {
				$group = array_key_exists('group', $this->fields[$name]) ? $this->fields[$name]['group'] : '';
				echo strtr($fmt, array(
					'#name'		=> $name,
					'#value'	=> $value,
					'#label'	=> $this->fields[$name]['label'],
					'#group'	=> $group,
					'#groupe'	=> $group,
					'#place'	=> $this->fields[$name]['place']
				));
			} elseif($empty_format !== false) {
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
						case 'entry':	echo (!empty($value)) ? $this->field_types[$value] : '&nbsp;'; break;
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
			if(array_key_exists($nameField, $this->fields) and in_array($this->fields[$nameField]['entry'], self::PLACES['article'])) {
				$value = $value = $plxMotor->plxRecord_arts->f(self::PREFIX . $nameField);
				if(empty($matches[2])) {
					echo $value;
				} elseif(!in_array($param, self::PLACES['article'])) {
					echo PHP_EOL . "<strong>$param is not a field for articles</strong>" . PHP_EOL;
				} else {
					switch(strtoupper($matches[2])) {
						case 'L' :
							$label = $this->fields[$nameField]['label'];
?>
<span class="label"><?= $label ?></span><?= $value ?>
<?php
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
