/*
 * WebLoginPE documentation (1.4.x) - no jQuery required.
 */

/* Theme: "light" or "dark" chosen by the user, otherwise the system setting. */
(function () {
	var saved = null;
	try { saved = window.localStorage.getItem('wlpe-doc-theme'); } catch (e) {}
	if (saved === 'light' || saved === 'dark') {
		document.documentElement.setAttribute('data-theme', saved);
	}
})();

function wlpeCurrentTheme()
{
	var forced = document.documentElement.getAttribute('data-theme');
	if (forced === 'light' || forced === 'dark') {
		return forced;
	}
	return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
}

function wlpeUpdateThemeButton(button)
{
	var dark = wlpeCurrentTheme() === 'dark';
	button.textContent = dark ? '☀' : '☾';
	button.title = dark ? 'Light mode' : 'Dark mode';
	button.setAttribute('aria-label', button.title);
}

document.addEventListener('DOMContentLoaded', function () {
	var menu = document.getElementById('menu');
	if (!menu) {
		return;
	}
	var item = document.createElement('li');
	item.className = 'theme-switch';
	var button = document.createElement('button');
	button.type = 'button';
	button.id = 'theme-toggle';
	wlpeUpdateThemeButton(button);
	button.addEventListener('click', function () {
		var next = wlpeCurrentTheme() === 'dark' ? 'light' : 'dark';
		document.documentElement.setAttribute('data-theme', next);
		try { window.localStorage.setItem('wlpe-doc-theme', next); } catch (e) {}
		wlpeUpdateThemeButton(button);
	});
	item.appendChild(button);
	menu.appendChild(item);
});

/* Links "See ... as HTML / as plain text" in views.html */
function viewTemplate(template)
{
	window.open('../Default%20Forms/' + template + '.html', template);
}

function viewTemplateAsText(template)
{
	window.open('../Default%20Forms/' + template + '.html.txt', template);
}
