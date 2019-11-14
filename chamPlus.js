var cps_mediasManager =  {

	frameId: 'chamPlus-medias',

	open: function(aLabel, url) {
		var myFrame = document.getElementById(this.frameId);
		if (! myFrame) {
			myFrame = document.createElement('iframe');
			myFrame.setAttribute('id', this.frameId);
			myFrame.src = url;
			aLabel.form.appendChild(myFrame);
		}
		myFrame.style.display = 'block';
		this.target = aLabel.getAttribute('for');
		this.test = 'a';
		return false;
	},

	setValue: function (aValue) {
		var target = document.getElementById(this.target);
		if (target)
			target.value = aValue;
	},

	init: function (urlBase, btnLabel, confirmLabel) {
		var id = window.id;
		if (window.frameElement && (window.frameElement.id == this.frameId)) {
			this.cibleId = window.frameElement.getAttribute('data-target');
			this.urlBase = urlBase;

			// cachez ces éléments que je ne saurais voir !!
			var aLink = document.createElement('style');
			aLink.type = 'text/css';
			aLink.innerHTML = '\
body > main > aside, #folder + input { display: none; } \
';
			document.head.appendChild(aLink);

			// change folder as you clik on select
			var aSelect = document.querySelector('#folder');
			if (aSelect)
				aSelect.setAttribute('onchange', 'this.form.submit();');

			var tbody = document.querySelector('#medias-table tbody');
			tbody.addEventListener('click',	function(event) {
				var t = event.target;
				if (t.tagName == 'A' && ! t.querySelector('img')) {
					cps_mediasManager.putValue(t);
					// this.putValue(t); why not ???
					event.preventDefault();
				}
			});
			// ajouter un bouton pour fermer
			var btn = document.createElement('input');
			btn.type = 'button';
			btn.value = btnLabel;
			btn.setAttribute('onclick', "window.frameElement.style.display = 'none';");
			btn.id = 'chamPlus-medias-close';
			var pattern1 = '#form_medias div:first-of-type';
			var target = document.querySelector(pattern1);
			if (target)
				target.appendChild(btn);
			else
				console.log('No result for querySelector(\''+pattern1+'\')');
			this.confirmLabel = confirmLabel;
		}
	},

	putValue: function (anchor1) {
		// var value = anchor1.href.replace(/^\https?:\/\/[^\/]+/, ''); // suppression du hostname
		value = anchor1.href.substr(this.urlBase.length);
		if (confirm(this.confirmLabel+':\n'+value)) {
			window.parent.cps_mediasManager.setValue(value);
			window.frameElement.style.display = 'none';
		}
		return false;
	}
}

function cpsOpenMediasManager(event) {
	event.preventDefault();
	var aLabel = event.target.parentNode;
	if (aLabel) {
		cps_mediasManager.open(aLabel, mediasManagerPath);
	}
}

function cpsPreview(event) {
	event.preventDefault();
	var aLabel = event.target.parentNode;
	var input1 = document.getElementById(aLabel.getAttribute('for'));
	var src = pluxmlRoot+input1.value;
	// alert(src);
	var cpsPreviewMedia = document.getElementById('cpsPreviewMedia');
	if (cpsPreviewMedia == undefined) {
		cpsPreviewMedia = document.createElement('div');
		cpsPreviewMedia.id = 'cpsPreviewMedia';
		var innerHTML = '<img>';
		innerHTML += '<span class="close" onclick="this.parentNode.classList.remove(\'visible\');">&Cross;</span>'
		cpsPreviewMedia.innerHTML = innerHTML;
		document.body.appendChild(cpsPreviewMedia);
	}
	var img = document.querySelector('#cpsPreviewMedia img');
	img.src = src;
	cpsPreviewMedia.classList.add('visible');
}

document.addEventListener('DOMContentLoaded', function (event) {
	var aForm = document.getElementById('form_article');
	if (aForm) {
		var filesBtn = document.querySelectorAll('.sidebar span.cps-medias');
		for (i=filesBtn.length-1; i>=0; i--) {
			filesBtn[i].addEventListener('click', cpsOpenMediasManager);
		}
		var previewBtn = document.querySelectorAll('.sidebar span.cps-preview');
		for (i=previewBtn.length-1; i>=0; i--) {
			previewBtn[i].addEventListener('click', cpsPreview);
		}
	}

});

/* ---------------- config.php ---------------------- */
(function() {
	'use strict';

	const scripts = document.scripts;
	const plugin = scripts[scripts.length - 1].dataset.plugin; //'<?php echo $plugin; ?>';
	if(typeof plugin != 'string') {
		return;
	}

	const myForm = document.getElementById(plugin + 'ConfigForm');
	if(myForm == null) { return; }

	myForm.addEventListener('submit', function(event) {
		// check for not empty input in the first column, except for the last row
		var count = 0;
		const elements = myForm.querySelectorAll('tbody tr:not(:last-of-type) td:first-of-type input');
		for(var i=0, iMax=elements.length; i<iMax; i++) {
			const node = elements[i];
			const value = node.value;
			if (value.trim() == '') {
				count++;
			} else if(! /^\w[\w-]{1,32}$/.test(value)) {
				// check if the value of name is right
				// alert('<?php $plxPlugin->lang('L_CHAMPLUS_BADNAME'); ?>');
				alert('Bad name');
				event.preventDefault();
				return;
				break;
			}
		}
		if(count == elements.length) {
			alert('No field');
			event.preventDefault();
		} else if(count > 0 && !confirm('Delete ' + count + ' fields')) {
			event.preventDefault();
		}
	});

	const helpBtn = document.getElementById('helpBtn');
	if(helpBtn != null) {
		helpBtn.addEventListener('click', function(event) {
			const box = document.getElementById(plugin + 'HelpView');
			if(box != null) {
				box.classList.add('active');
			}
			event.preventDefault();
		});

		const closeBtn = document.getElementById(plugin + 'HelpView').querySelector('.close');
		if(closeBtn != null) {
			closeBtn.addEventListener('click', function(event) {
			const box = document.getElementById(plugin + 'HelpView');
			if(box != null) {
				box.classList.remove('active');
			}
			event.preventDefault();
			});
		} else {
			console.log('Missing a button for closing');
		}
	}

	// new field
	const newFieldBtn = document.getElementById('newFieldBtn');
	if(newFieldBtn != null) {
		newFieldBtn.addEventListener('click', function(event) {
			const table = document.getElementById(plugin + 'Table');
			const lastRow = table.rows[table.rows.length - 1];
			const indice = parseInt(table.dataset.indice);
			table.dataset.indice = indice + 1;
			const newRow = document.createElement('TR');
			const template = lastRow.innerHTML.replace(/\[(\d+)\]/g, function(str, p1) {
				return '[' + (parseInt(p1) + 1) + ']';
			});
			newRow.innerHTML = template;
			table.appendChild(newRow);
			const nodes = newRow.querySelectorAll('input, select');
			for(var i=0, iMax=nodes.length; i<iMax; i++) {

				if(nodes[i].type == 'checkbox') {
					nodes[i].checked = false;
				} else if(nodes[i].tagName == 'SELECT') {
					nodes[i].selectedIndex = 0;
				} else {
					nodes[i].value = '';
				}
			}
			nodes[0].focus();
			event.preventDefault();
		});
	}
})();
/* ---------------- admin.php ---------------------- */
(function() {
	'use strict';

	const scripts = document.scripts;
	const plugin = scripts[scripts.length - 1].dataset.plugin; //'<?php echo $plugin; ?>';
	if(typeof plugin != 'string' || document.getElementById(plugin + '-arts') == null) {
		return;
	}

	const submitBtn = document.getElementById(plugin + '-submit');
	const filterBtn = document.getElementById(plugin + '-filter');
	const checkedArts = document.getElementById(plugin + 'ArtsForm').elements['idArts[]'];

	document.getElementById(plugin + '-selectAll').addEventListener('change', function(event) {
		const status = event.target.checked;
		for(var i=0, iMax=checkedArts.length; i<iMax; i++) {
			checkedArts[i].checked = status;
		}
		submitBtn.disabled = !status;
		filterBtn.disabled = status;
	});

	function checkedArtsCount() {
		var count = 0;
		if(typeof checkedArts.length == 'number') {
			for(var i=0, iMax=checkedArts.length; i<iMax; i++) {
				if(checkedArts[i].checked) { count++; }
			}
		} else if(typeof checkedArts == 'object') { // 1 seul article dans le tableau
			count = (checkedArts.checked) ? 1 : 0;
		}

		const status = (count > 0);
		submitBtn.disabled = !status;
		filterBtn.disabled = status;

		return count;
	}

	/* le champ d'un article change. On coche donc l'article */
	const fields = document.querySelectorAll('#' + plugin + '-arts input.field');
	for(var i=0, iMax=fields.length; i<iMax; i++) {
		fields[i].addEventListener('change', function(event) {
			const pattern = /^arts\[(\d{4})\].*$/;
			if(pattern.test(event.target.name)) {
				const idArt = event.target.name.replace(pattern, '$1');
				for(var i=0, iMax = checkedArts.length; i<iMax; i++) {
					if(checkedArts[i].value == idArt) {
						checkedArts[i].checked = true;
						filterBtn.disabled = true;
						submitBtn.disabled = false;
						break;
					}
				}
			}
		});
	}

	/* On efface ou on modifie un champ dans la colonne pour les articles cochés */
	document.getElementById(plugin + '-adminHead').addEventListener('click', function(event) {
		const t = event.target;
		const pattern = /.*\b(drop|edit)\.png$/;

		if(t.tagName == 'IMG' && pattern.test(t.src)) {
			const cellIndex = t.parentElement.parentElement.cellIndex;
			const count = checkedArtsCount();
			if(count == 0) {
				alert('Cochez au moins un article');
				return;
			}
			var newValue = '';
			switch(t.src.replace(pattern, "$1")) {
				case 'drop':
					if(!confirm('Effacez ' + count + ' rangées dans la colonne')) {
						return;
					}
					break;
				case 'edit':
					newValue = prompt('Nouvelle valeur pour les ' + count + ' articles');
					if(typeof newValue != 'string') {
						return;
					}
					break;
			}
			const rows = document.getElementById('chamPlus-arts').rows;
			for(var i=0, iMax=rows.length; i<iMax ; i++) {
				if(checkedArts[i].checked) {
					rows[i].cells[cellIndex].firstChild.value = newValue;
				}
			}
		}
	});

	/* On coche un article */
	document.getElementById(plugin + '-arts').addEventListener('click', function(event) {
		const t = event.target;
		if(t.tagName == 'INPUT' && t.name == 'idArts[]') {
			const count = checkedArtsCount();
		}
	}, true);

	const pagination = document.getElementById(plugin + 'Pagination')
	if(pagination != null) {
		pagination.addEventListener('click', function(event) {
			const t = event.target;
			if(t.tagName == 'BUTTON' && typeof t.dataset.page == 'string') {
				const frm = document.getElementById(plugin + 'ArtsForm');
				frm.elements.artsPage.value = t.dataset.page;
				frm.submit();
			}
		});
	}

})();
