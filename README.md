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
