var attrs = ['for', 'id', 'name'];

function resetAttributeNames(section) {
	var tags = section.querySelectorAll('select, input, label');
	var idx = Array.from(section.parentNode.children).indexOf(section);
	tags.forEach(function (tag) {
		attrs.forEach(function (attr) {
			var attr_val = tag.getAttribute(attr);
			if (attr_val) {
				tag.setAttribute(attr, attr_val.replace(/\[prod\]\[\d+\]\[/, '\[prod\]\[' + (idx + 1) + '\]\['));
			}
		});
	});
}

// Eliminar la sección
document.addEventListener('click', function (e) {
	if (e.target && e.target.matches('.remove')) {
		e.preventDefault();
		e.target.closest('.product-mergevars').remove();
	}
});

// Clonar y reiniciar valores de la sección
document.addEventListener('click', function (e) {
	if (e.target && e.target.matches('.repeat')) {
		e.preventDefault();
		var lastRepeatingGroup = document.querySelector('.repeating:last-of-type');
		var cloned = lastRepeatingGroup.cloneNode(true);
		lastRepeatingGroup.parentNode.insertBefore(cloned, lastRepeatingGroup.nextSibling);
		cloned.querySelectorAll("input").forEach(input => input.value = "");
		cloned.querySelectorAll("select").forEach(select => select.value = "");
		resetAttributeNames(cloned);
	}
});

let chargeother = function(el) { // note, its html element
  if ('custom' !== el.value) return;
  el.parentNode.innerHTML = "<input type='text' name='" + el.name + "'/>";
};