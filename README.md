# WebLoginPE 1.3.3

### Advanced web user management snippet for Evolution 1.4x

## Updates and fixes in 1.3.3

### MySQL/MariaDB strict mode

- Register() and SaveUserProfile() always write dob and gender as integers. Empty values become 0 instead of ''.
- New DobToInt() helper converts MM-DD-YYYY dates to a timestamp and returns 0 when the date is empty or invalid.
- Columns created by CustomTable() now have default values: DEFAULT '', and DEFAULT 0 for internalKey.

### PHP 8.x compatibility (PHP 7.4 still supported)

- Fixed a fatal count() error on a boolean during group assignment in Register().
- New PostValue()/PostString() helpers read form data safely. Reads from $_REQUEST, $_SERVER, $_SESSION and $_COOKIE are guarded in both the class and the snippet.
- New FormatDate() replaces strftime(), which is deprecated since PHP 8.1. It falls back to strftime() on PHP < 8.1 and uses localized names when intl is available.
- Uninitialized variables are now set before use, and values are cast before string functions so they are never null.
- GeneratePassword() uses random_int() instead of mt_srand()/mt_rand().
- PlaceHolders() uses include instead of include_once for the country and gender codes, so it works when called twice in one request.

### Security

- The "remember me" cookie value (AutoLogin()) and the username request parameter (snippet, users/manager modes) are escaped before they reach SQL queries. internalKey values are cast to integers.
- The request.userid and request.activationkey placeholders are HTML-escaped.

### Bug fixes

- Admin notification emails no longer reach the new user. They are sent from the site address with the user set as Reply-To.
- $this->report corrected to $this->Report.
- AutoLogin() referenced an undefined $type; it now uses $this->Type.
- RemoveProfile() now passes the correct username to the OnWebDeleteUser event.
- [+user.age+] now shows the user's age instead of a year.
- CustomTable() now calls getLastError as a method.
- The success check in the snippet's activated case uses stripos() instead of preg_match() with an unescaped pattern.

### New feature

- Checkboxes defined in &inputHandler accept a custom value, for example :id:newsletter:checkbox:(si). Without a value they send on as before, and a checkbox shows as checked when the stored value matches its value or is on.

## Upgrading from 1.3.x

### Files
- Replace both `assets/snippets/webloginpe/webloginpe.class.php` and `assets/snippets/webloginpe/webloginpe.snippet.php`: the two files must be updated together.
- Snippet parameters and templates from 1.3.x keep working without changes.

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

Columns created by 1.3.3 already get `DEFAULT ''`.

If registrations failed before the upgrade, you may find users created in `web_users` without attributes. List them with:

```sql
SELECT wu.id, wu.username
FROM modx_web_users wu
LEFT JOIN modx_web_user_attributes wua ON wua.internalKey = wu.id
WHERE wua.id IS NULL;
```

### Checkbox values in `&inputHandler`
Checkboxes can now send a custom value, written between the parentheses:

```
&inputHandler=`:userNewsletter:newsletter:checkbox:(yes)`
```

Without a value the checkbox sends `on` as before. A checkbox is shown checked when the stored value matches its value or is `on`. If your registration form stores a different value than the profile form (e.g. `yes` vs `on`), set the same value in `&inputHandler` and align existing data:

```sql
UPDATE modx_web_user_attributes_extended SET newsletter = 'yes' WHERE newsletter = 'on';
```

### Templates: one submit button per form
Do not use more than one `<button type="submit" name="service">` in the same form. The bundled `btnfix.js` (a legacy Internet Explorer fix) can send several `service` values, and PHP keeps the last one: in a profile form with *Save* and *Cancel* buttons this results in `service=cancel`, so the profile is not saved and the user is redirected to the home page.

Keep the submit button for the main action and turn the others into links:

```html
<button class="btn btn-success" type="submit" name="service" value="saveprofile">Save</button>
<a class="btn btn-danger" href="[~[(site_start)]~]">Cancel</a>
```

### Placeholders
- `[+user.age+]` now returns the user's age in years (it previously returned a year). It is empty when no date of birth is set.
- On PHP 8.1+ dates are formatted without `strftime()`: day and month names follow the `LC_TIME` locale only if the `intl` extension is installed, otherwise they are in English.
