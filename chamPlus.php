<?php
/*
 * plugin ChamPlus
 *
 * ré-écriture complète de champArt.
 * Nécessite Pluxml 5.4, HTML5, PHP 5.6
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
 * 2019-11-11 : création de admin.php
 * 2019-11-04 : fixed in AdminArticleInitData()
 * 2017-01-02 : fixed in _get_fields_art_loop()
*/

class chamPlus extends plxPlugin {
	const PREFIX = 'cps_';
	// pour les articles utilisant le plugin champArt, décommenter la ligne ci-dessous et supprimer la ligne au dessus
	// const PREFIX = 'champArt_';

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

	public $paramsNames = array(
		'name' =>	FILTER_SANITIZE_STRING, // nom du champ
		'label' =>	FILTER_SANITIZE_STRING, // libellé du champ
		'entry' =>	FILTER_VALIDATE_INT, // type de saisie : ligne, bloc-texte, photo (codé en numérique)
		'group' =>	FILTER_SANITIZE_STRING,
		'place' =>	FILTER_VALIDATE_INT // emplacement pour la saisie (codé en numérique)
	);

	public $options = array('no_integration'); # extended for Pluxml version <= 5.4

	public $order = 0;

	public function __construct($default_lang) {
		parent::__construct($default_lang);

		self::_getFields();
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
			} else {
				$this->addHook('plxShowLastArtList', 'plxShowLastArtList');
				$this->options[] = 'lastartlist';
			}
			/* ********** new hooks for this plugin ********* */
			// Hook du plugin à utiliser sur le site dans un thème
			$this->addHook('chamPlus', 'chamPlus');
			// Affiche les champs d'un article selon le format indiqué
			$this->addHook('chamPlusArticle', 'chamPlusArticle');
			// renvoie tous les champs sous forme de tableau
			$this->addHook('chamPlusList', 'chamPlusList');

			/* ******* compatibilté avec le plugin champArt  ****** */
			$this->addHook('champArt', 'champArt');
		}
	}

	private function _getFields() {
		$fields = array();
		$params = $this->getParams();
		if(!empty($params)) {
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
				$fields[$this->getParam('name' . $indice)] = $entry;
			}
		}
		$this->fields = $fields;
	}

	private function _save_code($content) {
		$filename = tempnam(sys_get_temp_dir(), 'pluxml-');
		file_put_contents($filename, $content);
	}

	// pour la sauvegarde des champs dans config.php
	public function isNumeric($name) {
		return in_array($name, array('place', 'entry'));
	}

	// retourne les indices des champs
	public function indices($config=false) {
		$params = $this->getParams();
		if(!empty($params)) {
			$names = array_filter(
				array_keys($params),
				function($k) { return (strpos($k, 'name') === 0); }
			);
			return array_map(function($v) { return substr($v, strlen('name')); }, $names);
		} elseif($config) {
			return array(1);
		} else {
			return array();
		}
	}

	// the field is only for static pages
	private function isStatic($indice) {
		return in_array($this->getParam('place'.$indice), array(self::TOP_STATIC, self::BOTTOM_STATIC));
	}

	private function isTextarea($indice) {
		return (!$this->isStatic($indice) and $this->getParam('entry'.$indice) == self::BLOCK_TEXT);
	}

	private function isMediaArt($indice) {
		return (!$this->isStatic($indice) and $this->getParam('entry' . $indice) == self::MEDIA);
	}

	private function isMedia($fieldName) {
		$result = false;
		foreach($this->indices() as $idx) {
			if ($this->getParam('name'.$idx) == $fieldName) {
				$result = ($this->getParam('entry'.$idx) == self::MEDIA);
				break;
			}
		}
		return $result;
	}

	public function newIndice() {
		$t = $this->indices();
		return ((!empty($t)) ? max($t) + 1 : 1);
	}

	public function adminArtDisplay($indice) {
		return !in_array($this->getParam('place' . $indice), array(self::TOP_STATIC, self::BOTTOM_STATIC));
	}

	public function printFieldConfig($indice) {
?>
				<tr>
<?php
		foreach (array_keys($this->paramsNames) as $name) {
			$value = (empty($new)) ? plxUtils::strCheck($this->getParam($name . $indice)) : '';
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

	/* -------------- Pour côté site ------------------ */

	private function getMediasArt() {
		$result = array();
		$indices = $this->indices();
		foreach ($indices as $i) {
			if ($this->isMediaArt($i)) {
				$result[] = $this->getParam('name'.$i);
			}
		}
		return $result;
	}

	/* *********************************************************** *
	 * le nom de variables $pls_medias et $cps_matches est commun  *
	 * aux fonctions _get_pls_medias(), _get_fields_art_loop,      *
	 *    plxShowLastArtListContent() et plxShowLastArtList()      *
	 *                    Ne pas modifier !!                       *
	 * *********************************************************** */

	private function _get_pls_medias() {
		# on collecte le nom des champs de type média pour les articles
		if ($this->getParam('no_integration') > 0) {
			$result = <<< 'CODE_NO'
			$pls_medias = false;

CODE_NO;
		} else {
			$pls_medias = $this->getMediasArt();
			$temp = implode("', '", $pls_medias);
			$result = <<< CODE_YES
			\$pls_medias = array('{$temp}');

CODE_YES;
		}
		return $result;
	}

	private function _get_fields_art_loop() {
		$code = <<< 'CODE_END'
			foreach ($cps_matches[0] as $k) {
				$value = $art[substr($k, 1)];
				if (! empty($pls_medias) and in_array(substr($k, 5), $pls_medias) and ($sizes = getimagesize(PLX_ROOT.$value))) {
					# we have an image
					$title = ucfirst(preg_replace($pattern_title, '$1', $value));
					$replaces[] = '<img src="'.$this->plxMotor->urlRewrite($value).'" '.$sizes[3].' alt="'.substr($k, 5).'" class="test" title="'.$title.'" />';
				} else {
					$replaces[] = htmlspecialchars($value, ENT_QUOTES, PLX_CHARSET);
				}
			}

CODE_END;
		return $code;
	}

	/* ========================== HOOKS ========================= */
	public function AdminFootEndBody() {
		$src = PLX_PLUGINS . __CLASS__ . '/' . __CLASS__ . '.js';
?>
		<script type="text/javascript" src="<?php echo $src; ?>" data-plugin="<?php echo __CLASS__; ?>"></script>
<!--
<?php print_r($this->fields); ?>
-->
<?php
	}

	/* -------------------- article.php ------------------------ */
	const ADMIN_ARTICLE_PARSE_DATA_CODE = <<< 'ADMIN_ARTICLE_PARSE_DATA_CODE'
	$#FIELD_NAME# = $result['#FIELD_NAME#'];
ADMIN_ARTICLE_PARSE_DATA_CODE;
	public function AdminArticleParseData() {
		echo '<?php' . PHP_EOL;
		foreach($this->indices() as $key) {
			if (! $this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::ADMIN_ARTICLE_PARSE_DATA_CODE) . PHP_EOL;
				}
		}
		echo '?>' . PHP_EOL;
	}

	const ADMIN_ARTICLE_PREVIEW_CODE = <<< 'ADMIN_ARTICLE_PREVIEW_CODE'
	$art['#FIELD_NAME#'] = $_POST['#FIELD_NAME#'];
ADMIN_ARTICLE_PREVIEW_CODE;
	public function AdminArticlePreview() {
		echo '<?php' . PHP_EOL;
		foreach($this->indices() as $key) {
			if(!$this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::ADMIN_ARTICLE_PREVIEW_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	const ADMIN_ARTICLE_POSTDATA_CODE = <<< 'ADMIN_ARTICLE_POSTDATA_CODE'
	$#FIELD_NAME# = $_POST['#FIELD_NAME#'];
ADMIN_ARTICLE_POSTDATA_CODE;
	public function AdminArticlePostData() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if (! $this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::ADMIN_ARTICLE_POSTDATA_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	public function adminEntry($data, $place=self::BOTTOM_ART) {
		foreach ($this->indices() as $key) {
			if($this->getParam('place' . $key) != $place) { continue; }

			$fieldName = self::PREFIX . $this->getParam('name' . $key);
			$caption = ucfirst($this->getParam('label' . $key));
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
			switch($this->getParam('entry' . $key)) {
				case self::LIGNE :
?>
						<label for="id_title"><?php echo $caption ?>&nbsp;:</label>
						<?php plxUtils::printInput($fieldName, plxUtils::strCheck($value), 'text', $size, false, $className); echo "\n"; ?>
<?php
					break;
				case self::BLOCK_TEXT :
					if(!$place != self::SIDEBAR_ART) { // static 140,30 au lieu de 35,8
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

	// ajoute les champs supplémentaires dans l'édition de l'article dans article.php
	public function AdminArticleTop()		{ echo str_replace('#PLACE#', self::TOP_ART,		self::ADMIN_ARTICLE_CODE); }
	public function AdminArticleContent()	{ echo str_replace('#PLACE#', self::BOTTOM_ART,		self::ADMIN_ARTICLE_CODE); }
	public function AdminArticleSidebar()	{ echo str_replace('#PLACE#', self::SIDEBAR_ART,	self::ADMIN_ARTICLE_CODE); }

	// ajoute des champs supplémentaires dans l'édition de la page statique dans statique.php
	public function AdminStaticTop() 		{ echo str_replace('#PLACE#', self::TOP_STATIC,		self::ADMIN_STATIC_CODE); }
	public function AdminStatic()			{ echo str_replace('#PLACE#', self::BOTTOM_STATIC,	self::ADMIN_STATIC_CODE); }

	// Load the fields of article from XML file
	const PLXMOTOR_PARSE_ARTICLE_CODE = <<< 'PLXMOTOR_PARSE_ARTICLE_CODE'
	$art['#FIELD_NAME#'] = (isset($iTags['#FIELD_NAME#'])) ? plxUtils::getValue($values[$iTags['#FIELD_NAME#'][0]]['value']) : '';
PLXMOTOR_PARSE_ARTICLE_CODE;
	public function plxMotorParseArticle() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if (!$this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::PLXMOTOR_PARSE_ARTICLE_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	// load data from statiques.xml in class.plx.motor
	const PLXMOTOR_GETSTATIQUES_CODE = <<< 'PLXMOTOR_GETSTATIQUES_CODE'
	$value = (array_key_exists($f, $iTags)) ? plxUtils::getValue($values[$iTags[$f][$i]]['value']) : '';
	$this->aStats[$number][$f] = $value;
PLXMOTOR_GETSTATIQUES_CODE;
	public function plxMotorGetStatiques() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if ($this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo
					'$f = \'' . $fieldName . '\';' . PHP_EOL .
					self::PLXMOTOR_GETSTATIQUES_CODE . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	// Save the  fields of article to XML file
	const PLXADMIN_EDIT_ARTICLE_XML_CODE = <<< 'PLXADMIN_EDIT_ARTICLE_XML_CODE'
	$xml .= "\t<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck(trim($content['#FIELD_NAME#']))."]]></#FIELD_NAME#>\n";
PLXADMIN_EDIT_ARTICLE_XML_CODE;
	public function plxAdminEditArticleXml() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if (! $this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDIT_ARTICLE_XML_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	const PLXADMIN_EDITSTATIQUE_CODE = <<< 'PLXADMIN_EDITSTATIQUE_CODE'
		$this->aStats[\$content['id']]['#FIELD_NAME#'] = $content['#FIELD_NAME#'];
PLXADMIN_EDITSTATIQUE_CODE;
	public function plxAdminEditStatique() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if ($this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDITSTATIQUE_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	const PLXADMIN_EDITSTATIQUES_UPDATE_CODE = <<< 'PLXADMIN_EDITSTATIQUES_UPDATE_CODE'
	$this->aStats[$static_id]['#FIELD_NAME#'] = (isset($this->aStats[$static_id]['#FIELD_NAME#']) ? $this->aStats[$static_id]['#FIELD_NAME#'] : '');
PLXADMIN_EDITSTATIQUES_UPDATE_CODE;
	public function plxAdminEditStatiquesUpdate() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if ($this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDITSTATIQUES_UPDATE_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	const PLXADMIN_EDITSTATIQUES_XML_CODE = <<< 'PLXADMIN_EDITSTATIQUES_XML_CODE'
	$xml .= "<#FIELD_NAME#><![CDATA[".plxUtils::cdataCheck($static['#FIELD_NAME#'])."]]></#FIELD_NAME#>";
PLXADMIN_EDITSTATIQUES_XML_CODE;
	public function plxAdminEditStatiquesXml() {
		echo '<?php' . PHP_EOL;
		foreach ($this->indices() as $key) {
			if ($this->isStatic($key)) {
				$fieldName = self::PREFIX.$this->getParam('name'.$key);
				echo str_replace('#FIELD_NAME#', $fieldName, self::PLXADMIN_EDITSTATIQUES_XML_CODE) . PHP_EOL;
			}
		}
		echo '?>' . PHP_EOL;
	}

	/* *************************************** */
	# for Pluxml version >= 5.5
	public function plxShowLastArtListContent() {

		$code = $this->_get_pls_medias();
		# Utilisation de Nowdoc. Requiert PHP >= 5.3
		$code .= <<< 'CODE_START'
		if (preg_match_all('/(#cps_[a-z]\w*)/', $format, $cps_matches) > 0) {
			$replaces = array();
			$pattern_title = '#^.*/ ([^\./]+)(?:\.tb)*\.(?:jpg|jpeg|png|gif)$#';

CODE_START;
		$code .= $this->_get_fields_art_loop();
		$code .= <<< 'CODE_END'
			$row = str_replace($cps_matches[0], $replaces, $row);
		}

CODE_END;

	echo '<?php '.$code.' ?>';
	}

	# for Pluxml version < 5.5
	public function plxShowLastArtList() {

		if ($this->getParam('lastartlist') > 0) {
			$code = $this->_get_pls_medias();
			$code .= <<< 'CODE_START'
		# Génération de notre motif
		if(empty($cat_id))
			$motif = '/^[0-9]{4}.(?:[0-9]|home|,)*(?:'.$this->plxMotor->activeCats.'|home)(?:[0-9]|home|,)*.[0-9]{3}.[0-9]{12}.[a-z0-9-]+.xml$/';
		else
			$motif = '/^[0-9]{4}.((?:[0-9]|home|,)*(?:'.str_pad($cat_id,3,'0',STR_PAD_LEFT).')(?:[0-9]|home|,)*).[0-9]{3}.[0-9]{12}.[a-z0-9-]+.xml$/';

		# Nouvel objet plxGlob et récupération des fichiers
		$plxGlob_arts = clone $this->plxMotor->plxGlob_arts;
		if($aFiles = $plxGlob_arts->query($motif,'art',$sort,0,$max,'before')) {
			$n1 = preg_match_all('/(#art_(?:title|url|id|status|author|date|hour|nbcoms)|#cat_list)/', $format, $matches1);
			// traiter #art_chapo(..), #art_content(..)
			$n2 = preg_match_all('/(#art_(?:chapo|content))(?:\((\d+)\))?/', $format, $matches2);
			// traiter champs supplémentaires #cps_...
			$n3 = preg_match_all('/(#cps_[a-z][a-z,0-9_]*)/', $format, $cps_matches);
			foreach($aFiles as $v) {
				# On parcourt tous les fichiers
				$art = $this->plxMotor->parseArticle(PLX_ROOT.$this->plxMotor->aConf['racine_articles'].$v);
				$num = intval($art['numero']);
				$replaces = array();
				if ($n1 > 0) {
					$patterns = $matches1[0];
					foreach ($matches1[1] as $k) {
						switch ($k) {
							case '#art_title' :
								$replaces[] = plxUtils::strCheck($art['title']);
								break;
							case '#art_url':
								$replaces[] = $this->plxMotor->urlRewrite('?article'.$num.'/'.$art['url']);
								break;
							case '#art_id':
								$replaces[] = $num;
								break;
							case '#art_status' :
								$replaces[] = (($this->plxMotor->mode == 'article') and ($num == $this->plxMotor->cible)) ? 'active' : 'noactive';
								break;
							case '#art_author' :
								$author = plxUtils::getValue($this->plxMotor->aUsers[$art['author']]['name']);
								$replaces[] = plxUtils::strCheck($author);
								break;
							case '#art_date' :
								$replaces[] = plxDate::formatDate($art['date'],'#num_day/#num_month/#num_year(4)');
								break;
							case '#art_hour' :
								$replaces[] = plxDate::formatDate($art['date'],'#hour:#minute');
								break;
							case '#art_nbcoms' :
								$replaces[] = $art['nb_com'];
								break;
							case '#cat_list' :
								$catList = array();
								$catIds = explode(',', $art['categorie']);
								foreach ($catIds as $idx => $catId) {
									if(isset($this->plxMotor->aCats[$catId])) { # La catégorie existe
										$catName = plxUtils::strCheck($this->plxMotor->aCats[$catId]['name']);
										$catUrl = $this->plxMotor->aCats[$catId]['url'];
										$catList[] = '<a title="'.$catName.'" href="'.$this->plxMotor->urlRewrite('?categorie'.intval($catId).'/'.$catUrl).'">'.$catName.'</a>';
									} else {
										$catList[] = L_UNCLASSIFIED;
									}
								}
								$replaces[] = implode(', ',$catList);
								break;
						}
					}
				} else
					$patterns = array();
				if ($n2 > 0) {
					# #artchapo, #art_content à longueur variable
					$patterns = array_merge($patterns, $matches2[0]);
					for ($i=0; $i<count($matches2[1]); $i++) {
						$strLength = (empty($matches2[2][$i])) ? 100 : intval($matches2[2][$i]);
						$f = substr($matches2[1][$i], 5);
						$replaces[] = plxUtils::truncate($art[$f],$strLength,$ending,true,true);
					}
				}
				if ($n3 > 0) {
					# champs supplémentaires
					$patterns = array_merge($patterns, $cps_matches[0]);
CODE_START;
		$code .= $this->_get_fields_art_loop();
		$code .= <<< 'CODE_END'
				}
				echo str_replace($patterns, $replaces, $format);
			}
		}
return true;

CODE_END;
		} else
			$code = 'return false;';
		/* $this->_save_code($code); // pour débogage */
		echo '<?php ' .$code.' ?>';
	}

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

		if (is_string($params)) {
			# affiche uniquement la valeur du champ
			$name = $params;
			$format = false;
			$empty_format = false;
		} else
			list($name, $format, $empty_format) = array_pad($params, 3, false);
		$nameField = self::PREFIX.$name;
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
		else if ($format === false) {
			// Pas de chaine de format, on imprime la valeur du champ
			if (($this->getParam('no_integration') > 0) or (! $this->isMedia($name))) {
				// on affiche uniquement la valeur
				echo $value;
			} else {
				if (preg_match('/\.(?:jpg|jpeg|gif|png)$/', $value)) {
					// le média est une image
					$imagesize = getimagesize(PLX_ROOT.$value);
					$attrs = $imagesize[3];
					$title = basename($value);
					echo <<< EOT
<img src="$value" alt="$value" title="$title" $attrs />
EOT;
				} else {
					$label = basename($value);
					echo <<< EOT
<a href="$value" target="_blank">$label</a>
EOT;
				}
			}
		}
		else {
			$fmt = (!empty($value)) ? $format : $empty_format;
			if (is_string($fmt)) {
				$label = '';
				$group = '';
				foreach($this->indices() as $idx) {
					if ($this->getParam('name'.$idx) == $name) {
						$label = $this->getParam('label'.$idx);
						$group = $this->getParam('group'.$idx);
						$type1 = $this->fieldTypes[$this->getParam('entry'.$idx)];
						break;
					}
				}
				$patterns = array('#name#', '#value#', '#label#', '#group#', '#type#');
				$replaces = array($name, $value, $label, $group, $type1);
				echo str_replace($patterns, $replaces, $fmt);
			}
		}

		return false;
	}

	/* **********************************************************
	 * affiche les champs de l'article en fonction de $format
	 * mime lastArtShow dans home.php, categorie.php, tags.php
	 ************************************************************ */
	public function chamPlusArticle($format='Précisez le format d\'affichage pour chaque article') {
		global $plxShow;

		if (is_string($format)) {
			$n1 = preg_match_all('/(#art_(?:title|url|id|author|author_mail|author_infos|date_time|date|hour|nbcoms)|#cat_list|#tag_list)/', $format, $matches1);
			// traiter #art_chapo(..), #art_content(..)
			$n2 = preg_match_all('/#art_(chapo|content)(?:\((\d+)\))?/', $format, $matches2);
			// traiter champs supplémentaires #cps_...
			$n3 = preg_match_all('/#(cps_[a-z][a-z,0-9_]*)/', $format, $matches3);
			// affichage de la date de publication au format demandé
			$n4 = preg_match_all('/(#art_date)(?:\(([a-z,-\/]+)\))/', $format, $matches4);

			# On prépare l'affichage de l'article
			$num = intval($plxShow->plxMotor->plxRecord_arts->f('numero'));
			$author = $plxShow->plxMotor->aUsers[$plxShow->plxMotor->plxRecord_arts->f('author')];
			$date_pub = $plxShow->plxMotor->plxRecord_arts->f('date');
			$replaces = array();
			if ($n1 > 0) {
				$patterns = $matches1[0];
				foreach ($matches1[1] as $k) {
					switch ($k) {
						case '#art_title' : // titre de l'article
							$replaces[] = plxUtils::strCheck($plxShow->plxMotor->plxRecord_arts->f('title'));
							break;
						case '#art_url': // url de l'article
							$replaces[] = $plxShow->plxMotor->urlRewrite('?article'.$num.'/'.$plxShow->plxMotor->plxRecord_arts->f('url'));
							break;
						case '#art_id': // id de l'article
							$replaces[] = $num;
							break;
						case '#art_author' : // auteur de l'article
							$replaces[] = plxUtils::strCheck($author['name']);
							break;
						case '#art_author_mail' : // email de l'auteur de l'article
							$replaces[] = plxUtils::strCheck($author['email']);
							break;
						case '#art_author_infos' : // infos sur l'auteur de l'article
							$replaces[] = plxUtils::strCheck($author['infos']);
							break;
						case '#art_date' : // date de publication de l'article au format court (jj/mm/aaaa)
							$replaces[] = plxDate::formatDate($date_pub, '#num_day #month #num_year(4)');
							break;
						case '#art_hour' : // heure de publication de l'article au format court (hh:mm)
							$replaces[] = plxDate::formatDate($date_pub,'#hour:#minute');
							break;
						case '#art_date_time' : // date de publication de l'article pour la balise time (aaaa/mm/jj hh/mm)
							$replaces[] = preg_replace('#^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})$#', '\1-\2-\3 \4:\5', $date_pub);
							break;
						case '#art_nbcoms' : // nombre de commentaires pour chaque article
							if ($plxShow->plxMotor->aConf['allow_com'] and $plxShow->plxMotor->plxRecord_arts->f('allow_com')) {
								$nbcoms = intval($plxShow->plxMotor->plxRecord_arts->f('nb_com'));
								if ($nbcoms > 0) {
									if ($nbcoms > 1)
										$result = '1 '.L_COMMENT;
									else
										$result = $nbcoms.' '.L_COMMENTS;
								} else
									$result = L_NO_COMMENT;
							} else
								$result = '';
							$replaces[] = $result;
							break;
						case '#cat_list' : //catégories auxquelles appartient l'article sous forme de liens
							$catIds = $plxShow->artActiveCatIds();
							$result = array();
							foreach ($catIds as $catId)
								if ($catId != 'home') {
									$label = plxUtils::strCheck($plxShow->plxMotor->aCats[$catId]['name']);
									$url = $plxShow->plxMotor->aCats[ $catId ]['url'];
									$href = $plxShow->plxMotor->urlRewrite('?categorie'.intval($catId).'/'.$url);
									$status = ($plxShow->plxMotor->mode == 'categorie' AND $plxShow->plxMotor->cible==$t) ? 'active' : 'noactive';
									$result[] = <<< CAT_LIST
<a href="$href" class="$status">$label</a>
CAT_LIST;
								}
							$replaces[] = implode(' ', $result);
							break;
						case '#tag_list' : // catégories auxquelles appartient l'article sous forme de liens
							$temp = $plxShow->plxMotor->plxRecord_arts->f('tags');
							if (! empty($temp)) {
								$tags = array_map('trim', explode(',', $temp));
								$result = array();
								foreach ($tags as $tag) {
									$label = plxUtils::strCheck($tag);
									$t = plxUtils::title2url($tag);
									$href = $plxShow->plxMotor->urlRewrite('?tag/'.$t);
									$status = ($plxShow->plxMotor->mode == 'tags' AND $plxShow->plxMotor->cible==$t) ? 'active' : 'noactive';
									$result[] = <<< TAG_LIST
<a href="$href" class="$status">$label</a>
TAG_LIST;
								}
								$replaces[] = implode(' ', $result);
							} else
								$replaces[] = '';
							break;
					}
				}
			} else
				$patterns = array();
			if ($n2 > 0) { // #art_chapo, #art_content
				$patterns = array_merge($patterns, $matches2[0]);
				for ($i=0; $i<count($matches2[1]); $i++) { // affiche un extrait du chapô ou du contenu de l'article
					$strLength = (empty($matches2[2][$i])) ? 1000 : intval($matches2[2][$i]);
					$f = $matches2[1][$i];
					$value = $plxShow->plxMotor->plxRecord_arts->f($f);
					if (empty($value) and ($f == 'chapo') and ! in_array('content', $matches[2]))
						# chapo est vide et on ne demande d'afficher le content
						$value = $plxShow->plxMotor->plxRecord_arts->f('content');
					$replaces[] = plxUtils::truncate($value, $strLength);
				}
			}
			if ($n3 > 0) { // champs supplémentaires
				$patterns = array_merge($patterns, $matches3[0]);
				$pls_medias = ($this->getParam('no_integration') > 0) ? false : $this->getMediasArt();
				$pattern_title = '#^.*/([^\./]+)(?:\.tb)*\.(?:jpg|jpeg|png|gif)$#';
				foreach ($matches3[1] as $k) {
					$value = $plxShow->plxMotor->plxRecord_arts->f($k);
					if (! empty($pls_medias) and in_array(substr($k, 5), $pls_medias) and ($sizes = getimagesize(PLX_ROOT.$value))) {
						# we have an image
						$title = ucfirst(preg_replace($pattern_title, '$1', $value));
						$replaces[] = '<img src="'.$this->plxMotor->urlRewrite($value).'" '.$sizes[3].' alt="'.substr($k, 5).'" title="'.$title.'" />';
					} else
						$replaces[] = plxUtils::strCheck([$value]);
				}
			}
			echo str_replace($patterns, $replaces, $format);
		}
	}

	/*
	 * Affiche les paramètres pour les champs supplémentaires
	 * ******************************************************** */
	public function chamPlusList() {
		global $plxMotor;

		$content = array();
		foreach($this->indices() as $idx) {
			$name = $this->getParam('name'.$idx);
			$nameField = self::PREFIX.$name;
			if (($plxMotor->mode == 'place') and $this->isStatic($idx)) {
				$static_id =  $plxMotor->cible;
				$value = addslashes(plxUtils::strCheck($plxMotor->aStats[$static_id][$nameField]));
			}
			elseif (($plxMotor->mode != 'place') and ! $this->isStatic($idx))
				$value = addslashes($plxMotor->plxRecord_arts->f($nameField));
			else
				$value = false;
			if ($value !== false) {
				$buf = array();
				foreach(array('label', 'entry', 'group', 'place') as $f) {
					switch($f) {
						case 'entry' : $n = $this->getParam($f . $idx); $buf[$f] = addslashes($this->fieldTypes[$n]) . " ($n)"; break;
						case 'place' : $n = $this->getParam($f . $idx); $buf[$f] = addslashes($this->places[$n]) . " ($n)"; break;
						default : $buf[$f] = addslashes($this->getParam($f . $idx));
					}
				}
				$content[$name] = $buf;
			}
		}
		return $content;
	}

	/*
	 * si $value se termine par '_R' on renvoie la valeur du champ
	 * si $value se termine par _L on imprime la valeur du champ, précédé de l'étiquette'
	 * sinon on imprime la valeur du champ
	 * */
	public function champArt($value) {

	}
}
?>
