# WebLoginPE 1.3.4

### Advanced web user management snippet for Evolution 1.4x

## Upgrading from 1.3.x (1.30 /1.3.1 /1.3.2)

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
