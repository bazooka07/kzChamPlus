<h1 id="top">Plugin kzChamPlus</h1>
<h2>Présentation</h2>
<p>
	Le plugin kzChamPlus permet d'ajouter des champs supplémentaires aux articles et aux pages statiques. Ils peuvent être d'un des trois types suivants:
</p>
<ul>
	<li>ligne</li>
	<li>bloc de texte, uniquement possible pour les articles</li>
	<li>média, qu'on peut choisir avec le gestionnaire de médias de Pluxml</li>
</ul>
<p>
	Le plugin kzChamPlus propose 5 hooks pour afficher les champs supplémentaires ou tous les champs des articles, à utiliser dans les gabarits. Pour afficher des champs supplémentaires sur votre site, utiliser le <strong>hook kzChamPlus</strong> dans vos gabarits (<i>template</i>) article.php ou static.php ou categorie.php comme ci-dessous. Pour ces cas particuliers, on peut également utiliser ces champs supplémentaires dans l'édition des pages statiques qui sont en réalité des scripts PHP. Pour garantir une rétro-compatiblité avec l'ancien plugin chamPlus, on peut également utiliser le hook chamPlus. Il est conseillé de dupliquer les gabarits originaux en les renommant <i>( attention, les noms sont sensibles à la casse )</i> :
</p>
<ul id="kzToc"></ul>

<h2 id="kzChamPlus">Hook kzChamPlus()</h2>
<p>
	Pour afficher des champs supplémentaire sur votre site, utiliser le hook kzChamPlus dans vos gabarits (<i>template</i>) article.php, categorie.php, static.php ou siedbar.php comme ci-dessous. Vous pouvez bien sûr dupliquer et renommer vos gabarits.
</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php eval(&dollar;plxShow->callHook('kzChamPlus', &dollar;params)); ?>
</code></pre>
<p>Lorsqu'on édite une page statique dans le back-office de PluXml, il faut utiliser &dollar;this à la place de &dollar;plxShow car le hook s'exécute dans la class plxMotor, comme ceci :</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php eval(&dollar;this->callHook('kzChamPlus', &dollar;params)); ?>
&lt;p>&lt;?= $a ?>&lt;/p>
</code></pre>
<p>
	<strong>&dollar;params</strong> peut être le nom du champ sous forme d'une chaine de caractères type string. Dans ce cas, la valeur du champ sera affichée à la place du hook.
</p>
<p>
	Si <strong>&dollar;params</strong> est un champ de <strong>type média</strong>, le média sera intégré dans la page automatiquement. Si la valeur du champ finit par l'extension d'un fichier image, comme "jpg", "jpeg", "png", "svg" ou "gif", alors la balise html <strong>&lt;img src="..." /></strong> sera utilisée pour afficher l'image.
	Dans le cas contraire, on utilise la balise html <strong>&lt;a href="..." target="_blank">étiquette</a></strong> pour afficher le média dans une autre fenêtre ou un autre onglet selon la configuration du navigateur.
	Cette intégration automatique peut être désactivée dans le panneau de configuration, ou en précisant une chaîne de format comme expliqué dans le paragraphe suivant.
</p>
<p>
	<strong>&dollar;params</strong> peut être aussi un tableau de un à trois éléments :
	<ul>
	 <li>Le premier élément est obligatoire. Il correspond au nom du champ comme précédemment.</li>
	 <li>Le deuxième élément optionnel sera utilisé lorsque le champ n'est pas vide, avec une des valeurs suivantes:
		<ul>
			<li>false: la valeur du champ sera uniquement affichée comme précèdemment. C'est le choix par défaut.</li>
			<li>true: la valeur du champ sera retournée sans être affichée. Cette valeur pourra être affectée à une variable</li>
			<li>une chaine de caractères, au format HTML, contenant un ou plusieurs motifs suivants : #name#, #value#, #label" ou #group# :
			<ul>
				<li><strong>#name#</strong> affiche le nom du champ</li>
				<li><strong>#label#</strong> affiche son libellé comme dans le formulaire de saisie d'un article ou d'une page statique</li>
				<li><strong>#value#</strong> affiche sa valeur</li>
				<li><strong>#group#</strong> affiche le nom du groupe saisi dans le panneau de configuration</li>
			</ul>
			</li>
		</ul>
	</li>
	<li>Le troisième élément optionnel sera utilisé lorsque le champ est vide. Il doit être obligatoirement de type string pour être pris en compte. C'est la chaine de format, avec les mêmes possibilités que le deuxième paramètre, qui sera utilisée lorsque le champ est vide.</li>
</ul>
<p>
	Pour imprimer un champ, on peut procèder comme suit :
</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php
&dollar;params = array(
  'price',
  'le champ #name# a la valeur &lt;strong>#value#&lt;/strong> !',
  '#label# est vide.'
);
eval(&dollar;plxShow->callHook('kzChamPlus', &dollar;params));
?>
</code></pre>
<p>
	On obtient la valeur du champ simplement en passant la valeur true comme paramètre :
</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php
&dollar;params = array('price', true);
&dollar;a = &dollar;plxShow->callHook('kzChamPlus', &dollar;params);
?>
&lt;span>&lt;?= &dollar;a ?>&lt;/span>
</code></pre>
<p>
	Toutes les valeurs des nouveaux champs ajoutés par ce plugin sont stockés avec les autres champs originaux créés par PluXml dans les fichiers d'articles, de catégories, de pages statiques et d'utilisateurs au format XML.
</p><p>
	Si un nouveau champ est nommé "monchamp", alors le nom de la balise, pour stocker sa valeur, dans les fichiers d'articles <em>data/articles/*.xml</em> ou dans le fichier <em>data/configuration/statiques.xml</em> sera "<em>kzChamPlus::PREFIX . 'monchamp'</em>". kzChamPlus::PREFIX est une constante de classe défini par le plugin kzChamPlus. Par défaut, elle est égale à "cps_". Le nom doit être en minuscules et commencer par une lettre, suivie d'au maximun 32 lettres ou chiffres. Il est conseillé de saisir le libellé en minuscules. Tous les caractères sont permis. Dans les éditions, la première lettre sera forcée en majuscule.
</p>
<p>
	Il ne peut pas y avoir de champ commun entre les articles, les catégories, les utilisateurs et les pages statiques. Il n'est pas possible d'avoir un bloc de texte (<i>textarea</i>) dans les pages statiques. Pour l'édition des articles, les champs, qui ne sont pas de type bloc de texte, sont situés sur le panneau à droite de la fenêtre.
</p>

<h2 id="chamPlusList">Hook chamPlusList()</h2>
<p>Il existe également le hook <strong>chamPlusList</strong>, sans paramètre, qui retourne l'ensemble des champs sous forme de tableau. On peut l'affecter à une variable ou l'afficher sur le site avec le code suivant dans un gabarit :</p>
<pre class="kzChamPlus-code"><code class="php">&lt;pre>&lt;?php print_r(eval(&dollar;plxShow->callHook('chamPlusList'))); ?>&lt;/pre></code></pre>

<h2 id="chamPlusArticle">Hook chamPlusArticle()</h2>
<p>
	Si le hook plxShowLastArtList() doit être utilisé pour afficher les derniers articles dans le panneau latéral (sidebar), le hook chamPlusArticle() permet d'afficher dans le panneau principal les champs soit d'un article, soit de plusieurs lorqu'on boucle dans une catégorie ou pour un mot-clé. Il remplace les fonctions comme &dollar;plxShow->ArtTitle(), &dollar;plxShow->chapo(), ... en passant les champs qu'on souhaite afficher dans une chaine de format comme plxShowLastArtList(). Les codes pour afficher les champs des articles sont listés dans le tableau ci-après :
</p>
<table>
	<tr><td>#art_title</td><td>titre</td></tr>
	<tr><td>#art_url</td><td>url</td></tr>
	<tr><td>#art_id</td><td>id</td></tr>
	<tr><td>#art_author</td><td>auteur</td></tr>
	<tr><td>#art_author_mail</td><td>email de l'auteur</td></tr>
	<tr><td>#art_author_infos</td><td>infos sur l'auteur</td></tr>
	<tr><td>#art_date</td><td>date de publication au format court (jj/mm/aaaa)</td></tr>
	<tr><td>#art_hour</td><td>heure de publication au format court (hh:mm)</td></tr>
	<tr><td>#art_date_time</td><td>date de publication pour la balise time (aaaa/mm/jj hh/mm)</td></tr>
	<tr><td>#art_nbcoms</td><td>nombre de commentaires pour chaque article</td></tr>
	<tr><td>#cat_list</td><td>catégories auxquelles appartient l'article sous forme de liens</td></tr>
	<tr><td>#tag_list</td><td>catégories auxquelles appartient l'article sous forme de liens</td></tr>
	<tr><td>#art_chapo</td><td>chapô</td></tr>
	<tr><td>#art_content</td><td>contenu</td></tr>
	<tr><td>#cps_xxxx</td><td>champ supplémentaire xxxx (plusieurs champs possibles)</td></tr>
</table>
<p>
Si par exemple, nous ajoutons le champ supplémentaire xxxx, nous pouvons afficher les articles avec ce champ pour une catégorie donnée avec le gabarit, nommé par exemple categorie-kzChamPlus-article, ci-dessous pour cette catégorie. Une règle CSS simple permet d'afficher sur deux colonnes, avec le champ vignette sur la première colonne :
</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php include(dirname(__FILE__).'/header.php'); ?>
	&lt;main>
		&lt;section id="vignettes-cat">
			&lt;nav id="pagination">&lt;?php &dollar;plxShow->pagination(); ?>&lt;/nav>
			&lt;p>&lt;?php
				&dollar;plxShow->lang('CATEGORIES'); echo ':&nbsp;';
				&dollar;plxShow->catName();
				&dollar;plxShow->catDescription(' : #cat_description');
		?>&lt;/p>
&lt;?php
// On démarre la capture des sorties suivantes.
ob_start();
?>
&lt;h2>&lt;a href="#art_url">#art_title&lt;/a>&lt;/h2>
&lt;small>
	&dollar;written #art_author -
	&lt;time datetime="#art_date_time">#art_date&lt;/time> -
	#art_nbcoms
&lt;/small>
&lt;section>
	&lt;p>
		#&lt;?= kzChamPlus::PREFIX ?>xxxx
	&lt;/p>
	&lt;div>
		#art_chapo
	&lt;/div>
&lt;/section>
&lt;footer>
	&lt;small>
		&dollar;classified_in : #cat_list - &dollar;tags : #tag_list
	&lt;/small>
&lt;/footer>
&lt;?php
// On stoppe la capture et on récupère le contenu.
&dollar;format = ob_get_clean();

while(&dollar;plxShow->plxMotor->plxRecord_arts->loop()) { ?>
			&lt;article id="post-&lt;?php echo &dollar;plxShow->artId(); ?>">
&lt;?php
&dollar;written = &dollar;plxShow->getLang('WRITTEN_BY');
&dollar;classified_in = &dollar;plxShow->getLang('CLASSIFIED_IN');
&dollar;tags = &dollar;plxShow->getLang('TAGS');
eval(&dollar;plxShow->callHook('chamPlusArticle', &dollar;format));
?>
			&lt;/article>
&lt;?php } ?>
			&lt;?php &dollar;plxShow->artFeed('rss', &dollar;plxShow->catId()); ?>
		&lt;/section>
	&lt;/main>
&lt;?php include(dirname(__FILE__).'/footer.php'); ?></code></pre>
<p>A insérer dans la feuille de styles theme.css :</p>
<pre class="kzChamPlus-code"><code class="php">#vignettes-cat { display: flex; }</code></pre>
<p>
	Rappelons que Pluxml permet d'utiliser un gabarit particulier pour une catégorie et que les articles peuvent y être triés par ordre alphabétique de leurs titres.
</p>

<h2 id="plxShowLastArtList">Hook plxShowLastArtList()</h2>
<p>
	Pluxml utilise le hook <strong>plxShowLastArtList</strong> pour afficher une courte liste des articles les plus récents. Le plugin kzChamPlus possède une option pour modifier l'affichage de ce hook. Il est nécessaire de passer une chaine de format à ce hook pour définir les champs de l'article à afficher. On peut bien sûr mélanger les champs gérés par Pluxml et les champs supplémentaires gérés par le plugin kzChamPlus. Les champs supplémentaires doivent être préfixés avec la constante de class <strong>kzChamPlus::PREFIX</strong>, sans oublier le caractère # comme pour tous les champs. Si la valeur d'un champ supplémentaire correspond au chemin d'un fichier image, alors l'image sera affichée automatiquement. Supposons qu'on est un champ nommé xxxx et qui pointe vers un fichier image, alors le code suivant, à intégrer dans le gabarit sidebar.php du thème, affichera une image à côté du lien vers l'article :
</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php
  &lt;h3 id="lastestArts">
    &lt;?php &dollar;plxShow->lang('LATEST_ARTICLES'); ?>
  &lt;/h3>
  &lt;ul>
    &lt;?php &dollar;plxShow->lastArtList(
		'&lt;li>&lt;span>#' . kzChamPlus::PREFIX . 'xxxx&lt;/span> &lt;a class="#art_status" href="#art_url">#art_title&lt;/a>&lt;/li>' . PHP_EOL
	); ?>
  &lt;/ul>
?></code></pre>
<p>Le source de ce gabarit est placé dans le dossier du plugin.</p>
<p>
	Pour afficher l'image à gauche du lien, il faudra utiliser la règle CSS suivante :
</p>
<pre class="kzChamPlus-code"><code class="php">#lastestArts {display: flex;}</code></pre>

<h2 id="plxShowLastArtListContent">Hook plxShowLastArtListContent()</h2>
<p>
La version 5.5 de Pluxml introduit ce nouveau hook qui simplifie beaucoup l'intégration du plugin kzChamPlus dans Pluxml.
L'utilisation de ce nouveau hook ne modifie pas la mise en oeuvre de la fonction &dollar;plxShow->lastArtList() dans les gabarits de présentation (<i>templates</i>)
</p>

<h2 id="integrationMedias">Intégration des médias dans les chaines de format</h2>
<p>Supposons qu'il existe dans la fiche article un champ supplémentaire de type <strong>média</strong>, nommé <strong>vignette</strong> et
avec sa valeur égale à "<strong>mon_image.jpg</strong>".
On peut utiliser sa valeur dans une chaîne de format comme ceci :</p>
<pre class="kzChamPlus-code"><code class="php">&lt;?php &dollar;plxShow->lastArtList('#cps_vignette');</code></pre>
<p>Par défaut, si la valeur du champ est l'adresse d'un media sur le serveur et qu'on puisse avoir la taille de son image,
alors le rendu dans la page HTML sera :</p>
<pre class="kzChamPlus-code"><code class="php">&lt;img src="mon_image.jpg" height="xxxx" width="yyyy"
    title="Mon_image" alt="vignette" /></code></pre>
<p>On peut désactiver cette fonctionnalité en cochant la case associée dans le panneau de configuration ou en spécifiant un autre type pour ce champ, par exemple: ligne.</p>

<h2 id="astuces">Astuces pour un thème</h2>
<p>Supposons qu'avec kzChamPlus, un nouveau champ "xxx" a été ajouté à un article, une page statique, un catégorie ou un utilisateur. Il est possible de récupérer la valeur de ce champ danss le thème comme ceci :</p>
<pre class="kzChamPlus-code"><code class="php"># Pour un article :
echo $plxShow->plxMotor->plxRecord_arts->f(kzChamPlus::PREFIX . 'xxx');

# Dans l'affichage d'une catégorie :
$catId = $plxShow->plxMotor->cible;
echo $plxShow->plxMotor->aCats[$catId][kzChamPlus::PREFIX . 'xxx'];

# Dans l'affichage d'une page statique :
$staticId = $plxShow->plxMotor->cible;
echo $plxShow->plxMotor->aStats[$staticId][kzChamPlus::PREFIX . 'xxx'];

# Pour les auteurs :
foreach($plxShow->plxMotor->aUsers as $userId=>$infos) {
	printf('%25s : %30s' . PHP_EOL, $infos['login'], $infos[kzChamPlus::PREFIX . 'xxx']);
}</code></pre>

<h2 id="utilisation-avancee">Utilisation avancée</h2>
<p>Supposons que le champ userTel soit ajouté aux profils utilisateurs, il est possible d'afficher la liste en créant une page statique avec le code PHP suivant :</p>
<pre class="kzChamPlus-code"><code class="php">&lt;ul&gt;
&lt;?php
# Utilisation du champ supplémentaire "userTel" dans les "comptes utilisateurs"

foreach($this-&gt;plxMotor-&gt;aUsers as $userId=&gt;$infos) {
    $tel = $this-&gt;callHook('kzChamPlus', array('userTel', true, false, $userId));
?&gt;
&lt;li&gt;&lt;?= $userId ?&gt; : &lt;?= $infos['name'] ?&gt; &lt;?= $tel ?&gt;&lt;/li&gt;
&lt;?php
}
?&gt;
&lt;/ul&gt;
&lt;h3&gt;
    Liste détaillée des "profils utilisateurs"
&lt;/h3&gt;
&lt;pre&gt;
&lt;?php
echo htmlentities(print_r($this-&gt;plxMotor-&gt;aUsers, true));
?&gt;
&lt;/pre&gt;</code></pre>
<p>La même chose est possible pour les catégories ou les articles.</p>

<a id="kzGoTop" href="#top">Début de l'aide</a>
<script type="text/javascript">
	(function() {
		'use strict';

		const toc = document.getElementById('kzToc');
		if(toc != null) {
			Array.from(document.querySelectorAll('.main .section h2[id]')).forEach(function(item) {
				const el = document.createElement('LI');
				el.innerHTML = `<a href="#${item.id}">${item.textContent}</a>`;
				toc.appendChild(el);
			});
		}

		const el = document.getElementById('kzGoTop');
		if(el != null) {
			const top = document.getElementById('top');
			el.onclick = function(event) {
				event.preventDefault();
				top.scrollIntoView({behavior: 'smooth'});
			};
		}
	})();
</script>
