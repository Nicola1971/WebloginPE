# WebLoginPE 1.4

### Advanced web user management snippet for Evolution 1.4x

## Upgrading from 1.3.x

### Files
- Replace `webloginpe.class.php`, `webloginpe.snippet.php` and the language files in `lang/`: the class and the snippet must be updated together.
- The files used only by the removed Taconite mode (`js/jquery.packed.js`, `js/jquery.form.js`, `js/jquery.taconite.js`) and `js/btnfix.js` can be deleted. Keep `js/jquery-1.1.3.1.js` and `js/jquery.corner2.js` if you ship the HTML documentation in `docs/`, which uses them.
- Custom language files need the new string `$wlpe_lang[43]` (expired form message). If it is missing, an English fallback is used.

### New parameters in 1.4.0

| Parameter | Default | Description |
|---|---|---|
| `&allowPhoto` | `0` | Set to `1` to enable the profile photo upload. **Disabled by default**: if your forms use the `photo` field, add `&allowPhoto=`1`` or the upload is ignored. |
| `&rememberDays` | `30` | Lifetime in days of the "remember me" login (previously 5 years). |
| `&dobFormat` | `m-d-Y` | Date of birth format: `d`, `m` and `Y` separated by `-`, `/` or `.` (e.g. `d-m-Y`). Dates in `YYYY-MM-DD` format, sent by `<input type="date">`, are always accepted. |
| `&csrf` | `0` | Set to `1` to require a CSRF token on all forms that change data (login, registration, profile, password reset, account deletion, manager). Every form must then contain the `[+form.csrf+]` placeholder. |
| `&passwordHash` | `md5` | Set to `bcrypt` to store passwords with `password_hash()` instead of MD5. Use it only if WebLoginPE is the only login system of the site (see *Passwords* below). |

Removed parameters: `&loadJquery` and the `taconite` value of `&type`. `&customJs` still works.

### Passwords
- By default passwords are still stored as **MD5**, as in previous versions. The Evolution 1.4 core (manager, WebLogin snippet) and MODxAPI, used by FormLister, DocLister and other extras to log in and save web users, only understand MD5 passwords for web users: keeping MD5 guarantees that all of them keep working.
- With `&passwordHash=`bcrypt`` new and changed passwords are stored with `password_hash()` (bcrypt) and existing MD5 passwords are converted automatically at the user's next login through WebLoginPE. **Use it only if WebLoginPE is the only way your users log in**: a user whose password has been converted can no longer log in through FormLister or the core WebLogin snippet. Use the same value in every WebLoginPE call.
- bcrypt hashes are 60 characters long. If the `password` column of the `web_users` table is shorter, WebLoginPE keeps using MD5. Check it with:

  ```sql
  SHOW COLUMNS FROM modx_web_users LIKE 'password';
  ```

- Password verification always accepts both formats, so switching `&passwordHash` back to MD5 does not lock out users whose password is already bcrypt. They keep logging in through WebLoginPE, but not through other extras until they set a new password.
- Temporary passwords created by the password reset are no longer stored in clear text.
- The password is no longer stored in the session (`$_SESSION['webValid']` is now empty).

### "Remember me"
- The cookie now contains a random token instead of the password hash. Tokens are stored (hashed) in the `web_user_settings` table, renewed at every automatic login and deleted at logout and when the password changes.
- Cookies created by previous versions are no longer accepted: users who chose "remember me" will have to log in once more after the upgrade.
- The cookie is set with `HttpOnly`, `SameSite=Lax` and, on HTTPS, `Secure`. The session ID is regenerated after a successful login.

### CSRF protection
With `&csrf=`1``, add the placeholder inside every WebLoginPE form:

```html
<form action="[~[*id*]~]" method="post">
	[+form.csrf+]
	...
</form>
```

`[+form.csrf+]` outputs a hidden field; `[+wlpe.csrf+]` contains only the token value. Both placeholders are always available, so you can add them to your templates before enabling the check. A request without a valid token is not processed and the form is shown again with message 43.

### MySQL/MariaDB strict mode
With strict mode enabled (`STRICT_TRANS_TABLES`, the default on most recent servers), any `NOT NULL` column without a default value that is not listed in `&customFields` makes the registration fail with:

```
Field 'xxx' doesn't have a default value
```

This usually affects the extended attributes table (default `web_user_attributes_extended`), where columns created by older versions have no default, especially if a field was later removed from `&customFields`. Run this query (replace the schema name and the table prefix) to generate the `ALTER` statements needed:

```sql
SELECT CONCAT(
  'ALTER TABLE `', TABLE_NAME, '` MODIFY `', COLUMN_NAME, '` ', COLUMN_TYPE,
  ' NOT NULL DEFAULT ',
  IF(DATA_TYPE IN ('int','tinyint','smallint','mediumint','bigint','decimal','float','double'), '0', ''''''),
  ';') AS fix
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'your_database'
  AND TABLE_NAME IN ('modx_web_user_attributes_extended', 'modx_web_user_attributes', 'modx_web_users')
  AND IS_NULLABLE = 'NO'
  AND COLUMN_DEFAULT IS NULL
  AND EXTRA NOT LIKE '%auto_increment%'
  AND COLUMN_NAME NOT IN ('id', 'internalKey');
```

Review the generated statements before running them. For `TEXT` columns use `MODIFY \`column\` TEXT NULL` instead, since not every MySQL version accepts a default on `TEXT`.

Columns created by 1.3.3 and later already get `DEFAULT ''`.

If registrations failed before the upgrade, you may find users created in `web_users` without attributes. List them with:

```sql
SELECT wu.id, wu.username
FROM modx_web_users wu
LEFT JOIN modx_web_user_attributes wua ON wua.internalKey = wu.id
WHERE wua.id IS NULL;
```

### Other behaviour changes
- Uploaded photos are validated on their real content, never stored in the site root and always re-encoded. Only JPEG, GIF and PNG are accepted.
- Passwords are used exactly as typed: special characters (quotes, `<`, `>`, …) are now allowed.
- All `[+post.*+]`, `[+user.*+]` and `[+view.*+]` placeholders are HTML-escaped. Existing entities (e.g. `&egrave;`) are not double-encoded. If a template relied on HTML stored in a user field, it will now be shown as text.
- Fields missing from the submitted profile form no longer overwrite the stored values with an empty string. Unchecked checkboxes defined in `&inputHandler` are still saved as empty.
- Several WebLoginPE calls on the same page (e.g. a login box and the profile form) now all get their language strings.
- `[+user.age+]` returns the user's age in years (it previously returned a year). It is empty when no date of birth is set.
- On PHP 8.1+ dates are formatted without `strftime()`: day and month names follow the `LC_TIME` locale only if the `intl` extension is installed, otherwise they are in English.

### Checkbox values in `&inputHandler`
Checkboxes can send a custom value, written between the parentheses:

```
&inputHandler=`:userNewsletter:newsletter:checkbox:(yes)`
```

Without a value the checkbox sends `on` as before. A checkbox is shown checked when the stored value matches its value or is `on`. If your registration form stores a different value than the profile form (e.g. `yes` vs `on`), set the same value in `&inputHandler` and align existing data:

```sql
UPDATE modx_web_user_attributes_extended SET newsletter = 'yes' WHERE newsletter = 'on';
```

### Templates: one submit button per form
Do not use more than one `<button type="submit" name="service">` in the same form. Up to 1.3.4 the bundled `btnfix.js` (a legacy Internet Explorer fix, removed in 1.4.0) could send several `service` values, and PHP keeps the last one: in a profile form with *Save* and *Cancel* buttons this resulted in `service=cancel`, so the profile was not saved and the user was redirected to the home page. Even without that script, pressing Enter in a field submits the form with the first submit button, which may not be the one you expect.

Keep the submit button for the main action and turn the others into links:

```html
<button class="btn btn-success" type="submit" name="service" value="saveprofile">Save</button>
<a class="btn btn-danger" href="[~[(site_start)]~]">Cancel</a>
```
