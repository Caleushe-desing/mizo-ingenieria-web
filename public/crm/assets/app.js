(function () {
	const list = document.querySelector('[data-items]');
	if (!list) return;

	const addBtn = document.querySelector('[data-add-item]');
	const totals = {
		neto: document.querySelector('[data-neto]'),
		iva: document.querySelector('[data-iva]'),
		total: document.querySelector('[data-total]'),
	};

	function rows() {
		return list.querySelectorAll('[data-item-row]');
	}

	function formatMoney(n) {
		return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	function parseMoney(value) {
		return Number(String(value).replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
	}

	function recalc() {
		let neto = 0;
		rows().forEach(function (row) {
			const qtyInput = row.querySelector('[name="item_quantity[]"]');
			const priceInput = row.querySelector('[name="item_price[]"]');
			const lineEl = row.querySelector('[data-line]');
			if (!qtyInput || !priceInput || !lineEl) return;
			const qty = Number(String(qtyInput.value).replace(',', '.')) || 0;
			const price = parseMoney(priceInput.value);
			const line = qty * price;
			neto += line;
			lineEl.textContent = formatMoney(line);
		});
		const iva = Math.round(neto * 0.19);
		if (totals.neto) totals.neto.textContent = formatMoney(neto);
		if (totals.iva) totals.iva.textContent = formatMoney(iva);
		if (totals.total) totals.total.textContent = formatMoney(neto + iva);
	}

	function bindRow(row) {
		row.querySelectorAll('input').forEach(function (input) {
			input.addEventListener('input', recalc);
		});
		const remove = row.querySelector('[data-remove]');
		if (remove) {
			remove.addEventListener('click', function () {
				if (rows().length === 1) return;
				row.remove();
				recalc();
			});
		}
	}

	rows().forEach(bindRow);
	recalc();

	if (addBtn) {
		addBtn.addEventListener('click', function () {
			const first = list.querySelector('[data-item-row]');
			if (!first) return;
			const row = first.cloneNode(true);
			row.querySelectorAll('input').forEach(function (input) {
				const name = input.getAttribute('name') || '';
				if (name === 'item_quantity[]') input.value = '1';
				else if (name === 'item_unit[]') input.value = 'un';
				else input.value = '';
			});
			const line = row.querySelector('[data-line]');
			if (line) line.textContent = '$0';
			list.appendChild(row);
			bindRow(row);
			const focus = row.querySelector('[name="item_description[]"]');
			if (focus) focus.focus();
		});
	}
})();
