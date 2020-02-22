(function() {
	'use strict';

	const scripts = document.scripts;
	const plugin = scripts[scripts.length - 1].dataset.plugin; //'<?php echo $plugin; ?>';
	if(typeof plugin != 'string') {
		return;
	}

	/* ------ manage overlay for thumbnail in article, statique, categorie, user.php ---- */

	const target = document.getElementById(plugin + '-modal-img');
	if(target != null) {
		const pattern = /\.tb\.(jpe?g|png|gif)$/;
		const elements = document.querySelectorAll('form img.' + plugin);
		for(var i=0, iMax=elements.length; i<iMax; i++) {
			if(pattern.test(elements[i].src)) {
				elements[i].addEventListener('click', function(event) {
					target.src = event.target.src.replace(pattern, ".$1");
					document.getElementById(plugin + '-modal').click();
				});
			}

		}
	}

	/* ---------------- config.php ---------------------- */

	const myForm = document.getElementById(plugin + 'ConfigForm');
	if(myForm != null) {
		myForm.addEventListener('submit', function(event) {
			// check for not empty input in the first column, except for the last row
			var count = 0;
			const elements = myForm.querySelectorAll('tbody td:first-of-type input');
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
				const tbody = document.getElementById(plugin + 'Table');
				const lastRow = tbody.rows[tbody.rows.length - 1];
				var newIndex = tbody.rows.length;
				const inputs = tbody.querySelectorAll('input[name^="name["]');
				if(inputs.length > 0) {
					for(var i=0, iMax=inputs.length; i<iMax; i++) {
						var value = parseInt(inputs[i].name.replace(/.*\[(\d+)\]$/, '$1'));
						if(newIndex <= value) {
							newIndex = value + 1;
						}
					}
				}
				const newRow = document.createElement('TR');
				const template = lastRow.innerHTML.replace(/\[(\d+)\]/g, '[' + newIndex + ']');
				newRow.innerHTML = template;
				// newRow.draggable = true;
				tbody.appendChild(newRow);
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
	}

	/* ---------------- admin.php ---------------------- */

	if(document.getElementById(plugin + '-arts') != null) {
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
	}

})();

function kzToggleDiv(divId, on, off) {
	const div = document.getElementById(divId);
	if(div != null) {
		if(div.style.display == 'none') {
			div.style.display = 'block';
			this.innerHTML = off;
		} else {
			div.style.display = 'none';
			this.innerHTML = on;
		}
	}
}
